import test from 'node:test';
import assert from 'node:assert';

import { USUAL_KINDS, composeLocation, kindChoices, labelOf, placeIn } from './marketPlaces.js';

test('writes a location from a city and a district', () => {
    assert.equal(composeLocation('جدة', 'الصفا'), 'جدة حي الصفا');
    assert.equal(composeLocation('جدة', 'حي الأمير فواز'), 'جدة حي الأمير فواز', 'a district already called a district is not called one twice');
    assert.equal(composeLocation('جدة', ''), 'جدة');
    assert.equal(composeLocation('', 'الصفا'), '', 'a district belongs to no city on its own');
    assert.equal(composeLocation(undefined, undefined), '');
});

test('finds the label of a choice by its key', () => {
    const cities = [{ key: 'جده', label: 'جدة' }, { key: 'الرياض', label: 'الرياض' }];

    assert.equal(labelOf(cities, 'جده'), 'جدة');
    assert.equal(labelOf(cities, 'نيوم'), '');
    assert.equal(labelOf(undefined, 'جده'), '');
});

test('offers the market kinds, and the usual ones before the market has been asked', () => {
    assert.deepEqual(kindChoices(['أرض', 'شقة'], 'شقة'), ['أرض', 'شقة']);
    assert.deepEqual(kindChoices([], 'شقة'), USUAL_KINDS);
    assert.deepEqual(kindChoices(undefined, ''), USUAL_KINDS);
});

test('keeps the kind a unit already is on the list', () => {
    assert.deepEqual(kindChoices(['شقة', 'فيلا'], 'ملحق'), ['شقة', 'فيلا', 'ملحق']);

    const kinds = ['شقة'];
    kindChoices(kinds, 'ملحق');
    assert.deepEqual(kinds, ['شقة'], 'the list that was passed in is not changed');
});

test('reads the city and the district a typed location names', () => {
    const cities = [{ key: 'جده', label: 'جدة' }, { key: 'الرياض', label: 'الرياض' }];
    const districts = [{ key: 'المنار', label: 'المنار' }, { key: 'الاجواد', label: 'الاجواد' }];

    assert.deepEqual(placeIn('جدة حي المنار', cities, districts), { city: 'جده', district: 'المنار' });
    assert.deepEqual(placeIn('\u200Fجده  حي  الأجواد', cities, districts), { city: 'جده', district: 'الاجواد' }, 'spelt another way, with a stray mark');
    assert.deepEqual(placeIn('جدة حي المنار', cities), { city: 'جده', district: '' }, 'the districts are not known yet');
    assert.deepEqual(placeIn('جدة مخطط التيسير', cities, districts), { city: 'جده', district: '' });
    assert.deepEqual(placeIn('مكة حي العزيزية', cities, districts), { city: '', district: '' });
    assert.deepEqual(placeIn(null, cities, districts), { city: '', district: '' });
});
