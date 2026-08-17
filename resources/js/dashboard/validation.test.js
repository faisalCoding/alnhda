import test from 'node:test';
import assert from 'node:assert';

import { isAbsoluteUrl } from './validation.js';

/**
 * The cases that matter are the ones a user actually types into the map field
 * offline. Each rejected value here would otherwise be accepted locally and
 * wedge the outbox with a 422 once the connection came back.
 */
test('accepts absolute http and https links', () => {
    for (const value of [
        'https://maps.google.com/example',
        'http://maps.google.com/example',
        'https://maps.app.goo.gl/AbC123',
        'https://www.google.com/maps/place/%D8%AC%D8%AF%D8%A9',
    ]) {
        assert.equal(isAbsoluteUrl(value), true, value);
    }
});

test('rejects a bare domain pasted without a scheme', () => {
    assert.equal(isAbsoluteUrl('maps.google.com/example'), false);
    assert.equal(isAbsoluteUrl('www.google.com/maps'), false);
});

test('rejects free text typed in place of a link', () => {
    assert.equal(isAbsoluteUrl('جدة حي المنار'), false);
    assert.equal(isAbsoluteUrl('لاحقًا'), false);
});

test('rejects empty and whitespace-only values', () => {
    assert.equal(isAbsoluteUrl(''), false);
    assert.equal(isAbsoluteUrl('   '), false);
});

test('rejects schemes that are not web links', () => {
    assert.equal(isAbsoluteUrl('ftp://example.com'), false);
    assert.equal(isAbsoluteUrl('javascript:alert(1)'), false);
});

test('rejects values that are not strings', () => {
    assert.equal(isAbsoluteUrl(null), false);
    assert.equal(isAbsoluteUrl(undefined), false);
});
