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

// نسخة واتساب ويب المثبّتة (اختيارية). القيم المتاحة في:
// https://github.com/wppconnect-team/wa-version/tree/main/html
const webVersion = process.env.WHATSAPP_WEB_VERSION || laravelEnv('WHATSAPP_WEB_VERSION');
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

// بعد المصادقة تحقن المكتبة دوالها في صفحة واتساب ثم تُطلق حدث الجاهزية. إذا
// فشل الحقن — وهو ما يحدث حين يُحدّث واتساب واجهته — يُرمى الخطأ داخل دالة
// مكشوفة لـ puppeteer فينتهي كـ unhandledRejection مسجَّلاً فقط: لا حدث جاهزية
// ولا انقطاع ولا فشل مصادقة. بلا مراقبة تبقى الجلسة "loading" إلى الأبد،
// وتظل الواجهة تعرض "جاري مزامنة المحادثات" بلا نهاية ولا سبب.
const LOADING_STALL_SECONDS = 150;
const LOADING_PROBE_INTERVAL_MS = 10000;

// الفحص يمر عبر صفحة قد تكون ميتة، فلا ننتظر رده إلى ما لا نهاية.
const PROBE_TIMEOUT_MS = 15000;

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
        loadingSince: 0,
        probing: false,
        error: null,
        startedAt: Date.now(),
    };

    const client = new Client({
        authStrategy: new LocalAuth({ clientId }),
        // واتساب ويب يُحدّث نفسه، وقد تصبح نسخته غير متوافقة مع المكتبة فتُنهي
        // الجلسة بـ LOGOUT أثناء المزامنة. تثبيت نسخة معروفة يتجاوز ذلك.
        ...(webVersion
            ? {
                webVersionCache: {
                    type: 'remote',
                    remotePath: `https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/${webVersion}.html`,
                },
            }
            : {}),
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
        enterLoading(sessionData, percent);
    });

    client.on('authenticated', () => {
        console.log(`[${clientId}] تمت المصادقة.`);
        enterLoading(sessionData);
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
        // نفس دالة الاستخراج المستخدمة عند الإرسال، وإلا لن يتطابق الطرفان.
        const messageId = extractMessageId(message);

        if (messageId) {
            rememberAck(messageId, ack);
        }
    });

    // message_create يشمل ما يرسله المستخدم نفسه، فيكفي أن يكتب في المجموعة
    // ليلتقطها النظام دون انتظار رسالة من غيره.
    client.on('message_create', (message) => {
        try {
            const chatId = message.fromMe ? message.to : message.from;
            rememberGroup(clientId, chatId, message._data?.chat?.name || message._data?.chatName || null);
        } catch (error) {
            console.error(`[${clientId}] تعذر التقاط المجموعة:`, error.message);
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

/**
 * قراءة قائمة المحادثات معطوبة مع بعض نسخ واتساب ويب، لكن حدث الرسالة يحمل
 * معرّف المحادثة نصاً جاهزاً. فتُلتقط المجموعات مما يمرّ بها من رسائل بدل
 * الاستعلام عن مخزن قد لا يكون مُهيّأ أصلاً.
 */
const seenGroups = new Map();
const SEEN_GROUPS_LIMIT = 50;

function rememberGroup(clientId, chatId, name) {
    if (typeof chatId !== 'string' || !chatId.endsWith('@g.us')) {
        return;
    }

    if (!seenGroups.has(clientId)) {
        seenGroups.set(clientId, new Map());
    }

    const forClient = seenGroups.get(clientId);
    const existing = forClient.get(chatId);

    forClient.set(chatId, {
        id: chatId,
        // الاسم قد لا يصل مع كل رسالة، فلا يُمحى اسم عُرف سابقاً.
        name: name || existing?.name || null,
        lastSeenAt: Date.now(),
    });

    if (forClient.size > SEEN_GROUPS_LIMIT) {
        const oldest = [...forClient.values()].sort((a, b) => a.lastSeenAt - b.lastSeenAt)[0];
        forClient.delete(oldest.id);
    }
}

const AUTH_DIR = path.join(__dirname, '.wwebjs_auth');

/**
 * يُرفع عند تغيّر ما تتوقعه Laravel من هذه الخدمة، فتكتشف تشغيل نسخة قديمة
 * بدل أن تفشل بصمت. 2 = يُرجع معرّف الرسالة ويتتبّع تأكيدات الاستلام.
 */
const SERVICE_CONTRACT = 2;

/**
 * آخر حالة تأكيد لكل رسالة أُرسلت. تُحفظ في الذاكرة فقط — Laravel هو صاحب
 * السجل الدائم، وهذه مجرد نافذة يسحب منها التحديثات. محدودة الحجم حتى لا
 * تتضخم مع طول تشغيل الخدمة.
 */
const acks = new Map();
const ACK_LIMIT = 5000;

/**
 * معرّف الرسالة كما تُصدره whatsapp-web.js. الشكل غير مضمون بين الإصدارات:
 * قد يكون كائناً فيه _serialized، أو نصاً جاهزاً، أو أجزاءً تُركّب. نقبلها كلها
 * بدل الاعتماد على شكل واحد ثم تسجيل رسائل بلا معرّف بصمت.
 */
function extractMessageId(message) {
    const id = message?.id;

    if (!id) {
        return null;
    }

    if (typeof id === 'string') {
        return id;
    }

    if (typeof id._serialized === 'string' && id._serialized !== '') {
        return id._serialized;
    }

    if (typeof id.id === 'string' && id.id !== '') {
        return [id.fromMe, id.remote, id.id].filter((part) => part !== undefined && part !== null).join('_');
    }

    return null;
}

/**
 * يبحث عن الرسالة التي أُرسلت للتو في سجل المحادثة. يُستخدم فقط حين تعجز
 * sendMessage عن إرجاع نموذجها، فبدونه تفقد الرسالة تتبّع الاستلام نهائياً.
 * الرسالة قد تحتاج لحظة لتظهر، لذا نحاول أكثر من مرة.
 */
async function recoverMessageId(client, chatId, body, attempts = 3) {
    for (let attempt = 0; attempt < attempts; attempt++) {
        await new Promise((resolve) => setTimeout(resolve, 800));

        try {
            const chat = await client.getChatById(chatId);
            const recent = await chat.fetchMessages({ limit: 10 });

            const match = recent
                .filter((candidate) => candidate.fromMe && candidate.body === body)
                .sort((a, b) => (b.timestamp ?? 0) - (a.timestamp ?? 0))[0];

            const id = extractMessageId(match);

            if (id) {
                console.log(`[${chatId}] استُعيد معرّف الرسالة من المحادثة.`);

                return id;
            }
        } catch (error) {
            console.error(`[${chatId}] فشلت محاولة استعادة المعرّف:`, error.message);
        }
    }

    return null;
}

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

// أقل من حد Laravel (500) بهامش. بدون التقطيع تتراكم التأكيدات أثناء أي انقطاع
// حتى تتجاوز الحد، فتُرفض كل دفعة بـ 422 إلى الأبد ولا تتعافى أبدًا.
const PUSH_BATCH_SIZE = 200;

// سقف للطابور نفسه حتى لا تتضخم الذاكرة إن ظل Laravel غير متاح طويلاً.
const PUSH_QUEUE_LIMIT = 2000;

const pendingPush = new Map();
let pushTimer = null;

function queueAckPush(messageId, ack) {
    if (!callbackUrl) {
        return;
    }

    if (pendingPush.size >= PUSH_QUEUE_LIMIT) {
        pendingPush.delete(pendingPush.keys().next().value);
    }

    // القيمة قد تصل غير رقمية من المكتبة، وJSON يُسقط undefined فيفشل التحقق.
    pendingPush.set(messageId, Number.isFinite(ack) ? Math.trunc(ack) : 0);

    if (pushTimer === null) {
        pushTimer = setTimeout(flushAcks, PUSH_DEBOUNCE_MS);
    }
}

function scheduleRetry() {
    if (pushTimer === null) {
        pushTimer = setTimeout(flushAcks, PUSH_RETRY_MS);
    }
}

async function flushAcks() {
    pushTimer = null;

    if (pendingPush.size === 0) {
        return;
    }

    const batch = Array.from(pendingPush.entries())
        .slice(0, PUSH_BATCH_SIZE)
        .map(([id, ack]) => ({ id, ack }));

    try {
        const response = await fetch(callbackUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Api-Key': apiKey },
            body: JSON.stringify({ acks: batch }),
            signal: AbortSignal.timeout(10000),
        });

        if (!response.ok) {
            // نطبع جسم الرد: حالة 422 وحدها لا تقول أي حقل رُفض.
            const detail = await response.text().catch(() => '');

            throw new Error(`ردّ Laravel بحالة ${response.status} — ${detail.slice(0, 300)}`);
        }

        // لا نحذف إلا ما نجح دفعه فعلاً، وما وصل أثناء الطلب يبقى للدفعة التالية.
        for (const { id, ack } of batch) {
            if (pendingPush.get(id) === ack) {
                pendingPush.delete(id);
            }
        }

        // بقيت دفعات: تابع فورًا بدل انتظار تأكيد جديد.
        if (pendingPush.size > 0) {
            pushTimer = setTimeout(flushAcks, 100);
        }
    } catch (error) {
        console.error('تعذر دفع تأكيدات الاستلام:', error.message);
        scheduleRetry();
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
 * دخول طور التهيئة، مع ضبط اللحظة التي تُقاس منها مدة التوقف. حدث المصادقة
 * يتكرر مع كل إعادة تحميل لصفحة واتساب، ولو صفّر كل تكرار المؤقّت لأجّل
 * المراقبة إلى ما لا نهاية — فلا نُصفّره إلا عند دخول الطور أو تقدّم فعلي.
 */
function enterLoading(session, percent = null, now = Date.now()) {
    if (session.status !== 'loading' || (percent !== null && percent !== session.loadingPercent)) {
        session.loadingSince = now;
    }

    if (percent !== null) {
        session.loadingPercent = percent;
    }

    session.status = 'loading';
    session.qrCode = null;
}

function isLoadingStalled(session, now = Date.now()) {
    return session.status === 'loading'
        && ! session.probing
        && now - session.loadingSince >= LOADING_STALL_SECONDS * 1000;
}

/**
 * ما تحتاجه الجلسة فعلاً كي تُرسل: قناة متصلة بواتساب، ودوال المكتبة محقونة
 * في الصفحة. الأولى وحدها لا تكفي — حالة الاتصال تُقرأ من وحدة واتساب مباشرة
 * ولا علاقة لها بنجاح الحقن.
 */
async function probeSession(client) {
    const [state, injected] = await Promise.all([
        client.getState(),
        client.pupPage.evaluate(() => typeof window.WWebJS !== 'undefined'),
    ]);

    return { state, injected };
}

function withTimeout(promise, ms) {
    let timer = null;

    // المؤقّت يُلغى دائماً: تركه معلقاً بعد رد سريع يُبقي حدث الخروج مشغولاً
    // ويؤخّر إغلاق العملية بلا داعٍ.
    return Promise.race([
        promise,
        new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error(`انتهت مهلة الفحص (${ms} مللي ثانية).`)), ms);
        }),
    ]).finally(() => clearTimeout(timer));
}

