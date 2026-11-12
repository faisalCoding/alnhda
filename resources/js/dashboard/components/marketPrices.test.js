import test from 'node:test';
import assert from 'node:assert';

import marketPricesPage, { GROUPS, THIN_MARKET } from './marketPrices.js';

function district(label, deals, median, previousMedian = null, value = deals * 1000) {
    return { key: label, label, deals, median, previous_median: previousMedian, value };
}

test('reads nothing out of a screen that is still loading', () => {
    const page = marketPricesPage();

    assert.equal(page.data, null);
    assert.equal(page.hasData, false);
    assert.deepEqual(page.quarters, []);
    assert.deepEqual(page.waiting, []);
    assert.deepEqual(page.districts, []);
    assert.equal(page.comparedWith, '');
});

test('lists the quarters newest first without reordering what the server sent', () => {
    const page = marketPricesPage();
    page.data = { quarters: [{ key: '2025-4' }, { key: '2026-1' }] };

    assert.deepEqual(page.quarters.map((quarter) => quarter.key), ['2026-1', '2025-4']);
    assert.equal(page.data.quarters[0].key, '2025-4');
});

test('names a quarter from a key or from a row', () => {
    const page = marketPricesPage();

    assert.equal(page.quarterLabel('2026-1'), 'الربع الأول 2026');
    assert.equal(page.quarterLabel({ year: 2025, quarter: 4 }), 'الربع الرابع 2025');
    assert.equal(page.quarterLabel(''), '');
    assert.equal(page.quarterLabel(null), '');
    assert.equal(page.shortQuarter(undefined), '', 'an empty chart has no first quarter to label');
});

test('says a sum of riyals the way a person would', () => {
    const page = marketPricesPage();

    assert.ok(page.money(30_096_877_517).endsWith('مليار'));
    assert.ok(page.money(4_500_000).endsWith('مليون'));
    assert.ok(!page.money(950_000).includes('مليون'));
    assert.equal(page.money(null), page.number(0));
});

test('prints a dash for a quarter with no price', () => {
    const page = marketPricesPage();

    assert.equal(page.rate(null), '—');
    assert.equal(page.rate(undefined), '—');
    assert.notEqual(page.rate(0), '—');
});

test('reports change against the year before, and nothing where there is no year before', () => {
    const page = marketPricesPage();

    assert.equal(page.change(3300, 3000).direction, 'up');
    assert.ok(page.change(3300, 3000).label.startsWith('+'));
    assert.equal(page.change(2700, 3000).direction, 'down');
    assert.equal(page.change(3000, 3000).direction, 'flat');
    assert.equal(page.change(3000, null), null);
    assert.equal(page.change(3000, 0), null, 'growth from nothing is not a percentage');
    assert.equal(page.change(undefined, 3000), null);
});

test('hides districts too quiet to price until asked', () => {
    const page = marketPricesPage();
    page.data = { districts: [district('الصفا', 40, 3500), district('ثول', THIN_MARKET - 1, 9000)] };

    assert.deepEqual(page.districts.map((row) => row.label), ['الصفا']);
    assert.equal(page.hiddenDistricts, 1);

    page.hideThin = false;
    assert.deepEqual(page.districts.map((row) => row.label), ['الصفا', 'ثول']);
});

test('keeps the opened district in the table even when it is a quiet one', () => {
    const page = marketPricesPage();
    page.data = { districts: [district('الصفا', 40, 3500), district('ثول', 1, 9000)] };
    page.district = 'ثول';

    assert.ok(page.districts.some((row) => row.label === 'ثول'));
});

test('searches and orders the districts', () => {
    const page = marketPricesPage();
    page.data = {
        districts: [
            district('الصفا', 40, 3500, 3000),
            district('الواحة', 90, 3300, 3400),
            district('الصالحية', 10, 5000),
        ],
    };

    assert.deepEqual(page.districts.map((row) => row.label), ['الواحة', 'الصفا', 'الصالحية']);

    page.sort = 'median';
    assert.deepEqual(page.districts.map((row) => row.label), ['الصالحية', 'الصفا', 'الواحة']);

    page.sort = 'change';
    assert.deepEqual(
        page.districts.map((row) => row.label),
        ['الصفا', 'الواحة', 'الصالحية'],
        'a district with no year before sorts last, not first',
    );

    page.search = 'الص';
    page.sort = 'deals';
    assert.deepEqual(page.districts.map((row) => row.label), ['الصفا', 'الصالحية']);

    assert.equal(page.data.districts[0].label, 'الصفا', 'sorting must not reorder what the server sent');
});

