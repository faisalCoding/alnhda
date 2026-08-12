const express = require('express');
const qrcode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(express.json());

/**
 * يقرأ متغيراً من ملف .env الخاص بـ Laravel (المجلد الأعلى) حتى يبقى المفتاح
 * معرَّفاً في مكان واحد، فلا يحتاج المشغّل إلى تمريره يدوياً عند التشغيل.
 */
function laravelEnv(key) {
    try {
        const file = fs.readFileSync(path.join(__dirname, '..', '.env'), 'utf8');
        const match = file.match(new RegExp('^' + key + '\\s*=\\s*(.*)$', 'm'));

        return match ? match[1].trim().replace(/^["']|["']$/g, '') : '';
    } catch {
        return '';
    }
}

// الخدمة داخلية فقط: يستقبل الطلبات من Laravel على نفس الخادم، لذا نربط على
// localhost حصراً ولا حاجة لـ CORS (لا يوجد اتصال من المتصفح مباشرة).
const port = parseInt(process.env.WHATSAPP_PORT || laravelEnv('WHATSAPP_PORT') || '3000', 10);
const host = process.env.WHATSAPP_BIND_HOST || '127.0.0.1';

// مفتاح سري مشترك مع Laravel: إذا عُرّف تُرفض أي طلبات بدونه.
const apiKey = process.env.WHATSAPP_API_KEY || laravelEnv('WHATSAPP_SERVICE_KEY');
app.use((req, res, next) => {
    if (apiKey && req.get('x-api-key') !== apiKey) {
        return res.status(401).json({ success: false, message: 'غير مصرح.' });
    }
    next();
});

// تخزين جميع الجلسات: Map من clientId -> بيانات الجلسة
const sessions = new Map();

// أقصى مدة نقبلها قبل وصول أول حدث من واتساب (QR أو مصادقة).
const STARTUP_TIMEOUT_SECONDS = 90;

// تبقى رسالة الخطأ ثابتة هذه المدة قبل السماح بمحاولة جديدة، حتى لا تتذبذب
// الواجهة بين "جاري التهيئة" والخطأ في كل استطلاع.
const ERROR_RETRY_SECONDS = 30;

/**
 * إنشاء أو استرجاع جلسة واتساب لمستخدم معين
 */
function getOrCreateSession(clientId) {
    if (sessions.has(clientId)) {
        return sessions.get(clientId);
    }

    const sessionData = {
        client: null,
        status: 'starting',
        qrCode: null,
        loadingPercent: 0,
        error: null,
        startedAt: Date.now(),
    };

    const client = new Client({
        authStrategy: new LocalAuth({ clientId }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                // crashpad يتطلب HOME قابلاً للكتابة ويفشل تحت systemd؛ لا حاجة له.
                '--disable-crashpad',
                '--disable-gpu',
            ],
        },
    });

    client.on('qr', (qr) => {
        console.log(`[${clientId}] QR Code جديد.`);
        sessionData.qrCode = qr;
        sessionData.status = 'needs_scan';
    });

    client.on('loading_screen', (percent) => {
        sessionData.loadingPercent = percent;
        sessionData.status = 'loading';
    });

    client.on('authenticated', () => {
        console.log(`[${clientId}] تمت المصادقة.`);
        sessionData.status = 'loading';
        sessionData.qrCode = null;
    });

    client.on('ready', () => {
        console.log(`[${clientId}] جاهز للإرسال.`);
        sessionData.status = 'ready';
        sessionData.qrCode = null;
    });

    client.on('disconnected', (reason) => {
        console.log(`[${clientId}] قُطع الاتصال: ${reason}`);

        // LOGOUT وحده يُبطل بيانات الاعتماد؛ تركها على القرص يعيد الحلقة.
        if (String(reason).toUpperCase().includes('LOGOUT')) {
            dropSession(clientId, sessionData).then(() => removeSessionFiles(clientId));

            return;
        }

        if (sessionData.status !== 'error') {
            sessionData.status = 'disconnected';
            sessionData.qrCode = null;
        }

        // إعادة التهيئة تلقائياً بعد 5 ثوانٍ — إلا إذا سُجّلت الجلسة كخطأ، فلها
        // مؤقّتها الخاص، وإلا لتذبذبت الواجهة بين الخطأ وإعادة المحاولة.
        setTimeout(() => {
            if (sessions.get(clientId) === sessionData && sessionData.status === 'disconnected') {
                dropSession(clientId, sessionData);
            }
        }, 5000);
    });

    // تتبّع تأكيد الاستلام: واتساب يُصدر ack لكل رسالة بعد إرسالها.
    client.on('message_ack', (message, ack) => {
        if (message?.id?._serialized) {
            rememberAck(message.id._serialized, ack);
        }
    });

    client.on('auth_failure', (message) => {
        console.error(`[${clientId}] فشلت المصادقة: ${message}`);
        failSession(sessionData, `فشلت المصادقة: ${message}`);
    });

    // بدون هذا الالتقاط يفشل الإقلاع بصمت وتبقى الحالة "starting" إلى الأبد.
    client.initialize().catch((error) => {
        console.error(`[${clientId}] فشلت التهيئة:`, error.message);
        failSession(sessionData, error.message);
    });

    sessionData.client = client;
    sessions.set(clientId, sessionData);

    return sessionData;
}

const AUTH_DIR = path.join(__dirname, '.wwebjs_auth');

/**
 * آخر حالة تأكيد لكل رسالة أُرسلت. تُحفظ في الذاكرة فقط — Laravel هو صاحب
 * السجل الدائم، وهذه مجرد نافذة يسحب منها التحديثات. محدودة الحجم حتى لا
 * تتضخم مع طول تشغيل الخدمة.
 */
const acks = new Map();
const ACK_LIMIT = 5000;

function rememberAck(messageId, ack) {
    if (acks.size >= ACK_LIMIT) {
        acks.delete(acks.keys().next().value);
    }

    acks.set(messageId, ack);
    queueAckPush(messageId, ack);
}

// ── دفع التأكيدات إلى Laravel فور وصولها ──────────────────────────────────────
const callbackUrl = process.env.WHATSAPP_CALLBACK_URL || laravelEnv('WHATSAPP_CALLBACK_URL');

// تُجمّع التأكيدات لثانيتين قبل الإرسال: الرسالة الواحدة تُصدر عدة تأكيدات
// متتابعة (أُرسلت ← وصلت ← قُرئت)، وإرسال طلب لكل واحد إهدار.
const PUSH_DEBOUNCE_MS = 2000;
const PUSH_RETRY_MS = 30000;
const pendingPush = new Map();
let pushTimer = null;

function queueAckPush(messageId, ack) {
    if (!callbackUrl) {
        return;
    }

    pendingPush.set(messageId, ack);

    if (pushTimer === null) {
        pushTimer = setTimeout(flushAcks, PUSH_DEBOUNCE_MS);
    }
}

async function flushAcks() {
    pushTimer = null;

    if (pendingPush.size === 0) {
        return;
    }

    const batch = Array.from(pendingPush.entries()).map(([id, ack]) => ({ id, ack }));

    try {
        const response = await fetch(callbackUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Api-Key': apiKey },
            body: JSON.stringify({ acks: batch }),
            signal: AbortSignal.timeout(10000),
        });

        if (!response.ok) {
            throw new Error(`ردّ Laravel بحالة ${response.status}`);
        }

        // لا نحذف إلا ما نجح دفعه فعلاً، وما وصل أثناء الطلب يبقى للدفعة التالية.
        for (const { id, ack } of batch) {
            if (pendingPush.get(id) === ack) {
                pendingPush.delete(id);
            }
        }
    } catch (error) {
        console.error('تعذر دفع تأكيدات الاستلام:', error.message);

        // نُعيد المحاولة لاحقاً؛ وأمر whatsapp:sync-acks يبقى شبكة أمان.
        if (pushTimer === null) {
            pushTimer = setTimeout(flushAcks, PUSH_RETRY_MS);
        }
    }
}