/**
 * يحسم مصير جلسة توقفت في طور التهيئة: إما أنها جاهزة فعلاً وسقط حدث الجاهزية
 * وحده، فنعتمدها؛ أو أن الحقن فشل فلا سبيل للإرسال، فنُسجّلها خطأً برسالة
 * تقول ما العمل. الحالتان أفضل من دوران الواجهة على رسالة لا تتغير.
 */
async function resolveStalledSession(clientId, session) {
    session.probing = true;

    try {
        const { state, injected } = await withTimeout(probeSession(session.client), PROBE_TIMEOUT_MS);

        if (state === 'CONNECTED' && injected) {
            // الإرسال يعمل، لكن attachEventListeners لم يُستدعَ على الأرجح، فقد
            // لا تصل تأكيدات الاستلام. نُسجّلها بوضوح حتى لا تُلتمس في مكان آخر.
            console.log(`[${clientId}] لم يصل حدث الجاهزية رغم اكتمال الاتصال والحقن — اعتُمدت الجلسة جاهزة (قد تتأخر تأكيدات الاستلام).`);
            session.status = 'ready';
            session.qrCode = null;

            return;
        }

        console.error(`[${clientId}] توقفت التهيئة عند ${session.loadingPercent}% (الاتصال: ${state ?? 'مجهول'}، الحقن: ${injected ? 'تم' : 'فشل'}).`);

        failSession(session, injected
            ? `توقفت التهيئة عند ${session.loadingPercent}% وحالة الاتصال ${state ?? 'مجهولة'}.`
            : 'تمت المصادقة لكن فشل تحميل واجهة واتساب ويب — غالبًا لأن واتساب حدّث موقعه. ثبّت نسخة معروفة عبر WHATSAPP_WEB_VERSION في ملف .env ثم أعد تشغيل الخدمة.');
    } catch (error) {
        console.error(`[${clientId}] تعذر فحص الجلسة المتوقفة:`, error.message);
        failSession(session, `توقفت التهيئة ولم تستجب الجلسة للفحص: ${error.message}`);
    } finally {
        session.probing = false;
    }
}