test('scales the bars against the tallest quarter of the chosen metric', () => {
    const page = marketPricesPage();
    const series = [
        { key: '2025-4', year: 2025, quarter: 4, median: 2000, deals: 400, value: 10 },
        { key: '2026-1', year: 2026, quarter: 1, median: 4000, deals: 100, value: 20 },
        { key: '2026-2', year: 2026, quarter: 2, median: null, deals: 0, value: 0 },
    ];

    assert.equal(page.barHeight(series, series[0]), '50%');
    assert.equal(page.barHeight(series, series[1]), '100%');
    assert.equal(page.barHeight(series, series[2]), '0%', 'a quarter with no deals draws no bar');

    page.metric = 'deals';
    assert.equal(page.barHeight(series, series[0]), '100%');
    assert.equal(page.barHeight(series, series[1]), '25%');

    assert.equal(page.peak([]), 1, 'an empty chart still needs a divisor');
    assert.equal(page.peak(undefined), 1);
});

test('labels a bar in the unit of the chosen metric', () => {
    const page = marketPricesPage();
    const point = { year: 2026, quarter: 1, median: 3200, deals: 5616, value: 4_500_000 };

    assert.ok(page.barLabel(point).includes('ر.س/م²'));

    page.metric = 'deals';
    assert.ok(page.barLabel(point).includes('صفقة'));

    page.metric = 'value';
    assert.ok(page.barLabel(point).includes('مليون'));
    assert.ok(page.barLabel(point).startsWith('الربع الأول 2026'));
});

test('prints the range of prices, and a dash where none was kept', () => {
    const page = marketPricesPage();

    assert.equal(page.range({ lower: 3200, upper: 4700 }), `${page.rate(3200)} – ${page.rate(4700)}`);
    assert.equal(page.range({ lower: 0, upper: 0 }), '—', 'a quarter counted before ranges were kept');
    assert.equal(page.range({ lower: null, upper: null }), '—');
    assert.equal(page.range(null), '—');
    assert.equal(page.range(undefined), '—');
});

test('offers no bands and no notices before the screen has loaded', () => {
    const page = marketPricesPage();

    assert.deepEqual(page.bands, []);
    assert.deepEqual(page.outdated, []);
    assert.deepEqual(page.changed, []);
    assert.equal(page.day(null), '');
});

test('picks out the quarters that need their file again', () => {
    const page = marketPricesPage();
    page.data = {
        quarters: [
            { key: '2025-3', outdated: true, file_changed: false },
            { key: '2025-4', outdated: false, file_changed: true },
            { key: '2026-1', outdated: false, file_changed: false },
        ],
    };

    assert.deepEqual(page.outdated.map((quarter) => quarter.key), ['2025-3']);
    assert.deepEqual(page.changed.map((quarter) => quarter.key), ['2025-4']);
});

test('uploads what is dropped on the screen and stops showing the drop target', async () => {
    const page = marketPricesPage();
    const seen = [];
    page.upload = async (event) => seen.push(event.target.files);
    page.dragging = true;

    await page.drop({ dataTransfer: { files: ['a.csv', 'b.csv'] } });

    assert.equal(page.dragging, false);
    assert.deepEqual(seen, [['a.csv', 'b.csv']]);

    await page.drop({});
    assert.deepEqual(seen[1], [], 'something dropped that is not a file uploads nothing');
});

test('reads no property types out of a screen that is still loading', () => {
    const page = marketPricesPage();

    assert.equal(page.types, null);
    assert.equal(page.typesQuarterNote, '');
    assert.equal(page.extremes(null), '—');
    assert.equal(page.extremes({ lowest: null, highest: null }), '—');
});

test('names the quarter the type figures are for only when it differs from the screen', () => {
    const page = marketPricesPage();
    page.data = { filters: { quarter: '2026-2' }, types: { quarter: '2026-1' } };

    assert.ok(page.typesQuarterNote.includes('الربع الأول 2026'));

    page.data = { filters: { quarter: '2026-1' }, types: { quarter: '2026-1' } };
    assert.equal(page.typesQuarterNote, '');

    page.data = { filters: { quarter: '2026-1' }, types: { quarter: null } };
    assert.equal(page.typesQuarterNote, '', 'no figures from the authority, nothing to name');
});

test('prints the lowest and highest price the authority reported', () => {
    const page = marketPricesPage();

    assert.equal(page.extremes({ lowest: 1999, highest: 8090 }), `${page.rate(1999)} – ${page.rate(8090)}`);
});

test('draws a chart in its own metric when it names one', () => {
    const page = marketPricesPage();
    const series = [
        { key: '2025-4', year: 2025, quarter: 4, average: 3700, deals: 5518 },
        { key: '2026-1', year: 2026, quarter: 1, average: null, deals: 0 },
    ];

    // The screen is on the median, which these points do not have.
    assert.equal(page.metric, 'median');
    assert.equal(page.barHeight(series, series[0], 'average'), '100%');
    assert.equal(page.barHeight(series, series[1], 'average'), '0%');
    assert.equal(page.barHeight(series, series[0]), '0%');
    assert.ok(page.barLabel(series[0], 'average').includes('صفقة'));
});

