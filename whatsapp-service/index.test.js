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

// ── دورة الحياة بلا استطلاع ───────────────────────────────────────────────────
// كل ما يلي كان يوماً داخل معالج /status، فكانت الجلسة التي لا يستطلعها أحد
// تعلق إلى الأبد. الاختبارات هنا لا تستدعي /status إطلاقاً — وهذا مقصود.

const {
    isStartupExpired,
    isErrorCooledDown,
    findSessionProcesses,
    readSessionUsage,
    assessSessionUsage,
    IDLE_IO_BYTES_PER_SECOND,
    purgeSessionCaches,
    clearSingletonLocks,
    trimServiceLog,
    sessionDataDir,
    PURGEABLE_CACHES,
    SINGLETON_LOCKS,
    MAX_READY_ADOPTIONS,
    STARTUP_TIMEOUT_SECONDS,
    ERROR_RETRY_SECONDS,
    CPU_GUARD_THRESHOLD_PERCENT,
    CPU_GUARD_WINDOW_MS,
    CLOCK_TICKS_PER_SECOND,
    LOG_MAX_BYTES,
    LOG_KEEP_BYTES,
} = require('./index');

const fs = require('fs');
const os = require('os');
const path = require('path');

function startingSession(ageSeconds) {
    return {
        client: {},
        status: 'starting',
        startedAt: Date.now() - ageSeconds * 1000,
        qrCode: null,
        probing: false,
        error: null,
    };
}

function tempDir(prefix) {
    return fs.mkdtempSync(path.join(os.tmpdir(), prefix));
}

test('لا تنقضي مهلة البدء قبل وقتها', () => {
    assert.equal(isStartupExpired(startingSession(STARTUP_TIMEOUT_SECONDS - 5)), false);
});

test('تنقضي مهلة البدء بلا حاجة إلى أن يستطلعها أحد', () => {
    assert.equal(isStartupExpired(startingSession(STARTUP_TIMEOUT_SECONDS + 5)), true);
});

test('مهلة البدء لا تخصّ إلا طور البدء', () => {
    for (const status of ['loading', 'needs_scan', 'ready', 'disconnected', 'error']) {
        const session = startingSession(STARTUP_TIMEOUT_SECONDS + 5);
        session.status = status;

        assert.equal(isStartupExpired(session), false, status);
    }
});

test('لا تُسقط الجلسة الفاشلة قبل انقضاء التهدئة', () => {
    const session = { status: 'error', erroredAt: Date.now() };

    assert.equal(isErrorCooledDown(session), false);
});

test('تُسقط الجلسة الفاشلة بعد التهدئة', () => {
    const session = { status: 'error', erroredAt: Date.now() - (ERROR_RETRY_SECONDS + 5) * 1000 };

    assert.equal(isErrorCooledDown(session), true);
});

test('المكنسة تُنهي مهلة جلسة عالقة في طور البدء', () => {
    const session = startingSession(STARTUP_TIMEOUT_SECONDS + 5);
    sessions.set('test_startup', session);

    sweepStalledSessions();

    assert.equal(session.status, 'error');
    assert.match(session.error, /90/);
});

test('المكنسة تُسقط الجلسة الفاشلة بعد التهدئة فتبدأ التالية نظيفة', () => {
    sessions.set('test_drop', {
        client: null,
        status: 'error',
        erroredAt: Date.now() - (ERROR_RETRY_SECONDS + 5) * 1000,
    });

    sweepStalledSessions();

    assert.equal(sessions.has('test_drop'), false);
});

// ── سقف اعتماد الجاهزية ──────────────────────────────────────────────────────

test('تُسقط الجلسة بعد تكرار اعتماد الجاهزية بدل الدوران بلا نهاية', async () => {
    const session = loadingSession();

    for (let attempt = 0; attempt < MAX_READY_ADOPTIONS; attempt++) {
        session.status = 'loading';
        session.loadingSince = Date.now() - STALL_MS;

        await resolveStalledSession('admin_1', session);

        assert.equal(session.status, 'ready', `المحاولة ${attempt + 1}`);
    }

    session.status = 'loading';
    session.loadingSince = Date.now() - STALL_MS;

    await resolveStalledSession('admin_1', session);

    assert.equal(session.status, 'error');
    assert.match(session.error, /تحميل نفسها/);
});

// ── حارس المتصفح المعلّق ─────────────────────────────────────────────────────

const MINUTE = 60000;

/**
 * قياس دقيقة كاملة: `percent` من نواة، و`bytes` في الثانية من حركة البيانات.
 */
function afterOneMinute(previous, percent, bytesPerSecond) {
    return {
        ticks: previous.ticks + CLOCK_TICKS_PER_SECOND * 60 * (percent / 100),
        chars: previous.chars === null ? null : previous.chars + bytesPerSecond * 60,
    };
}

test('القياس الأول لا يحكم على شيء — لا أساس يُقاس عليه', () => {
    const session = {};

    assert.equal(assessSessionUsage(session, { ticks: 1000, chars: 0 }, 0), false);
    assert.deepEqual(session.usageSample, { ticks: 1000, chars: 0, at: 0 });
});