function sweepStalledSessions(now = Date.now()) {
    for (const [clientId, session] of sessions) {
        if (isLoadingStalled(session, now)) {
            resolveStalledSession(clientId, session);
        }
    }
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
        contract: SERVICE_CONTRACT,
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
        const waiting = Math.round((Date.now() - session.loadingSince) / 1000);

        return res.json({
            status: 'loading',
            message: `تمت المصادقة. جاري التهيئة... (${session.loadingPercent}%، منذ ${waiting} ثانية)`,
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
    const { clientId, phone, message, groupId } = req.body;

    if (!clientId || !message || (!phone && !groupId)) {
        return res.status(400).json({ success: false, message: 'يرجى توفير clientId ورقم الهاتف أو معرّف المجموعة والرسالة.' });
    }

    const session = sessions.get(clientId);

    if (!session || session.status !== 'ready') {
        return res.status(503).json({ success: false, message: `الجلسة [${clientId}] غير جاهزة بعد.` });
    }

    try {
        // المجموعات لها لاحقة g.us@ ومعرّفها ليس رقم هاتف، فيُمرَّر كما هو.
        const chatId = groupId ? groupId : `${phone}@c.us`;
        const sent = await session.client.sendMessage(chatId, message);

        // sendMessage قد تُرجع undefined رغم وصول الرسالة: نموذج الرسالة يُسلسل
        // عبر puppeteer وقد يسقط في الطريق. اعتبارها فشلاً يكذب على المستخدم،
        // فنكتفي بفقدان التتبّع ونحاول استعادة المعرّف من المحادثة.
        let messageId = extractMessageId(sent);

        if (!messageId) {
            console.error(
                `[${clientId}] لم يصل معرّف من sendMessage (شكل id: ${JSON.stringify(sent?.id ?? null)}) — محاولة استعادته من المحادثة.`
            );

            messageId = await recoverMessageId(session.client, chatId, message);
        }

        if (messageId) {
            rememberAck(messageId, sent?.ack ?? 0);
        } else {
            console.error(`[${clientId}] تعذر تحديد معرّف الرسالة — لن يمكن تأكيد استلامها.`);
        }

        return res.json({ success: true, message: 'تم الإرسال بنجاح.', message_id: messageId });
    } catch (error) {
        console.error(`[${clientId}] خطأ في الإرسال:`, error.message);
        return res.status(500).json({ success: false, message: 'حدث خطأ أثناء الإرسال.', error: error.message });
    }
});

// ── POST /acks ────────────────────────────────────────────────────────────────
// يستعلم Laravel عن حالة تأكيد الرسائل التي ما زالت غير مؤكدة عنده.
app.get('/seen-groups/:clientId', (req, res) => {
    const forClient = seenGroups.get(req.params.clientId);
    const groups = forClient
        ? [...forClient.values()].sort((a, b) => b.lastSeenAt - a.lastSeenAt)
        : [];

    return res.json({ success: true, groups });
});

app.get('/groups/:clientId', async (req, res) => {
    const session = sessions.get(req.params.clientId);

    if (!session || session.status !== 'ready') {
        return res.status(503).json({ success: false, message: `الجلسة [${req.params.clientId}] غير جاهزة بعد.` });
    }

    try {
        // status قد يبقى ready بينما صفحة puppeteer تحتها ماتت. لمسة خفيفة على
        // العميل تكشف ذلك برسالة مفهومة بدل انهيار غامض داخل getChats.
        try {
            await session.client.getState();
        } catch (error) {
            return res.status(503).json({
                success: false,
                message: `الجلسة تبدو مرتبطة لكنها لا تستجيب (${error.message}). أعد تشغيل الخدمة ثم أعد الربط إن لزم.`,
            });
        }

        let groups;

        try {
            const chats = await session.client.getChats();

            groups = chats
                .filter((chat) => chat.isGroup)
                .map((chat) => ({
                    id: chat.id._serialized,
                    name: chat.name || chat.formattedTitle || chat.id.user,
                    participants: chat.participants ? chat.participants.length : null,
                }));
        } catch (error) {
            console.error(`[${req.params.clientId}] getChats فشل (${error.message})، ستُجرَّب المصادر البديلة.`);

            groups = await session.client.pupPage.evaluate(async () => {
                const found = new Map();
                const add = (id, name) => {
                    if (typeof id === 'string' && id.endsWith('@g.us') && !found.has(id)) {
                        found.set(id, { id, name: name || null, participants: null });
                    }
                };

                // ١. المخزن المحقون، إن كان قد التُقط أصلاً.
                try {
                    const store = window.Store?.Chat;
                    const models = store?.getModelsArray ? store.getModelsArray() : Array.from(store?.models || []);
                    for (const chat of models) {
                        add(chat?.id?._serialized, chat?.name || chat?.formattedTitle);
                    }
                } catch { /* الحقن ناقص */ }

                // ٢. قاعدة واتساب الدائمة: مستقلة عن الحقن تماماً.
                if (found.size === 0) {
                    try {
                        const db = await new Promise((resolve, reject) => {
                            const request = indexedDB.open('model-storage');
                            request.onsuccess = () => resolve(request.result);
                            request.onerror = () => reject(request.error);
                        });

                        if (db.objectStoreNames.contains('chat')) {
                            const rows = await new Promise((resolve, reject) => {
                                const request = db.transaction('chat', 'readonly').objectStore('chat').getAll();
                                request.onsuccess = () => resolve(request.result || []);
                                request.onerror = () => reject(request.error);
                            });

                            for (const row of rows) {
                                add(row?.id, row?.name || row?.subject || row?.formattedTitle);
                            }
                        }

                        db.close();
                    } catch { /* القاعدة غير متاحة */ }
                }

                // ٣. آخر ملاذ: النمط كما هو مكتوب في الصفحة المعروضة.
                if (found.size === 0) {
                    for (const id of document.documentElement.outerHTML.match(/[\d-]{10,}@g\.us/g) || []) {
                        add(id, null);
                    }
                }

                return [...found.values()];
            });
        }

        groups.sort((a, b) => String(a.name).localeCompare(String(b.name), 'ar'));

        return res.json({ success: true, groups });
    } catch (error) {
        console.error(`[${req.params.clientId}] تعذر جلب المجموعات:`, error.message);

        // السبب الحقيقي يُمرَّر كما هو: رسالة عامة تترك المستخدم بلا دليل.
        return res.status(500).json({
            success: false,
            message: `تعذر جلب قائمة المجموعات: ${error.message}`,
        });
    }
});

// ── GET /storage/:clientId ────────────────────────────────────────────────────
// فحص شكل قاعدة واتساب الداخلية قبل البناء عليها. طبقة النماذج المحقونة تنكسر
// على بعض البناءات، وتنكسر معها استعادة معرّف الرسالة وتتبّع تأكيدها — وكلاهما
// موجود في هذه القاعدة، المستقلة عن الحقن. لكن أسماء الحقول تختلف بين
// البناءات، والتخمين هنا ينتج فشلاً صامتاً آخر، فتُقرأ كما هي أولاً.
//
// المحتوى محجوب: تُكشف قيم الحقول التعريفية وحدها (معرّف، تأكيد، وقت، نوع)،
// وما عداها يُختصر إلى نوعه وحجمه. أسماء الحقول كلها تظهر — هي المطلوب.
const STORAGE_SAFE_FIELDS = [
    'id', '_serialized', 'fromMe', 'remote', 'participant', 'self', 'chatId',
    'ack', 'ackTs', 'isNewMsg', 'star', 'type', 'subtype', 'isGroupMsg',
    't', 'timestamp', 'invis',
];

/**
 * حدود الفحص. openCursor على مخزن رسائل قد يمشي على عشرات الآلاف من الصفوف،
 * وتحميلها كلها يجمّد الصفحة التي تُرسل منها الرسائل.
 *
 * @param {Record<string, unknown>} query
 */
function storageProbeOptions(query = {}) {
    const clamp = (value, fallback, min, max) => {
        const parsed = parseInt(value, 10);

        return Number.isNaN(parsed) ? fallback : Math.min(Math.max(parsed, min), max);
    };

    const id = query.id === undefined || query.id === null ? null : String(query.id).trim();

    return {
        store: query.store ? String(query.store) : 'message',
        limit: clamp(query.limit, 3, 1, 20),
        scan: clamp(query.scan, 400, 1, 5000),
        id: id === '' ? null : id,
        safeFields: STORAGE_SAFE_FIELDS,
    };
}

/**
 * تُسلسَل إلى صفحة puppeteer، فلا تصل إلى أي شيء خارج وسيطها.
 */
async function probeStorage(options) {
    const safe = new Set(options.safeFields);
    const report = { databases: null, database: 'model-storage', stores: [], meta: null, count: null, scanned: 0, rows: [], notes: [] };

    try {
        if (typeof indexedDB.databases === 'function') {
            report.databases = (await indexedDB.databases()).map((entry) => entry.name);
        }
    } catch (error) {
        report.notes.push('تعذر سرد القواعد: ' + error.message);
    }

    let db;

    try {
        db = await new Promise((resolve, reject) => {
            const request = indexedDB.open('model-storage');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error || new Error('فشل الفتح'));
            request.onblocked = () => reject(new Error('القاعدة مشغولة'));
        });
    } catch (error) {
        report.notes.push('تعذر فتح model-storage: ' + error.message);

        return report;
    }

    report.stores = [...db.objectStoreNames];

    if (!report.stores.includes(options.store)) {
        report.notes.push('المخزن "' + options.store + '" غير موجود في هذه القاعدة.');
        db.close();

        return report;
    }

    // كل استعلام في معاملة خاصة به: المعاملة تُغلق حالما يخلو دورها من الطلبات،
    // فطلب ثانٍ بعد await على الأول يرتطم بمعاملة ميتة.
    const storeIn = () => db.transaction(options.store, 'readonly').objectStore(options.store);

    const first = storeIn();
    report.meta = { keyPath: first.keyPath, autoIncrement: first.autoIncrement, indexes: [...first.indexNames] };

    report.count = await new Promise((resolve) => {
        const request = storeIn().count();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve(null);
    });

    let collected;

    try {
        collected = await new Promise((resolve, reject) => {
            const rows = [];
            const request = storeIn().openCursor(null, 'prev');

            request.onsuccess = () => {
                const cursor = request.result;

                if (!cursor || rows.length >= options.scan) {
                    resolve(rows);

                    return;
                }

                rows.push({ key: cursor.key, value: cursor.value });
                cursor.continue();
            };

            request.onerror = () => reject(request.error || new Error('فشل المرور'));
        });
    } catch (error) {
        report.notes.push('تعذر المرور على الصفوف: ' + error.message);
        db.close();

        return report;
    }

    report.scanned = collected.length;

    const shorten = (value) => {
        if (value === null) { return '<null>'; }
        if (Array.isArray(value)) { return '<مصفوفة، ' + value.length + ' عنصرًا>'; }
        if (typeof value === 'string') { return '<نص، ' + value.length + ' محرفًا>'; }
        if (typeof value === 'object') { return '<كائن: ' + Object.keys(value).join('، ') + '>'; }

        return '<' + typeof value + '>';
    };

    const reveal = (value, depth) => {
        if (value === null || typeof value !== 'object') { return value; }
        if (depth <= 0) { return shorten(value); }
        if (Array.isArray(value)) { return value.slice(0, 5).map((entry) => reveal(entry, depth - 1)); }

        const out = {};

        for (const [name, entry] of Object.entries(value)) {
            out[name] = safe.has(name) ? reveal(entry, depth - 1) : shorten(entry);
        }

        return out;
    };

    const idOf = (row) => {
        const id = row.value ? row.value.id : null;

        if (typeof id === 'string') { return id; }
        if (id && typeof id._serialized === 'string') { return id._serialized; }
        if (typeof row.key === 'string') { return row.key; }

        return null;
    };

    const stampOf = (row) => Number((row.value && (row.value.t ?? row.value.timestamp)) ?? 0) || 0;

    let chosen = collected;

    if (options.id) {
        chosen = collected.filter((row) => {
            const id = idOf(row);

            return id !== null && id.includes(options.id);
        });

        if (chosen.length === 0) {
            report.notes.push('لم يظهر المعرّف المطلوب ضمن آخر ' + collected.length + ' صف. جرّب رفع scan.');
        }
    }

    report.rows = chosen
        .sort((a, b) => stampOf(b) - stampOf(a))
        .slice(0, options.limit)
        .map((row) => ({
            key: typeof row.key === 'object' && row.key !== null ? shorten(row.key) : row.key,
            fields: Object.fromEntries(
                Object.entries(row.value || {}).map(([name, value]) => [name, safe.has(name) ? reveal(value, 2) : shorten(value)])
            ),
        }));

    db.close();

    return report;
}

