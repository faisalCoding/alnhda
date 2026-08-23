<?php

use App\Services\WhatsappGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.whatsapp.url', 'http://127.0.0.1:3000');
    config()->set('services.whatsapp.key', 'test-key');
});

/**
 * @param  array<string, mixed>  $report
 * @param  list<array{client_id: string, status: string}>  $sessions
 */
function fakeStorageProbe(array $report = [], array $sessions = [['client_id' => 'admin_1', 'status' => 'ready']]): void
{
    Http::fake([
        '*/health' => Http::response(['ok' => true, 'contract' => 2, 'active_sessions' => $sessions]),
        '*/storage/*' => Http::response(['success' => true] + $report + [
            'databases' => ['model-storage'],
            'stores' => ['chat', 'message'],
            'meta' => ['keyPath' => 'id', 'autoIncrement' => false, 'indexes' => ['chat']],
            'count' => 812,
            'scanned' => 400,
            'notes' => [],
            'rows' => [[
                'key' => 'true_966500000000@c.us_ABC',
                'fields' => ['id' => 'true_966500000000@c.us_ABC', 'ack' => 3, 't' => 1756000000, 'body' => '<نص، 42 محرفًا>'],
            ]],
        ]),
    ]);
}

it('asks the gateway for the store, and passes the probe limits along', function () {
    fakeStorageProbe();

    $result = app(WhatsappGateway::class)->storage('admin_1', ['store' => 'message', 'limit' => 5, 'scan' => 800, 'id' => 'ABC']);

    expect($result['ok'])->toBeTrue()
        ->and($result['report']['stores'])->toContain('message')
        ->and($result['report']['rows'][0]['fields']['ack'])->toBe(3);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/storage/admin_1')
            && str_contains($request->url(), 'store=message')
            && str_contains($request->url(), 'limit=5')
            && str_contains($request->url(), 'scan=800')
            && str_contains($request->url(), 'id=ABC');
    });
});

it('leaves out probe options that were not given', function () {
    fakeStorageProbe();

    app(WhatsappGateway::class)->storage('admin_1', ['store' => 'message', 'id' => '']);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'id=') && ! str_contains($request->url(), 'limit='));
});

it('passes the service reason through when the probe fails', function () {
    Http::fake(['*/storage/*' => Http::response(['success' => false, 'message' => 'الجلسة [admin_1] غير جاهزة بعد.'], 503)]);

    $result = app(WhatsappGateway::class)->storage('admin_1');

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toBe('الجلسة [admin_1] غير جاهزة بعد.');
});

it('probes the only linked session without being told which', function () {
    fakeStorageProbe();

    $this->artisan('whatsapp:storage')
        ->expectsOutputToContain('admin_1')
        ->expectsOutputToContain('message')
        ->expectsOutputToContain('812')
        ->assertSuccessful();
});

it('keeps message content out of what it prints', function () {
    fakeStorageProbe();

    $this->artisan('whatsapp:storage')
        ->doesntExpectOutputToContain('السلام عليكم')
        ->assertSuccessful();
});

it('asks which session when more than one is linked', function () {
    fakeStorageProbe(sessions: [
        ['client_id' => 'admin_1', 'status' => 'ready'],
        ['client_id' => 'admin_2', 'status' => 'ready'],
    ]);

    $this->artisan('whatsapp:storage')
        ->expectsOutputToContain('--client')
        ->assertFailed();
});

it('says the device is not linked rather than probing a session that cannot answer', function () {
    fakeStorageProbe(sessions: [['client_id' => 'admin_1', 'status' => 'needs_scan']]);

    $this->artisan('whatsapp:storage')
        ->expectsOutputToContain('لا توجد جلسة مرتبطة')
        ->assertFailed();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/storage/'));
});

it('reports the notes the probe came back with', function () {
    fakeStorageProbe(['notes' => ['المخزن "message" غير موجود في هذه القاعدة.'], 'rows' => []]);

    $this->artisan('whatsapp:storage')
        ->expectsOutputToContain('غير موجود في هذه القاعدة')
        ->assertSuccessful();
});

it('names a stale service build rather than blaming the session', function () {
    Http::fake([
        '*/health' => Http::response(['ok' => true, 'active_sessions' => [['client_id' => 'admin_1', 'status' => 'ready']]]),
        '*/storage/*' => Http::response('Cannot GET /storage/admin_1', 404),
    ]);

    $this->artisan('whatsapp:storage')
        ->expectsOutputToContain('whatsapp:restart')
        ->assertFailed();
});
