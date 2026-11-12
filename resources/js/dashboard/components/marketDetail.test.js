import test from 'node:test';
import assert from 'node:assert';

import { emptyOfferForm } from './marketDetail.js';
import marketPricesPage from './marketPrices.js';

/** The page with its requests replaced by counters, so nothing leaves the test. */
function page() {
    const component = marketPricesPage();
    component.calls = { deeds: 0, projects: 0 };
    component.loadDeeds = async () => component.calls.deeds++;
    component.loadProjects = async () => component.calls.projects++;
    component.loadOffers = async () => {};
    component.city = 'جده';

    return component;
}

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

test('measures a unit against the reference chosen, and nothing where it cannot', () => {
    const component = page();
    const unit = {
        rate: 4400,
        market: {
            kind: { scope: 'city', deals: 300, average: 4000 },
            similar: { scope: 'district', deals: 40, median: 5500 },
            offers: { scope: 'district', deals: 3, median: 4400 },
        },
    };

    assert.equal(component.gapReference, 'kind', 'the authority average until something else is chosen');
    assert.equal(component.gap(unit).direction, 'above');
    assert.ok(component.gap(unit).label.includes('فوق'));
    assert.ok(component.gapLabel().includes('الهيئة'));

    component.setGapReference('deeds');
    assert.equal(component.gap(unit).direction, 'below');
    assert.ok(component.gap(unit).label.startsWith('٢٠٪'));
    assert.ok(component.gapLabel().includes('وزارة العدل'));

    component.setGapReference('offers');
    assert.equal(component.gap(unit).direction, 'level', 'three offered properties are enough');

    component.setGapReference('nowhere');
    assert.equal(component.gapReference, 'offers', 'a reference that does not exist changes nothing');
});

test('says why a unit has no gap', () => {
    const component = page();
    const market = (deals, average) => ({ kind: { deals, average }, similar: null, offers: { deals: 2, median: 4000 } });

    assert.equal(component.gap({ rate: null, market: market(300, 4000) }), null);
    assert.ok(component.gapMissing({ rate: null, market: market(300, 4000) }).includes('لا سعر'));

    assert.equal(component.gap({ rate: 4400, market: market(4, 4000) }), null, 'four sales are not a market');
    assert.ok(component.gapMissing({ rate: 4400, market: market(4, 4000) }).includes('أقل من'));

    component.setGapReference('deeds');
    assert.equal(component.gap({ rate: 4400, market: market(300, 4000) }), null);
    assert.equal(component.gapMissing({ rate: 4400, market: market(300, 4000) }), 'لا مرجع');

    component.setGapReference('offers');
    assert.equal(component.gap({ rate: 4400, market: market(300, 4000) }), null, 'two offered properties are too few');

    assert.equal(component.gap({ rate: 4400 }), null);
});

test('reads the company units and the hand-entered ones for a city whose deeds are not kept', async () => {
    const component = page();
    let offers = 0;
    component.loadOffers = async () => offers++;
    component.deeds = { available: true };
    component.data = { deeds_available: false };

    await component.refreshDetail();

    assert.equal(component.deeds, null, 'no deeds to show for this city');
    assert.deepEqual(component.calls, { deeds: 0, projects: 1 });
    assert.equal(offers, 1);
});

test('reads the detail for the city the screen opened on before there is a list to choose from', () => {
    const component = page();
    component.city = '';
    component.data = { city: { key: 'جده', label: 'جدة' } };

    assert.equal(component.detailCity(), 'جده');
    assert.equal(new URLSearchParams(component.deedsQuery()).get('city'), 'جده');

    component.city = 'الرياض';
    assert.equal(component.detailCity(), 'الرياض');

    component.city = '';
    component.data = null;
    assert.equal(component.detailCity(), '');
});

test('names what a count is a count of', () => {
    const component = page();
    const figure = { deals: 5, scope: 'city' };
    const project = { district_label: 'الصفا' };

    assert.ok(component.countCaption(figure, project, 'صفقة').includes('صفقة'));
    assert.ok(component.countCaption(figure, project, 'وحدة').includes('وحدة'));
    assert.ok(component.countCaption(figure, project, 'وحدة').includes('المدينة كلها'));
    assert.equal(component.countCaption(null, project, 'وحدة'), '');
});

test('opens the offer form with every column a listings file has', () => {
    const form = emptyOfferForm();

    for (const field of ['source_site', 'listing_id', 'source_url', 'district', 'name', 'price', 'area', 'rooms', 'bedrooms',
        'bathrooms', 'street_width', 'facade', 'posted_on', 'listing_updated_on', 'observed_on', 'duplicates',
        'property_age', 'property_age_years']) {
        assert.ok(field in form, `the form has no ${field}`);
    }
});

