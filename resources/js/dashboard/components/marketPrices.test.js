import test from 'node:test';
import assert from 'node:assert';

import marketPricesPage, { THIN_MARKET } from './marketPrices.js';

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
