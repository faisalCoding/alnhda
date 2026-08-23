const test = require('node:test');
const assert = require('node:assert');

const {
    sessions,
    enterLoading,
    isLoadingStalled,
    resolveStalledSession,
    sweepStalledSessions,
    chooseStoredMessage,
    recoverMessageId,
    RECOVERY_BUDGET_MS,
    storageProbeOptions,
    STORAGE_SAFE_FIELDS,
    LOADING_STALL_SECONDS,
} = require('./index');

const STALL_MS = LOADING_STALL_SECONDS * 1000;

/**
 * جلسة بالشكل الذي يبنيه getOrCreateSession، مع عميل مزيّف يردّ ما نطلبه من
 * الفحص. العميل الحقيقي يقود متصفحاً، ولا يصلح في اختبار.
 */
function loadingSession({ state = 'CONNECTED', injected = true, fails = null } = {}) {
    return {
        client: {
            getState: async () => {
                if (fails) {
                    throw new Error(fails);
                }

                return state;
            },
            pupPage: { evaluate: async () => injected },
        },
        status: 'loading',
        qrCode: null,
        loadingPercent: 100,
        loadingSince: Date.now() - STALL_MS,
        probing: false,
        error: null,
        startedAt: Date.now(),
    };
}

test.beforeEach(() => sessions.clear());

test('لا تُعدّ الجلسة متوقفة قبل انقضاء المهلة', () => {
    const session = loadingSession();
    session.loadingSince = Date.now();

    assert.equal(isLoadingStalled(session), false);
});

test('تُعدّ الجلسة متوقفة بعد انقضاء المهلة بلا تقدّم', () => {
    assert.equal(isLoadingStalled(loadingSession()), true);
});

test('لا تُراقَب إلا الجلسات في طور التهيئة', () => {
    for (const status of ['starting', 'needs_scan', 'ready', 'disconnected', 'error']) {
        const session = loadingSession();
        session.status = status;

        assert.equal(isLoadingStalled(session), false, status);
    }
});

test('لا تُفحص جلسة قيد الفحص أصلاً', () => {
    const session = loadingSession();
    session.probing = true;

    assert.equal(isLoadingStalled(session), false);
});

test('تقدّم نسبة التحميل يُصفّر مؤقّت المراقبة', () => {
    const session = loadingSession();

    enterLoading(session, 60);

    assert.equal(isLoadingStalled(session), false);
    assert.equal(session.loadingPercent, 60);
});

test('تكرار حدث المصادقة لا يُصفّر المؤقّت فتبقى المراقبة فعّالة', () => {
    const session = loadingSession();

    enterLoading(session);
    enterLoading(session);

    assert.equal(isLoadingStalled(session), true);
});

test('دخول طور التهيئة لأول مرة يضبط المؤقّت ويمحو رمز QR', () => {
    const session = loadingSession();
    session.status = 'needs_scan';
    session.qrCode = 'qr';
    session.loadingSince = 0;

    enterLoading(session);

    assert.equal(session.status, 'loading');
    assert.equal(session.qrCode, null);
    assert.equal(isLoadingStalled(session), false);
});

test('تُعتمد الجلسة جاهزة إذا اكتمل الاتصال والحقن رغم غياب حدث الجاهزية', async () => {
    const session = loadingSession();

    await resolveStalledSession('admin_1', session);

    assert.equal(session.status, 'ready');
    assert.equal(session.probing, false);
});

test('فشل الحقن يُسجَّل خطأً يرشد إلى تثبيت نسخة واتساب ويب', async () => {
    const session = loadingSession({ injected: false });

    await resolveStalledSession('admin_1', session);

    assert.equal(session.status, 'error');
    assert.match(session.error, /WHATSAPP_WEB_VERSION/);
});

test('انقطاع القناة رغم نجاح الحقن يُسجَّل خطأً يذكر حالة الاتصال', async () => {
    const session = loadingSession({ state: 'UNPAIRED' });

    await resolveStalledSession('admin_1', session);

    assert.equal(session.status, 'error');
    assert.match(session.error, /UNPAIRED/);
});

test('عجز الجلسة عن الرد على الفحص يُسجَّل خطأً بدل بقائها معلّقة', async () => {
    const session = loadingSession({ fails: 'Session closed' });

    await resolveStalledSession('admin_1', session);

    assert.equal(session.status, 'error');
    assert.match(session.error, /Session closed/);
    assert.equal(session.probing, false);
});

test('المسح الدوري يحسم المتوقفة ولا يمس الجلسات السليمة', async () => {
    const stalled = loadingSession({ injected: false });
    const syncing = loadingSession();
    syncing.loadingSince = Date.now();
    const ready = loadingSession();
    ready.status = 'ready';

    sessions.set('admin_1', stalled);
    sessions.set('admin_2', syncing);
    sessions.set('admin_3', ready);

    sweepStalledSessions();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(stalled.status, 'error');
    assert.equal(syncing.status, 'loading');
    assert.equal(ready.status, 'ready');
});

