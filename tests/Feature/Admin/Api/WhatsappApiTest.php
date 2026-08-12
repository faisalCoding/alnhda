<?php

use App\Jobs\SendLeadWhatsappJob;
use App\Models\Admin;
use App\Models\Lead;
use App\Services\WhatsappGateway;
use App\Services\WhatsappServiceProcess;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    config()->set('services.whatsapp.url', 'http://127.0.0.1:3000');
    config()->set('services.whatsapp.key', 'test-key');
});

/**
 * @param  array<string, mixed>  $extra
 */
function fakeGateway(string $status = 'ready', array $extra = []): void
{
    Http::fake([
        '*/status/*' => Http::response(['status' => $status, 'message' => 'حالة'] + $extra),
        '*/send' => Http::response(['success' => true]),
        '*/disconnect/*' => Http::response(['success' => true]),
        '*/reset/*' => Http::response(['success' => true]),
    ]);
}

it('rejects guests from the whatsapp api', function () {
    $this->getJson(panelUrl('/api/whatsapp/status'))->assertUnauthorized();
    $this->postJson(panelUrl('/api/whatsapp/send'), [])->assertUnauthorized();
});

it('reports the session status and scopes the session to the admin', function () {
    fakeGateway('needs_scan', ['qr_image' => 'data:image/png;base64,AAA']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/whatsapp/status'))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'needs_scan')
        ->assertJsonPath('data.qr_image', 'data:image/png;base64,AAA')
        ->assertJsonPath('data.client_id', 'admin_'.$this->admin->id);
});

it('sends the shared api key with every gateway call', function () {
    fakeGateway();

    $this->actingAs($this->admin, 'admin')->getJson(panelUrl('/api/whatsapp/status'))->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'test-key'));
});

it('reports an error instead of failing when the node service is down', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/whatsapp/status'))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'error');
});

it('queues one job per lead and personalizes the name placeholder', function () {
    Queue::fake();
    fakeGateway();

    $first = Lead::factory()->create(['name' => 'محمد', 'phone' => '0555555555']);
    $second = Lead::factory()->create(['name' => 'سارة', 'phone' => '0566666666']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/send'), [
            'message' => 'مرحباً {الاسم}، لدينا عرض جديد.',
            'lead_ids' => [$first->id, $second->id],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.queued', 2)
        ->assertJsonPath('data.skipped', 0);

    Queue::assertPushed(SendLeadWhatsappJob::class, 2);
    Queue::assertPushed(fn (SendLeadWhatsappJob $job) => $job->message === 'مرحباً محمد، لدينا عرض جديد.'
        && $job->clientId === 'admin_'.$this->admin->id);
    Queue::assertPushed(fn (SendLeadWhatsappJob $job) => $job->message === 'مرحباً سارة، لدينا عرض جديد.');
});

it('skips leads whose phone has no digits', function () {
    Queue::fake();
    fakeGateway();

    $valid = Lead::factory()->create(['phone' => '0555555555']);
    $invalid = Lead::factory()->create(['phone' => '—']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/send'), [
            'message' => 'مرحباً',
            'lead_ids' => [$valid->id, $invalid->id],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.queued', 1)
        ->assertJsonPath('data.skipped', 1);

    Queue::assertPushed(SendLeadWhatsappJob::class, 1);
});

it('refuses to send while the session is not linked', function () {
    Queue::fake();
    fakeGateway('needs_scan');

    $lead = Lead::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/send'), ['message' => 'مرحباً', 'lead_ids' => [$lead->id]])
        ->assertConflict();

    Queue::assertNothingPushed();
});

it('validates the send payload', function (array $payload, string $field) {
    fakeGateway();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/send'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no message' => [['lead_ids' => [1]], 'message'],
    'no leads' => [['message' => 'مرحباً'], 'lead_ids'],
    'unknown lead' => [['message' => 'مرحباً', 'lead_ids' => [999999]], 'lead_ids.0'],
]);

it('normalizes saudi phone numbers to international digits', function (string $input, string $expected) {
    expect(app(WhatsappGateway::class)->normalizePhone($input))->toBe($expected);
})->with([
    ['0555555555', '966555555555'],
    ['555555555', '966555555555'],
    ['+966 55 555 5555', '966555555555'],
    ['00966555555555', '966555555555'],
    ['966555555555', '966555555555'],
    ['—', ''],
]);

it('delivers a queued message through the gateway', function () {
    fakeGateway();
    config()->set('services.whatsapp.send_delay_min', 0);
    config()->set('services.whatsapp.send_delay_max', 0);

    (new SendLeadWhatsappJob('admin_1', '0555555555', 'مرحباً'))->handle(app(WhatsappGateway::class));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/send')
        && $request['phone'] === '966555555555'
        && $request['clientId'] === 'admin_1'
        && $request['message'] === 'مرحباً');
});

it('starts the node service when nothing is listening', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('start')->once()->andReturn('started');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/start'))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'starting');
});