test('says which kind of file an upload turned out to be', () => {
    const page = marketPricesPage();
    const periods = [{ year: 2026, quarter: 1, deals: 12098, region: 'مكة المكرمة' }];

    assert.ok(page.importSummary(periods, 'indicators').includes('مكة المكرمة'));
    assert.ok(page.importSummary(periods, 'indicators').includes('مؤشرات'));
    assert.ok(!page.importSummary(periods, 'sales').includes('مؤشرات'));
    assert.ok(!page.importSummary(periods).includes('مؤشرات'), 'a response that names no kind is read as deeds');
});

test('sums up what an upload brought in', () => {
    const page = marketPricesPage();

    const summary = page.importSummary([
        { year: 2025, quarter: 4, deals: 44490 },
        { year: 2026, quarter: 1, deals: 32587 },
    ]);

    assert.ok(summary.includes('الربع الرابع 2025'));
    assert.ok(summary.includes('الربع الأول 2026'));
    assert.equal(page.importSummary([]), '');
});

test('opens on the board and reads a group out of the address', () => {
    const page = marketPricesPage();

    assert.equal(page.view, '');
    assert.equal(page.groupIn('#city'), 'city');
    assert.equal(page.groupIn('city'), 'city');
    assert.equal(page.groupIn(''), '');
    assert.equal(page.groupIn('#nowhere'), '', 'an address that names no group is the board');
    assert.equal(page.groupIn('#constructor'), '', 'a name every object has is not a group');
    assert.equal(page.groupIn(undefined), '');
});

test('opens a group and goes back to the board', () => {
    const page = marketPricesPage();

    page.go('deeds');
    assert.equal(page.view, 'deeds');

    page.go('');
    assert.equal(page.view, '');

    page.go('nowhere');
    assert.equal(page.view, '', 'a group that does not exist opens the board');
});

test('shows each group only the filters it reads by', () => {
    const page = marketPricesPage();

    assert.equal(page.shows('quarter'), false, 'the board has no filters');

    page.go('kingdom');
    assert.equal(page.shows('band'), true);
    assert.equal(page.shows('city'), false);

    page.go('deeds');
    assert.equal(page.shows('quarter'), false, 'the deeds have their own span of quarters');
    assert.equal(page.shows('city'), true);

    page.go('units');
    assert.equal(page.shows('classification'), false);

    page.go('files');
    assert.deepEqual(GROUPS.files, []);
    assert.equal(page.shows('city'), false);
});

test('says something on every square before anything has loaded', () => {
    const page = marketPricesPage();

    for (const group of Object.keys(GROUPS)) {
        assert.ok(page.tileFigure(group).length > 0, `the ${group} square is blank`);
    }

    assert.equal(page.tileFigure('nowhere'), '');
    assert.equal(page.filesNeedingAttention, 0);
});

test('puts the figure each group is opened for on its square', () => {
    const page = marketPricesPage();
    page.data = {
        country: { current: { median: 928 } },
        city: { label: 'جدة', current: { median: 3236 } },
        quarters: [{ key: '2025-4', outdated: true, file_changed: false }, { key: '2026-1', outdated: false, file_changed: true }],
        waiting: [{ key: '2026-2' }, { key: '2026-3' }],
        types: { rows: [{ type: 'شقة', average: 3876 }], waiting: [{ key: '2026-2' }], changed: [] },
    };
    page.deeds = { available: true, summary: { deals: 5616 } };
    page.projects = { projects: [{ units: [{}, {}] }, { units: [{}] }] };

    assert.ok(page.tileFigure('kingdom').includes('ر.س/م²'));
    assert.ok(page.tileFigure('city').startsWith('جدة'));
    assert.ok(page.tileFigure('deeds').includes('صفقة'));
    assert.ok(page.tileFigure('types').startsWith('شقة'));
    assert.ok(page.tileFigure('units').includes('وحدة'));

    assert.equal(page.tileFigure('offers'), 'لم تُضف وحدات بعد');
    page.offers = { units: [{}, {}, {}], properties: 3 };
    assert.ok(page.tileFigure('offers').includes('مرصودة'));
    page.offers = { units: Array(44).fill({}), properties: 30 };
    assert.ok(page.tileFigure('offers').includes('إعلانًا'), 'a property listed twice is counted once');

    // Two never uploaded, one replaced, one out of date, one of the authority's waiting.
    assert.equal(page.filesNeedingAttention, 5);
    assert.ok(page.tileFigure('files').includes('ينتظر'));
});

test('takes dropped files to the files group', async () => {
    const page = marketPricesPage();
    page.upload = async () => {};
    page.go('city');

    await page.drop({ dataTransfer: { files: [] } });

    assert.equal(page.view, 'files');
});