test('استهلاك تحت الحد يمحو التاريخ فلا تتراكم النوبات المتفرقة', () => {
    const session = {};
    const first = { ticks: 0, chars: 0 };

    assessSessionUsage(session, first, 0);
    assessSessionUsage(session, afterOneMinute(first, CPU_GUARD_THRESHOLD_PERCENT - 10, 0), MINUTE);

    assert.equal(session.cpuHighSince, null);
});

test('استهلاك عالٍ لكن أقصر من النافذة لا يُسقط الجلسة', () => {
    const session = {};
    let usage = { ticks: 0, chars: 0 };

    assessSessionUsage(session, usage, 0);

    for (let at = MINUTE; at < CPU_GUARD_WINDOW_MS; at += MINUTE) {
        usage = afterOneMinute(usage, 100, 0);

        assert.equal(assessSessionUsage(session, usage, at), false, `عند ${at}`);
    }
});

test('استهلاك متصل مع حركة بيانات متجمّدة طوال النافذة يُسقط الجلسة', () => {
    const session = {};
    let usage = { ticks: 0, chars: 0 };
    let verdict = false;

    assessSessionUsage(session, usage, 0);

    for (let at = MINUTE; at <= CPU_GUARD_WINDOW_MS + MINUTE; at += MINUTE) {
        // نواة كاملة، و150 بايت في الثانية — وهي أرقام المتصفح الذي علق فعلاً.
        usage = afterOneMinute(usage, 100, 150);
        verdict = assessSessionUsage(session, usage, at);
    }

    assert.equal(verdict, true);
    assert.equal(session.cpuPercent, 100);
    assert.equal(session.ioPerSecond, 150);
});

test('المزامنة الأولى تحرق نواة كاملة بحق فلا تُسقط ما دامت البيانات تتحرك', () => {
    const session = {};
    let usage = { ticks: 0, chars: 0 };

    assessSessionUsage(session, usage, 0);

    for (let at = MINUTE; at <= CPU_GUARD_WINDOW_MS * 2; at += MINUTE) {
        usage = afterOneMinute(usage, 180, IDLE_IO_BYTES_PER_SECOND * 4);

        assert.equal(assessSessionUsage(session, usage, at), false, `عند ${at}`);
    }

    assert.equal(session.cpuHighSince, null);
});

test('جهل حركة البيانات يمنع الحكم بدل أن يُسقط جلسة على نصف دليل', () => {
    const session = {};
    let usage = { ticks: 0, chars: null };

    assessSessionUsage(session, usage, 0);

    for (let at = MINUTE; at <= CPU_GUARD_WINDOW_MS * 2; at += MINUTE) {
        usage = afterOneMinute(usage, 100, 0);

        assert.equal(assessSessionUsage(session, usage, at), false, `عند ${at}`);
    }
});

test('عودة العدّاد إلى الصفر بعد إعادة تشغيل المتصفح لا تُحسب استهلاكاً', () => {
    const session = {};

    assessSessionUsage(session, { ticks: 500000, chars: 900000 }, 0);

    assert.equal(assessSessionUsage(session, { ticks: 0, chars: 0 }, MINUTE), false);
    assert.equal(session.cpuHighSince, null);
});

test('غياب العمليات لا يُحسب استهلاكاً', () => {
    const session = {};

    assessSessionUsage(session, { ticks: 1000, chars: 0 }, 0);

    assert.equal(assessSessionUsage(session, null, MINUTE), false);
});

test('غياب العمليات يُسجَّل بوقته فلا يُعاد مسح /proc في كل دورة', () => {
    const session = {};

    assessSessionUsage(session, null, MINUTE);

    assert.equal(session.usageSample.at, MINUTE);
    assert.equal(session.usageSample.ticks, null);
});

// ── التعرّف على عمليات الجلسة ────────────────────────────────────────────────

function fakeProc(clientId, entries) {
    const dir = tempDir('proc-');

    for (const [pid, { cmdline, ticks, chars }] of Object.entries(entries)) {
        fs.mkdirSync(path.join(dir, pid));
        fs.writeFileSync(path.join(dir, pid, 'cmdline'), cmdline.join('\0') + '\0');

        if (ticks !== undefined) {
            // اسم العملية بين قوسين وفيه مسافة وقوس، وهو ما يكسر أي تقسيم ساذج.
            // بين الحالة (الحقل 3) وutime (الحقل 14) عشرة حقول بالضبط.
            const before = Array(10).fill('0').join(' ');
            fs.writeFileSync(
                path.join(dir, pid, 'stat'),
                `${pid} (chrome (renderer)) S ${before} ${ticks.utime} ${ticks.stime} 0 0\n`,
            );
        }

        if (chars !== undefined) {
            fs.writeFileSync(
                path.join(dir, pid, 'io'),
                `rchar: ${chars.rchar}\nwchar: ${chars.wchar}\nsyscr: 1\nread_bytes: 4096\n`,
            );
        }
    }

    return dir;
}