/**
 * بعد LOGOUT تصبح بيانات الاعتماد المحفوظة غير صالحة، وإبقاؤها يجعل المحاولة
 * التالية تُصادق ثم تُطرد فوراً — وهي حلقة «رمز QR ← ٩٩٪ ← فصل».
 */
function removeSessionFiles(clientId) {
    const dir = path.join(AUTH_DIR, `session-${clientId}`);

    if (!fs.existsSync(dir)) {
        return;
    }

    try {
        fs.rmSync(dir, { recursive: true, force: true });
        console.log(`[${clientId}] حُذفت بيانات الجلسة المنتهية.`);
    } catch (error) {
        console.error(`[${clientId}] تعذر حذف بيانات الجلسة:`, error.message);
    }
}

function failSession(session, message) {
    session.status = 'error';
    session.error = message;
    session.erroredAt = Date.now();
    session.qrCode = null;
}

/**
 * تُسقط جلسة عالقة حتى يبدأ الطلب التالي محاولة جديدة بدل الانتظار بلا نهاية.
 */
/**
 * destroy() قد يعود قبل أن يموت Chromium فعلاً (أو يُرفض إن كانت الصفحة قيد
 * الحقن)، فيبقى المتصفح يتيماً. كل جلسة مُسقطة كانت تسرّب متصفحاً كاملاً، وهو
 * ما يستنزف ذاكرة الخادم عند تكرار حلقة الفصل. لذا نُجهز على العملية بعدها.
 */
