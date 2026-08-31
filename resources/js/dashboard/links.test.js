import test from 'node:test';
import assert from 'node:assert';

import { ITEM_KINDS, composeValue, labelForValue, linkOptions, moveItem, parseCtaValue, targetName } from './links.js';

const data = () => ({
    projects: [{ id: 1, name: 'مشروع النهضة' }],
    properties: [{ id: 4, name: 'شقة ٣' }],
    articles: [
        { id: 7, title: 'مقال أول' },
        { id: 8, title: 'مقال ثانٍ' },
    ],
    collections: [{ id: 2, title: 'شقق جاهزة' }],
});

test('composes a picker value from a saved link', () => {
    assert.equal(composeValue('project', 12), 'project:12');
    assert.equal(composeValue('properties', 5), 'properties:5');
});

test('composes an empty value when there is no link', () => {
    assert.equal(composeValue(null, null), '');
    assert.equal(composeValue('project', null), '');
    assert.equal(composeValue(null, 12), '');
});

test('parses a picker value into what the server expects', () => {
    assert.deepEqual(parseCtaValue('project:12'), { cta_target_type: 'project', cta_target_id: 12 });
    assert.deepEqual(parseCtaValue('collection:2'), { cta_target_type: 'collection', cta_target_id: 2 });
});

test('parses a cleared picker as no link, so saving removes the old one', () => {
    for (const value of ['', null, undefined, 'project:', ':12']) {
        assert.deepEqual(parseCtaValue(value), { cta_target_type: null, cta_target_id: null });
    }
});

test('parses an unknown type as no link', () => {
    assert.deepEqual(parseCtaValue('user:1'), { cta_target_type: null, cta_target_id: null });
});

test('offers every kind of destination, grouped', () => {
    const groups = linkOptions(data());

    assert.deepEqual(
        groups.map((group) => group.type),
        ['project', 'properties', 'article', 'collection']
    );
    assert.deepEqual(groups[0].options, [{ value: 'project:1', label: 'مشروع النهضة' }]);
    assert.deepEqual(groups[3].options, [{ value: 'collection:2', label: 'شقق جاهزة' }]);
});

test('offers a collection page only the kinds it may gather', () => {
    const groups = linkOptions(data(), { kinds: ITEM_KINDS });

    assert.deepEqual(
        groups.map((group) => group.type),
        ['project', 'properties', 'article']
    );
});

test('never offers the article being edited as its own destination', () => {
    const groups = linkOptions(data(), { excludeArticleId: 7 });
    const articles = groups.find((group) => group.type === 'article');

    assert.deepEqual(articles.options, [{ value: 'article:8', label: 'مقال ثانٍ' }]);
});

test('stops offering what a page already holds', () => {
    const groups = linkOptions(data(), { kinds: ITEM_KINDS, exclude: ['project:1', 'article:7'] });

    assert.deepEqual(
        groups.map((group) => group.type),
        ['properties', 'article']
    );
    assert.deepEqual(groups[1].options, [{ value: 'article:8', label: 'مقال ثانٍ' }]);
});

test('leaves out records still waiting to sync, whose id the server has never seen', () => {
    const unsynced = data();
    unsynced.projects.push({ id: 'tmp_9f0c', name: 'مشروع أُنشئ دون اتصال' });

    const projects = linkOptions(unsynced).find((group) => group.type === 'project');

    assert.deepEqual(projects.options, [{ value: 'project:1', label: 'مشروع النهضة' }]);
});

test('drops a group with nothing to offer instead of showing it empty', () => {
    const groups = linkOptions({ projects: [], properties: [], articles: data().articles, collections: [] }, { excludeArticleId: 8 });

    assert.deepEqual(
        groups.map((group) => group.type),
        ['article']
    );
});

test('reads a link destination out of the records already held', () => {
    assert.equal(targetName(data(), 'project', 1), 'مشروع النهضة');
    assert.equal(targetName(data(), 'properties', 4), 'شقة ٣');
    assert.equal(targetName(data(), 'article', 7), 'مقال أول');
    assert.equal(targetName(data(), 'collection', 2), 'شقق جاهزة');
});

test('reads no destination for a record the panel does not hold', () => {
    assert.equal(targetName(data(), 'project', 404), null);
    assert.equal(targetName(data(), 'employee', 1), null);
    assert.equal(targetName(data(), null, null), null);
});

test('names a chosen item, falling back to its raw value', () => {
    assert.equal(labelForValue(data(), 'properties:4'), 'شقة ٣');
    assert.equal(labelForValue(data(), 'project:404'), 'project:404');
});

test('moves an item up and down the arrangement', () => {
    const list = ['a', 'b', 'c'];

    assert.deepEqual(moveItem(list, 2, -1), ['a', 'c', 'b']);
    assert.deepEqual(moveItem(list, 0, 1), ['b', 'a', 'c']);
    assert.deepEqual(list, ['a', 'b', 'c'], 'the original list is left alone');
});

test('refuses to move an item off either end', () => {
    const list = ['a', 'b', 'c'];

    assert.deepEqual(moveItem(list, 0, -1), list);
    assert.deepEqual(moveItem(list, 2, 1), list);
    assert.deepEqual(moveItem(list, 9, -1), list);
});