app.get('/storage/:clientId', async (req, res) => {
    const session = sessions.get(req.params.clientId);

    if (!session || session.status !== 'ready') {
        return res.status(503).json({ success: false, message: `الجلسة [${req.params.clientId}] غير جاهزة بعد.` });
    }

    try {
        const report = await session.client.pupPage.evaluate(probeStorage, storageProbeOptions(req.query));

        return res.json({ success: true, ...report });
    } catch (error) {
        console.error(`[${req.params.clientId}] تعذر فحص القاعدة:`, error.message);

        return res.status(500).json({ success: false, message: `تعذر فحص القاعدة: ${error.message}` });
    }
});

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

// الاستيراد من الاختبارات يجب ألا يحجز منفذاً ولا يُقلع متصفحاً.
if (require.main === module) {
    app.listen(port, host, () => {
        console.log(`خدمة الواتساب تعمل على http://${host}:${port}`);
        console.log(`نسخة واتساب ويب: ${webVersion || 'تلقائية'} | دفع التأكيدات: ${callbackUrl ? 'مفعّل' : 'معطّل'}`);
        restoreSavedSessions();

        // المراقبة دورية وليست عند الاستطلاع فقط: الجلسات المستعادة تلقائياً لا
        // يستطلعها أحد، وتوقفها يعني توقف الإرسال بلا أثر في أي مكان.
        setInterval(sweepStalledSessions, LOADING_PROBE_INTERVAL_MS);
    });
}

module.exports = {
    sessions,
    enterLoading,
    isLoadingStalled,
    resolveStalledSession,
    sweepStalledSessions,
    extractMessageId,
    storageProbeOptions,
    STORAGE_SAFE_FIELDS,
    LOADING_STALL_SECONDS,
};
