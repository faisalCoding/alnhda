import test from 'node:test';
import assert from 'node:assert';

import marketPricesPage from './marketPrices.js';

/** The page with its requests replaced by counters, so nothing leaves the test. */
function page() {
    const component = marketPricesPage();
    component.calls = { deeds: 0, projects: 0 };
    component.loadDeeds = async () => component.calls.deeds++;
    component.loadProjects = async () => component.calls.projects++;
    component.city = 'جده';

    return component;
}

test('reads no deeds and no projects for a city whose deeds are not kept', async () => {
    const component = page();
    component.deeds = { available: true };
    component.projects = { projects: [] };
    component.data = { deeds_available: false };

    await component.refreshDetail();

    assert.equal(component.deeds, null);
    assert.equal(component.projects, null);
    assert.deepEqual(component.calls, { deeds: 0, projects: 0 });
});

test('reads the detail once per city and classification, not on every reload', async () => {
    const component = page();
    component.data = { deeds_available: true };

    await component.refreshDetail();
    await component.refreshDetail();
    assert.deepEqual(component.calls, { deeds: 1, projects: 1 }, 'opening a district reloads the page, not the deeds');

    component.classification = 'سكني';
    await component.refreshDetail();
    assert.deepEqual(component.calls, { deeds: 2, projects: 2 });

    await component.refreshDetail({ force: true });
    assert.equal(component.calls.deeds, 3, 'an upload changes the deeds whatever the city');
});

test('starts the filters afresh for a new city but keeps the chosen span', async () => {
    const component = page();
    component.data = { deeds_available: true };
    component.deedFilters = { ...component.deedFilters, district: 'الصفا', area_min: 100, page: 3, quarters: 8 };

    await component.refreshDetail();

    assert.equal(component.deedFilters.district, '');
    assert.equal(component.deedFilters.area_min, '');
    assert.equal(component.deedFilters.page, 1);
    assert.equal(component.deedFilters.quarters, 8);
});

test('asks for deeds with the filters that are set and none that are empty', () => {
    const component = page();
    component.classification = 'سكني';
    component.deedFilters = { ...component.deedFilters, district: 'الصفا', area_min: 100, area_max: '', price_min: 0 };

    const query = new URLSearchParams(component.deedsQuery());

    assert.equal(query.get('city'), 'جده');
    assert.equal(query.get('classification'), 'سكني');
    assert.equal(query.get('district'), 'الصفا');
    assert.equal(query.get('area_min'), '100');
    assert.equal(query.get('price_min'), '0', 'zero is a bound, not a blank');
    assert.equal(query.has('area_max'), false);
    assert.equal(query.get('quarters'), '4');
    assert.equal(query.get('sort'), 'date');
});

test('leaves the classification out when every classification is shown', () => {
    const component = page();

    assert.equal(new URLSearchParams(component.deedsQuery()).has('classification'), false);
});

test('sorts by a column and turns round on the second press', async () => {
    const component = page();
    component.deedFilters.page = 4;

    await component.sortDeeds('price');
    assert.equal(component.deedFilters.sort, 'price');
    assert.equal(component.deedFilters.direction, 'desc');
    assert.equal(component.deedFilters.page, 1, 'a new order starts from the first page');
    assert.equal(component.sortMark('price'), ' ↓');
    assert.equal(component.sortMark('area'), '');

    await component.sortDeeds('price');
    assert.equal(component.deedFilters.direction, 'asc');
    assert.equal(component.sortMark('price'), ' ↑');
});

test('turns pages only within the pages there are', async () => {
    const component = page();
    component.deeds = { pages: 2 };

    await component.turnDeedPage(-1);
    assert.equal(component.deedFilters.page, 1);
    assert.equal(component.calls.deeds, 0);

    await component.turnDeedPage(1);
    assert.equal(component.deedFilters.page, 2);

    await component.turnDeedPage(1);
    assert.equal(component.deedFilters.page, 2);
    assert.equal(component.calls.deeds, 1);
});

test('narrows the deeds to the district opened above them, when there are deeds', async () => {
    const component = page();

    await component.followDistrict('الصفا');
    assert.equal(component.deedFilters.district, '', 'no deeds for this city, nothing to narrow');

    component.deeds = { available: true };
    component.deedFilters.page = 3;
    await component.followDistrict('الصفا');
    assert.equal(component.deedFilters.district, 'الصفا');
    assert.equal(component.deedFilters.page, 1);

    await component.followDistrict('');
    assert.equal(component.deedFilters.district, '');
});