test('تُعرف عمليات الجلسة بمجلد بياناتها لا بشجرة الأبناء', () => {
    const mine = `--user-data-dir=${sessionDataDir('admin_1')}`;
    const proc = fakeProc('admin_1', {
        101: { cmdline: ['chrome', mine] },
        102: { cmdline: ['chrome', '--type=renderer', mine] },
        103: { cmdline: ['chrome', `--user-data-dir=${sessionDataDir('other')}`] },
        104: { cmdline: ['node', 'index.js'] },
    });

    assert.deepEqual(findSessionProcesses('admin_1', proc).sort(), [101, 102]);
});

test('يُجمع الاستهلاك من عمليات الجلسة كلها لا من الأم وحدها', () => {
    const mine = `--user-data-dir=${sessionDataDir('admin_1')}`;
    const proc = fakeProc('admin_1', {
        // الأم هادئة، والابن هو الملتهم — وهذا ما حدث فعلاً على الخادم.
        201: { cmdline: ['chrome', mine], ticks: { utime: 100, stime: 50 }, chars: { rchar: 10, wchar: 5 } },
        202: { cmdline: ['chrome', '--type=utility', mine], ticks: { utime: 20000, stime: 800 }, chars: { rchar: 900, wchar: 85 } },
        203: { cmdline: ['chrome', `--user-data-dir=${sessionDataDir('other')}`], ticks: { utime: 9, stime: 9 }, chars: { rchar: 9, wchar: 9 } },
    });

    assert.deepEqual(readSessionUsage('admin_1', proc), { ticks: 20950, chars: 1000 });
});

test('تعذّر قراءة الإدخال/الإخراج يترك حركة البيانات مجهولة لا صفراً', () => {
    const mine = `--user-data-dir=${sessionDataDir('admin_1')}`;
    const proc = fakeProc('admin_1', {
        301: { cmdline: ['chrome', mine], ticks: { utime: 7, stime: 3 } },
    });

    assert.deepEqual(readSessionUsage('admin_1', proc), { ticks: 10, chars: null });
});

test('انعدام عمليات الجلسة يُرجع null لا صفراً', () => {
    assert.equal(readSessionUsage('admin_1', tempDir('proc-')), null);
});

// ── التراكم على القرص ────────────────────────────────────────────────────────

test('يُمسح الكاش وحده ولا يُمسّ ما تعيش فيه بيانات الارتباط', () => {
    const dir = tempDir('session-');
    const keep = ['Default/IndexedDB', 'Default/Local Storage'];

    for (const relative of [...PURGEABLE_CACHES, ...keep]) {
        fs.mkdirSync(path.join(dir, relative), { recursive: true });
        fs.writeFileSync(path.join(dir, relative, 'file'), 'x');
    }

    assert.equal(purgeSessionCaches('admin_1', dir), true);

    for (const relative of PURGEABLE_CACHES) {
        assert.equal(fs.existsSync(path.join(dir, relative)), false, relative);
    }

    for (const relative of keep) {
        assert.equal(fs.existsSync(path.join(dir, relative, 'file')), true, relative);
    }
});

test('تُحذف أقفال كروم وحدها فيُقلع المتصفح التالي على المجلد نفسه', () => {
    const dir = tempDir('session-');

    for (const name of [...SINGLETON_LOCKS, 'Default']) {
        fs.writeFileSync(path.join(dir, name), 'x');
    }

    clearSingletonLocks('admin_1', dir);

    for (const name of SINGLETON_LOCKS) {
        assert.equal(fs.existsSync(path.join(dir, name)), false, name);
    }

    assert.equal(fs.existsSync(path.join(dir, 'Default')), true);
});

test('مجلد جلسة غير موجود لا يُعدّ خطأً', () => {
    assert.equal(purgeSessionCaches('admin_1', path.join(tempDir('session-'), 'missing')), false);
});

// ── سقف السجل ────────────────────────────────────────────────────────────────

test('السجل الصغير لا يُمسّ', () => {
    const logPath = path.join(tempDir('log-'), 'node.log');
    fs.writeFileSync(logPath, 'سطر\n');

    assert.equal(trimServiceLog(logPath), false);
    assert.equal(fs.readFileSync(logPath, 'utf8'), 'سطر\n');
});

test('السجل المتضخم يُقصّ مع الإبقاء على ذيله', () => {
    const logPath = path.join(tempDir('log-'), 'node.log');
    const tail = 'آخر ما قالته الخدمة\n';

    fs.writeFileSync(logPath, 'x'.repeat(LOG_MAX_BYTES + 1024) + tail);

    assert.equal(trimServiceLog(logPath), true);

    const kept = fs.readFileSync(logPath);

    assert.equal(kept.length, LOG_KEEP_BYTES);
    assert.equal(kept.subarray(-Buffer.byteLength(tail)).toString(), tail);
});