it('reports that the node service is already running', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('start')->once()->andReturn('already_running');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/start'))
        ->assertSuccessful()
        ->assertJsonPath('data.message', 'الخدمة تعمل مسبقًا.');
});

it('explains when the host forbids starting processes from the browser', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('start')->once()->andReturn('unavailable');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/start'))
        ->assertConflict();
});

it('rejects guests from starting the node service', function () {
    $this->postJson(panelUrl('/api/whatsapp/start'))->assertUnauthorized();
});

it('reads the service port from the configured url', function () {
    config()->set('services.whatsapp.url', 'http://127.0.0.1:3999');
    expect(app(WhatsappServiceProcess::class)->port())->toBe(3999);

    config()->set('services.whatsapp.url', 'http://127.0.0.1');
    expect(app(WhatsappServiceProcess::class)->port())->toBe(3000);
});

it('checks service health without creating a session', function () {
    Http::fake([
        '*/health' => Http::response(['uptime_seconds' => 12, 'active_sessions' => [], 'saved_sessions' => ['admin_1']]),
    ]);

    $health = app(WhatsappGateway::class)->health();

    expect($health['ok'])->toBeTrue()
        ->and($health['saved_sessions'])->toBe(['admin_1']);

    // A status probe would boot a browser and persist credentials; health must not.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/status/'));
});

it('reports unhealthy when the gateway cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));

    expect(app(WhatsappGateway::class)->health()['ok'])->toBeFalse();
});

it('returns the tail of the service log', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('logPath')->andReturn('/tmp/node.log');
        $mock->shouldReceive('tailLog')->once()->andReturn(['خدمة الواتساب تعمل', '[admin_1] QR Code جديد.']);
        $mock->shouldReceive('isRunning')->andReturn(true);
    });

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/whatsapp/log'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.1', '[admin_1] QR Code جديد.')
        ->assertJsonPath('data.running', true);
});

it('rejects guests from reading the service log', function () {
    $this->getJson(panelUrl('/api/whatsapp/log'))->assertUnauthorized();
});

it('returns no log lines when the file is missing', function () {
    $process = app(WhatsappServiceProcess::class);

    expect($process->tailLog())->toBe([]);
})->skip(fn () => is_file(base_path('whatsapp-service/node.log')), 'a real log exists locally');

it('reads only the last lines of the log', function () {
    $path = base_path('whatsapp-service/node.log');
    $existing = is_file($path) ? file_get_contents($path) : null;

    file_put_contents($path, implode("\n", array_map(fn ($i) => "line {$i}", range(1, 500))));

    try {
        $lines = app(WhatsappServiceProcess::class)->tailLog(10);

        expect($lines)->toHaveCount(10)
            ->and($lines[0])->toBe('line 491')
            ->and(end($lines))->toBe('line 500');
    } finally {
        $existing === null ? @unlink($path) : file_put_contents($path, $existing);
    }
});

it('renders the log panel on the whatsapp page', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(panelUrl('/whatsapp-dashboard'))
        ->assertSuccessful()
        ->assertSee('سجل الخدمة')
        ->assertSee('toggleLog()', false);
});

it('renders the whatsapp dashboard page for an authenticated admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(panelUrl('/whatsapp-dashboard'))
        ->assertSuccessful()
        ->assertSee('whatsappPage()', false)
        ->assertSee('ربط الواتساب');
});

it('redirects guests away from the whatsapp dashboard page', function () {
    $this->get(panelUrl('/whatsapp-dashboard'))->assertRedirect();
});
