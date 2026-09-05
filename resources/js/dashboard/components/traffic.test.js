import test from 'node:test';
import assert from 'node:assert';

import trafficPage from './traffic.js';

test('formats nothing while the screen is still loading', () => {
    const page = trafficPage();

    // What the header reads on every first paint, before the request returns.
    assert.equal(page.data, null);
    assert.equal(page.lastUpdated, '');
});

test('formats nothing when analytics has never been pulled', () => {
    const page = trafficPage();
    page.data = { google: { pulled_at: null } };

    assert.equal(page.lastUpdated, '');
});

test('formats the moment analytics was last pulled', () => {
    const page = trafficPage();
    page.data = { google: { pulled_at: '2026-09-04T03:40:00.000Z' } };

    assert.ok(page.lastUpdated.length > 0);
    assert.ok(!page.lastUpdated.includes('1970'), 'a missing date must not fall back to the epoch');
});

test('reads no traffic out of an empty screen', () => {
    const page = trafficPage();

    assert.deepEqual(page.timeline, []);
    assert.equal(page.peak, 1, 'an empty chart still needs a divisor');
});

test('scales the bars against the tallest day', () => {
    const page = trafficPage();
    page.data = {
        days: [
            { date: '2026-09-01', users: 50, requests: 100 },
            { date: '2026-09-02', users: 25, requests: 60 },
            { date: '2026-09-03', users: 0, requests: 0 },
        ],
    };

    assert.equal(page.peak, 100);
    assert.equal(page.barHeight(100), '100%');
    assert.equal(page.barHeight(50), '50%');
    assert.equal(page.barHeight(0), '0%');
});

test('gives a day with almost no traffic a bar you can still see', () => {
    const page = trafficPage();
    page.data = { days: [{ users: 1000, requests: 1000 }, { users: 1, requests: 1 }] };

    assert.equal(page.barHeight(1), '2%');
});

test('reports growth from nothing as new rather than as an infinity', () => {
    const page = trafficPage();

    assert.deepEqual(page.change(40, 0), { direction: 'new', label: 'جديد' });
    assert.equal(page.change(0, 0), null);
});

test('reports the change against the stretch before it', () => {
    const page = trafficPage();

    assert.equal(page.change(150, 100).direction, 'up');
    assert.equal(page.change(50, 100).direction, 'down');
    assert.equal(page.change(100, 100).direction, 'flat');
});

test('reads bytes as a size a person understands', () => {
    const page = trafficPage();

    assert.equal(page.bytes(0), '0 بايت');
    assert.equal(page.bytes(2048), '2.0 كيلوبايت');
    assert.equal(page.bytes(1048576), '1.0 ميجابايت');
    assert.equal(page.bytes(3221225472), '3.0 جيجابايت');
});

test('scales a ranked row against the leader of its own list', () => {
    const page = trafficPage();
    const list = [{ value: 80 }, { value: 40 }, { value: 0 }];

    assert.equal(page.share(list[0], list), '100%');
    assert.equal(page.share(list[1], list), '50%');
    assert.equal(page.share(list[2], list), '0%');
});

test('says nothing about a running day it has no timestamp for', () => {
    const page = trafficPage();
    page.data = { today: { has_data: false, updated_at: null } };

    assert.equal(page.todayUpdated, '');
});

test('reads the refresh time as a phrase, not a clock', () => {
    const page = trafficPage();
    const minutesAgo = (n) => new Date(Date.now() - n * 60000).toISOString();

    page.data = { today: { updated_at: minutesAgo(0) } };
    assert.equal(page.todayUpdated, 'محدّث الآن');

    page.data = { today: { updated_at: minutesAgo(7) } };
    assert.ok(page.todayUpdated.includes('دقيقة'));

    page.data = { today: { updated_at: minutesAgo(150) } };
    assert.ok(page.todayUpdated.includes('ساعة'), 'past an hour it should not count in minutes');
});
