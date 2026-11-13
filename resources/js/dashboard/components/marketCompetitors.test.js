import test from 'node:test';
import assert from 'node:assert';

import marketCompetitors, { CANDIDATE_LIMIT } from './marketCompetitors.js';

/** The competitors part of the page, with the offers and projects it reads beside it. */
function page(offers = [], projects = []) {
    return { ...marketCompetitors(), offers: { units: offers }, projects: { projects } };
}

const offer = (id, attributes = {}) => ({
    id,
    label: `فيلا ${id}`,
    kind: 'فيلا',
    district: 'المنار',
    district_key: 'منار',
    area: 400,
    listing_id: String(1000 + id),
    ...attributes,
});

const project = { id: 2 };
const villa = { id: 7, kind: 'فيلا', basis: 'land', area: 600, land_area: 450, competitors: [] };

test('offers the same kind in the project district, nearest in size first', () => {
    const component = page(
        [
            offer(1, { area: 300 }),
            offer(2, { area: 460 }),
            offer(3, { area: 440, kind: 'شقة' }),
            offer(4, { area: 450, district_key: 'صفا' }),
            offer(5, { area: 600 }),
        ],
        [{ id: 2, district: null, guessed_district: 'منار' }],
    );

    // A villa measured by its land is matched to listings by its land, not its building.
    assert.deepEqual(component.candidates(villa, project).map((item) => item.id), [2, 1, 5]);

    component.pickSameKind = false;
    component.pickSameDistrict = false;
    assert.deepEqual(component.candidates(villa, project).map((item) => item.id), [4, 2, 3, 1, 5]);
});

test('leaves out what is already chosen and narrows by a search', () => {
    const component = page([offer(1), offer(2, { listing_id: '6816659' }), offer(3)], [{ id: 2, district: 'منار' }]);
    const unit = { ...villa, competitors: [{ id: 1 }] };

    assert.deepEqual(component.candidates(unit, project).map((item) => item.id), [2, 3]);

    component.pickSearch = ' 6816659 ';
    assert.deepEqual(component.candidates(unit, project).map((item) => item.id), [2]);
});

test('does not narrow by a district or kind nobody knows', () => {
    const component = page([offer(1, { district_key: 'صفا', kind: 'شقة' })], [{ id: 2, district: null, guessed_district: null }]);

    assert.equal(component.candidates({ ...villa, kind: null }, project).length, 1);
});

test('lists a limited number of candidates at once', () => {
    const many = Array.from({ length: CANDIDATE_LIMIT + 10 }, (_, index) => offer(index + 1));
    const component = page(many, [{ id: 2, district: 'منار' }]);

    assert.equal(component.candidates(villa, project).length, CANDIDATE_LIMIT);
});

test('opens one picker at a time and starts it afresh', () => {
    const component = page();
    component.pickSearch = 'قديم';
    component.pickNote = 'قديمة';

    component.togglePicker(villa);
    assert.equal(component.pickingFor, 7);
    assert.equal(component.pickSearch, '');
    assert.equal(component.pickNote, '');

    component.togglePicker(villa);
    assert.equal(component.pickingFor, null);
});

test('says where our unit sits against a competitor', () => {
    const component = page();

    assert.deepEqual(component.competitorGap(12), { direction: 'above', label: 'أعلى ١٢٪' });
    assert.deepEqual(component.competitorGap(-20), { direction: 'below', label: 'أقل ٢٠٪' });
    assert.deepEqual(component.competitorGap(0), { direction: 'level', label: 'مساوٍ' });
    assert.deepEqual(component.competitorGap(null), { direction: null, label: '—' });
});

test('counts the units with competitors and the competitors chosen', () => {
    const component = page();
    assert.deepEqual(component.competitorCounts(), { units: 0, links: 0 });

    component.competitors = { projects: [{ units: [{ competitors: [{}, {}] }, { competitors: [] }] }] };
    assert.deepEqual(component.competitorCounts(), { units: 1, links: 2 });
});