async function closeClient(clientId, client) {
    const browserProcess = client?.pupBrowser?.process?.();

    try {
        await client?.destroy();
    } catch (error) {
        console.error(`[${clientId}] تعذر إغلاق الجلسة:`, error.message);
    }

    try {
        if (browserProcess && browserProcess.exitCode === null && !browserProcess.killed) {
            browserProcess.kill('SIGKILL');
            console.log(`[${clientId}] أُغلق المتصفح المتبقي.`);
        }
    } catch (error) {
        console.error(`[${clientId}] تعذر إنهاء المتصفح:`, error.message);
    }
}

function dropSession(clientId, session) {
    console.log(`[${clientId}] إسقاط الجلسة (الحالة: ${session.status}).`);
    sessions.delete(clientId);

    return closeClient(clientId, session.client);
}

// ── GET /health ───────────────────────────────────────────────────────────────
// فحص لا ينشئ جلسة: التشخيص يجب ألا يُقلع متصفحاً ولا يترك أثراً على القرص.
app.get('/health', (req, res) => {
    const saved = fs.existsSync(AUTH_DIR)
        ? fs.readdirSync(AUTH_DIR).filter((name) => name.startsWith('session-')).map((name) => name.slice(8))
        : [];

    res.json({
        ok: true,
        uptime_seconds: Math.round(process.uptime()),
        // إجمالي ما تتبّعته من تأكيدات: يميّز "لم تصل أي تأكيدات" عن
        // "وصلت لكن بمعرفات لا تطابق ما خزّنه Laravel".
        acks_tracked: acks.size,
        acks_pending_push: pendingPush.size,
        active_sessions: Array.from(sessions.entries()).map(([id, session]) => ({
            client_id: id,
            status: session.status,
        })),
        saved_sessions: saved,
    });
});

