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

function failSession(session, message) {
    session.status = 'error';
    session.error = message;
    session.erroredAt = Date.now();
    session.qrCode = null;
}

/**
 * تُسقط جلسة عالقة حتى يبدأ الطلب التالي محاولة جديدة بدل الانتظار بلا نهاية.
 */
function dropSession(clientId, session) {
    console.log(`[${clientId}] إسقاط الجلسة (الحالة: ${session.status}).`);
    sessions.delete(clientId);

    try {
        session.client?.destroy();
    } catch (error) {
        console.error(`[${clientId}] تعذر إغلاق الجلسة:`, error.message);
    }
}

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
        await session.client.sendMessage(chatId, message);
        return res.json({ success: true, message: 'تم الإرسال بنجاح.' });
    } catch (error) {
        console.error(`[${clientId}] خطأ في الإرسال:`, error.message);
        return res.status(500).json({ success: false, message: 'حدث خطأ أثناء الإرسال.', error: error.message });
    }
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