// ── حدود فحص القاعدة ─────────────────────────────────────────────────────────
// المرور على مخزن الرسائل يجري داخل الصفحة التي تُرسل منها الرسائل، فحدٌّ مفقود
// هنا يعني تجميدها لا بطء الفحص فقط.

test('فحص القاعدة يبدأ من مخزن الرسائل بحدود معقولة', () => {
    const options = storageProbeOptions({});

    assert.equal(options.store, 'message');
    assert.equal(options.limit, 3);
    assert.equal(options.scan, 400);
    assert.equal(options.id, null);
    assert.ok(options.safeFields.includes('ack'), 'حقل التأكيد يجب أن يُكشف، فهو المقصود بالفحص');
});

test('فحص القاعدة يقصّ الأرقام الخارجة عن الحدود ويتجاهل غير الرقمية', () => {
    assert.equal(storageProbeOptions({ scan: '999999' }).scan, 5000);
    assert.equal(storageProbeOptions({ scan: '0' }).scan, 1);
    assert.equal(storageProbeOptions({ limit: '500' }).limit, 20);
    assert.equal(storageProbeOptions({ limit: 'كثير' }).limit, 3);
    assert.equal(storageProbeOptions({ scan: '' }).scan, 400);
});

test('فحص القاعدة يقبل مخزناً ومعرّفاً محددين، ويعتبر الفارغ غياباً', () => {
    const options = storageProbeOptions({ store: 'chat', id: ' true_123@g.us_ABC ' });

    assert.equal(options.store, 'chat');
    assert.equal(options.id, 'true_123@g.us_ABC');
    assert.equal(storageProbeOptions({ id: '   ' }).id, null);
});

test('الحقول المكشوفة تعريفية ولا تشمل نص الرسالة', () => {
    for (const field of ['body', 'caption', 'quotedMsg', 'notifyName']) {
        assert.ok(!STORAGE_SAFE_FIELDS.includes(field), `${field} يجب أن يبقى محجوباً`);
    }
});

// ── اختيار الرسالة من قاعدة واتساب ───────────────────────────────────────────
// هذا الاختيار هو ما يقرر أي رسالة نتتبّع تأكيدها. خطؤه لا يظهر كعطل، بل
// كتأكيد يُنسب إلى الرسالة الخطأ — وهو أسوأ من غياب التأكيد.

test('لا مرشّح يعني لا معرّف', () => {
    assert.equal(chooseStoredMessage([]), null);
    assert.equal(chooseStoredMessage(null), null);
    assert.equal(chooseStoredMessage(undefined), null);
});

test('مطابقة النص ترجّح على الأحدث', () => {
    const chosen = chooseStoredMessage([
        { id: 'مطابقة', t: 100, bodyMatches: true },
        { id: 'أحدث', t: 200, bodyMatches: false },
    ]);

    assert.equal(chosen.id, 'مطابقة');
});

test('بين المتطابقات يفوز الأحدث', () => {
    const chosen = chooseStoredMessage([
        { id: 'قديمة', t: 100, bodyMatches: true },
        { id: 'حديثة', t: 300, bodyMatches: true },
        { id: 'أحدث بلا مطابقة', t: 900, bodyMatches: false },
    ]);

    assert.equal(chosen.id, 'حديثة');
});

test('بلا أي مطابقة نصية يُؤخذ الأحدث', () => {
    const chosen = chooseStoredMessage([
        { id: 'أ', t: 100, bodyMatches: false },
        { id: 'ب', t: 400, bodyMatches: false },
    ]);

    assert.equal(chosen.id, 'ب');
});

test('الصفوف بلا ختم وقت لا تُسقط الاختيار', () => {
    const chosen = chooseStoredMessage([{ id: 'وحيدة', t: 0, bodyMatches: false }]);

    assert.equal(chosen.id, 'وحيدة');
});

// ── ميزانية الاستعادة ────────────────────────────────────────────────────────
// تجاوزها يجعل Laravel يسجّل رسالة واصلة على أنها فاشلة، وهو أسوأ من فقد
// المعرّف — فالتوقف عند الحد سلوك مقصود لا تقصير.

test('الاستعادة من سجلّ المحادثة لا تبدأ بعد نفاد المهلة', async () => {
    let touched = false;
    const client = { getChatById: async () => { touched = true; return {}; } };

    const id = await recoverMessageId(client, '966500000000@c.us', 'مرحبا', Date.now() - 1);

    assert.equal(id, null);
    assert.equal(touched, false, 'لا يجوز لمس متصفح لا وقت لانتظاره');
});

test('الاستعادة تتوقف داخل الميزانية ولا تتجاوزها', async () => {
    const client = { getChatById: async () => ({ fetchMessages: async () => [] }) };
    const started = Date.now();

    const id = await recoverMessageId(client, 'محادثة', 'مرحبا', started + 2000);

    assert.equal(id, null);
    assert.ok(Date.now() - started <= 2500, 'تجاوزت الميزانية الممنوحة لها');
});

test('ميزانية الاستعادة تبقى دون مهلة Laravel', () => {
    assert.ok(RECOVERY_BUDGET_MS < 15000, 'الميزانية يجب أن تنتهي قبل أن يستسلم Laravel');
});