// ── GET /status/:clientId ─────────────────────────────────────────────────────
app.get('/status/:clientId', async (req, res) => {
    const { clientId } = req.params;
    const session = getOrCreateSession(clientId);

    if (session.status === 'ready') {
        return res.json({ status: 'ready', message: 'واتساب متصل وجاهز.' });
    }

    if (session.status === 'loading') {
        return res.json({
            status: 'loading',
            message: `تمت المصادقة. جاري التهيئة... (${session.loadingPercent}%)`,
        });
    }

    if (session.status === 'needs_scan' && session.qrCode) {
        try {
            const qrImage = await qrcode.toDataURL(session.qrCode);
            return res.json({ status: 'needs_scan', qr_image: qrImage });
        } catch (err) {
            return res.status(500).json({ status: 'error', message: 'فشل في توليد QR.' });
        }
    }

    if (session.status === 'disconnected') {
        return res.json({ status: 'disconnected', message: 'انقطع الاتصال. جاري إعادة التهيئة...' });
    }

    const elapsed = Math.round((Date.now() - session.startedAt) / 1000);

    // لا حدث وصل خلال المهلة: الجلسة معلّقة، نعلّمها كخطأ بدل الدوران بلا نهاية.
    if (session.status === 'starting' && elapsed > STARTUP_TIMEOUT_SECONDS) {
        console.error(`[${clientId}] لم يصل أي حدث خلال ${elapsed} ثانية.`);
        failSession(session, `تعذر بدء الجلسة خلال ${STARTUP_TIMEOUT_SECONDS} ثانية — راجع node.log.`);
    }

    if (session.status === 'error') {
        // بعد فترة التهدئة نُسقط الجلسة ليبدأ الاستطلاع التالي محاولة نظيفة.
        if (Date.now() - session.erroredAt > ERROR_RETRY_SECONDS * 1000) {
            dropSession(clientId, session);
        }

        return res.json({
            status: 'error',
            message: session.error ?? 'فشلت تهيئة الجلسة.',
        });
    }

    return res.json({ status: 'starting', message: `جاري تهيئة الواتساب... (${elapsed} ثانية)` });
});

// ── POST /send ────────────────────────────────────────────────────────────────
app.post('/send', async (req, res) => {
    const { clientId, phone, message } = req.body;

    if (!clientId || !phone || !message) {
        return res.status(400).json({ success: false, message: 'يرجى توفير clientId ورقم الهاتف والرسالة.' });
    }

    const session = sessions.get(clientId);

    if (!session || session.status !== 'ready') {
        return res.status(503).json({ success: false, message: `الجلسة [${clientId}] غير جاهزة بعد.` });
    }

    try {
        const chatId = `${phone}@c.us`;
        const sent = await session.client.sendMessage(chatId, message);
        const messageId = sent?.id?._serialized ?? null;

        if (messageId) {
            rememberAck(messageId, sent?.ack ?? 0);
        }

        return res.json({ success: true, message: 'تم الإرسال بنجاح.', message_id: messageId });
    } catch (error) {
        console.error(`[${clientId}] خطأ في الإرسال:`, error.message);
        return res.status(500).json({ success: false, message: 'حدث خطأ أثناء الإرسال.', error: error.message });
    }
});

// ── POST /acks ────────────────────────────────────────────────────────────────
// يستعلم Laravel عن حالة تأكيد الرسائل التي ما زالت غير مؤكدة عنده.
app.post('/acks', (req, res) => {
    const ids = Array.isArray(req.body?.ids) ? req.body.ids : [];

    if (ids.length === 0) {
        return res.status(400).json({ success: false, message: 'يرجى توفير قائمة المعرفات.' });
    }

    const found = {};

    for (const id of ids) {
        if (acks.has(id)) {
            found[id] = acks.get(id);
        }
    }

    return res.json({ success: true, acks: found });
});

// ── POST /disconnect/:clientId ────────────────────────────────────────────────
app.post('/disconnect/:clientId', async (req, res) => {
    const { clientId } = req.params;
    const session = sessions.get(clientId);

    if (!session) {
        return res.json({ success: true, message: 'الجلسة غير موجودة.' });
    }

    try {
        await session.client.destroy();
        sessions.delete(clientId);
        return res.json({ success: true, message: 'تم قطع الاتصال.' });
    } catch (error) {
        return res.status(500).json({ success: false, error: error.message });
    }
});