test('puts every field of a listing back into the form, and nothing as the word null', () => {
    const component = page();

    component.editOffer({
        id: 9, source_site: 'aqar', listing_id: '6662806', source_url: 'https://example.com', district: 'المنار',
        district_key: 'المنار', name: 'فيلا', kind: 'فيلا', price: 3600000, area: 648, rooms: 10, bedrooms: 10,
        bathrooms: null, street_width: '25 / 12', facade: 'شرق', posted_on: '2026-10-28', listing_updated_on: null,
        observed_on: '2026-11-12', duplicates: null, property_age: '8 سنوات', property_age_years: 8, project_name: null,
    });

    assert.equal(component.offerForm.listing_id, '6662806');
    assert.equal(component.offerForm.rooms, 10);
    assert.equal(component.offerForm.bathrooms, '');
    assert.equal(component.offerForm.listing_updated_on, '');
    assert.equal(component.offerForm.street_width, '25 / 12');
    assert.equal(component.offerForm.district, 'المنار');
});

test('sums up a listings import and a listing in a few words', () => {
    const component = page();

    const summary = component.listingSummary({ added: 44, updated: 0, properties: 30, repeated: 9 });
    assert.ok(summary.includes('مرة واحدة'));
    assert.ok(!component.listingSummary({ added: 1, updated: 0, properties: 1, repeated: 0 }).includes('مرة واحدة'));
    assert.equal(component.listingSummary(null), '');

    assert.ok(component.offerDetails({ bedrooms: 7, bathrooms: 5, property_age_years: 0 }).includes('جديد'));
    assert.ok(component.offerDetails({ rooms: 7, property_age_years: null, property_age: 'أكثر من 10 سنوات' }).includes('أكثر من 10 سنوات'));
    assert.equal(component.offerDetails({}), '');
    assert.equal(component.offerStreet({ street_width: '25 / 12', facade: 'شرق' }), 'شارع 25 / 12 م · شرق');
    assert.equal(component.offerStreet({}), '');
});

test('opens the offer form empty, dated today', () => {
    const form = emptyOfferForm();

    assert.equal(form.district, '');
    assert.equal(form.kind, 'شقة');
    assert.match(form.observed_on, /^\d{4}-\d{2}-\d{2}$/);
    assert.equal(new Date(form.observed_on).toDateString(), new Date(new Date().setHours(12)).toDateString());
});

test('puts a unit back into the form to be corrected, and lets go of it', () => {
    const component = page();

    component.editOffer({
        id: 7, name: null, project_name: 'مشروع الواحة', district_key: 'الصفا', kind: 'دور',
        area: 200, price: 900000, observed_on: '2026-10-01', source_url: null,
    });

    assert.equal(component.editingOffer, 7);
    assert.equal(component.offerForm.name, '', 'a unit with no name opens with an empty field, not the word null');
    assert.equal(component.offerForm.district, 'الصفا', 'the district is chosen by its key');
    assert.equal(component.offerForm.kind, 'دور');
    assert.equal(component.offerForm.observed_on, '2026-10-01');
    assert.equal(component.offerForm.source_url, '');

    component.offerErrors = { area: ['المساحة مطلوبة.'] };
    assert.equal(component.offerError('area'), 'المساحة مطلوبة.');
    assert.equal(component.offerError('price'), '');

    component.cancelOfferEdit();
    assert.equal(component.editingOffer, null);
    assert.equal(component.offerForm.project_name, '');
    assert.deepEqual(component.offerErrors, {});
});

test('offers the market kinds for a hand-entered unit, and the usual ones before they are known', () => {
    const component = page();

    assert.ok(component.offerKinds().includes('شقة'));

    component.offers = { kinds: ['أرض', 'شقة', 'مبنى'] };
    assert.deepEqual(component.offerKinds(), ['أرض', 'شقة', 'مبنى']);

    component.offers = { kinds: [] };
    assert.ok(component.offerKinds().length > 0);
});

test('lets go of a half-typed correction when the city changes', async () => {
    const component = page();
    component.loadOffers = async () => {};
    component.data = { deeds_available: true };
    component.editOffer({ id: 7, kind: 'شقة', area: 150, price: 600000 });

    await component.refreshDetail();

    assert.equal(component.editingOffer, null, 'the unit being corrected belonged to the other city');
});