test('names a month and a price bracket', () => {
    const component = page();

    assert.ok(component.monthLabel('2026-01').includes('يناير'));
    assert.equal(component.monthLabel(undefined), '', 'an empty chart has no first month');
    assert.equal(component.monthLabel('nonsense'), '');

    assert.ok(component.bracketLabel({ from: 0, to: 250000 }).startsWith('أقل من'));
    assert.ok(component.bracketLabel({ from: 250000, to: 500000 }).includes('–'));
    assert.ok(component.bracketLabel({ from: 1500000, to: 2500000 }).includes('مليون'));
    assert.ok(component.bracketLabel({ from: 5000000, to: null }).endsWith('فأكثر'));
});

test('scales the months and the brackets against their own tallest', () => {
    const component = page();
    component.deeds = {
        monthly: [{ key: '2026-01', median: 2000 }, { key: '2026-02', median: 4000 }, { key: '2026-03', median: null }],
        distribution: [{ from: 0, to: 250000, deals: 5 }, { from: 250000, to: 500000, deals: 20 }, { from: 500000, to: null, deals: 0 }],
    };

    assert.equal(component.monthHeight(component.deeds.monthly[0]), '50%');
    assert.equal(component.monthHeight(component.deeds.monthly[2]), '0%');
    assert.equal(component.bracketWidth(component.deeds.distribution[0]), '25%');
    assert.equal(component.bracketWidth(component.deeds.distribution[2]), '0%');

    component.deeds = null;
    assert.equal(component.bracketWidth({ deals: 0 }), '0%', 'an empty screen still needs a divisor');
});

test('says where a market figure is from', () => {
    const component = page();
    const project = { district_label: 'الصفا' };

    assert.ok(component.scopeNote({ scope: 'district', quarter: '2025-3' }, project).includes('حي الصفا'));
    assert.ok(component.scopeNote({ scope: 'district', quarter: '2025-3' }, project).includes('الربع الثالث 2025'));
    assert.equal(component.scopeNote({ scope: 'city' }, project), 'المدينة كلها', 'deeds carry no single quarter');
    assert.equal(component.scopeNote(null, project), '');
});

test('counts the units of a project in words', () => {
    const component = page();

    assert.ok(component.unitCount({ units: [{}, {}] }).includes('وحدة'));
    assert.equal(component.unitCount({ units: [] }), 'لا وحدات');
    assert.equal(component.unitCount({}), 'لا وحدات');
});

test('says how far a unit sits from what its kind of property sold for, and nothing where it cannot', () => {
    const component = page();
    const market = (average, deals = 300) => ({ kind: { scope: 'city', deals, average }, similar: { deals: 900, median: 1 } });

    assert.equal(component.gap({ rate: 4400, market: market(4000) }).direction, 'above');
    assert.ok(component.gap({ rate: 4400, market: market(4000) }).label.includes('فوق'));
    assert.equal(component.gap({ rate: 3600, market: market(4000) }).direction, 'below');
    assert.equal(component.gap({ rate: 4000, market: market(4000) }).direction, 'level');

    assert.equal(component.gap({ rate: null, market: market(4000) }), null, 'a unit with no price');
    assert.equal(component.gap({ rate: 4400, market: { kind: null, similar: { deals: 900, median: 3000 } } }), null, 'no kind: similar deeds are not the same kind');
    assert.equal(component.gap({ rate: 4400, market: market(null) }), null);
    assert.equal(component.gap({ rate: 4400, market: market(4000, 4) }), null, 'four sales are not a market');
    assert.equal(component.gap({ rate: 4400 }), null);
});

test('asks the comparison for the ministry until the source is switched', async () => {
    const component = page();
    let reads = 0;
    component.loadProjects = async () => {
        reads++;
    };

    assert.equal(component.comparisonSource, 'ministry');
    assert.equal(new URLSearchParams(component.projectsQuery()).get('source'), 'ministry');

    await component.setComparisonSource('manual');
    assert.equal(component.comparisonSource, 'manual');
    assert.equal(new URLSearchParams(component.projectsQuery()).get('source'), 'manual');
    assert.equal(reads, 1);

    await component.setComparisonSource('manual');
    assert.equal(reads, 1, 'choosing the source that is already on does not read the report again');

    await component.setComparisonSource('nope');
    assert.equal(component.comparisonSource, 'manual');
});

test('names a comparable as a deed or as a hand-entered unit', () => {
    const component = page();
    const figure = { deals: 5, scope: 'city' };
    const project = { district_label: 'الصفا' };

    assert.ok(component.similarCaption(figure, project).includes('صفقة'));

    component.comparisonSource = 'manual';
    assert.ok(component.similarCaption(figure, project).includes('وحدة'));
    assert.equal(component.similarCaption(null, project), '');
});

test('reads the projects without deeds when the comparison source is the units entered by hand', async () => {
    const component = page();
    component.comparisonSource = 'manual';
    component.deeds = { available: true };
    component.projects = { projects: [] };
    component.data = { deeds_available: false };

    await component.refreshDetail();

    assert.equal(component.deeds, null);
    assert.equal(component.calls.projects, 1);
});