// ── POST /reset/:clientId ────────────────────────────────────────────────
app.post('/reset/:clientId', async (req, res) => {
    const { clientId } = req.params;
    const session = sessions.get(clientId);

    if (session) {
        try {
            await session.client.destroy();
        } catch (e) {
            console.error('Error destroying client:', e);
        }
        sessions.delete(clientId);
    }

    const sessionDir = path.join(__dirname, '.wwebjs_auth', `session-${clientId}`);
    if (fs.existsSync(sessionDir)) {
        try {
            fs.rmSync(sessionDir, { recursive: true, force: true });
        } catch (e) {
            console.error('Error deleting session directory:', e);
        }
    }

    return res.json({ success: true, message: 'تم إعادة تعيين الجلسة بنجاح.' });
});

/**
 * إغلاق مرتب: بدونه يبقى Chromium حياً بعد قتل Node، ممسكاً بالمنفذ الموروث،
 * فتفشل إعادة التشغيل وتتراكم متصفحات يتيمة تلتهم ذاكرة الخادم.
 */
let shuttingDown = false;

function shutdown(signal) {
    if (shuttingDown) {
        return;
    }

    shuttingDown = true;
    console.log(`إيقاف الخدمة (${signal})...`);

    const closing = Promise.allSettled(
        Array.from(sessions.entries()).map(([id, session]) => closeClient(id, session.client))
    );

    // لا ننتظر متصفحاً عالقاً إلى ما لا نهاية.
    const deadline = new Promise((resolve) => setTimeout(resolve, 8000));

    Promise.race([closing, deadline]).then(() => process.exit(0));
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

// whatsapp-web.js تُصدر أحياناً أخطاء Promise غير معالجة (انقطاع جلسة، تغيّر
// واجهة واتساب ويب). نسجلها ونبقي الخدمة حية بدل أن يُنهي Node العملية.
process.on('unhandledRejection', (reason) => {
    console.error('[unhandledRejection]', reason);
});

// خطأ متزامن غير متوقع يترك العملية بحالة غير موثوقة: نسجله ونخرج،
// وsystemd (Restart=always) يعيد التشغيل خلال ثوانٍ.
process.on('uncaughtException', (err) => {
    console.error('[uncaughtException]', err);
    process.exit(1);
});

// عند الإقلاع: استعادة الجلسات المحفوظة على القرص تلقائياً حتى لا يتوقف الإرسال
// بعد إعادة تشغيل الخدمة (systemd) بانتظار فتح كل مستخدم لصفحة الإعدادات.
// تُستعاد الجلسات تباعاً (وليس دفعة واحدة) لأن تهيئة كل Chromium ثقيلة على المعالج.
const SESSION_RESTORE_GAP_MS = 20000;

function restoreSavedSessions() {
    const authDir = path.join(__dirname, '.wwebjs_auth');
    if (!fs.existsSync(authDir)) {
        return;
    }

    const clientIds = fs.readdirSync(authDir)
        .filter((name) => name.startsWith('session-'))
        .map((name) => name.substring('session-'.length));

    clientIds.forEach((clientId, index) => {
        setTimeout(() => {
            console.log(`استعادة الجلسة المحفوظة [${clientId}]...`);
            getOrCreateSession(clientId);
        }, index * SESSION_RESTORE_GAP_MS);
    });

    if (clientIds.length > 0) {
        console.log(`سيتم استعادة ${clientIds.length} جلسة محفوظة تباعاً.`);
    }
}

app.listen(port, host, () => {
    console.log(`خدمة الواتساب تعمل على http://${host}:${port}`);
    restoreSavedSessions();
});
