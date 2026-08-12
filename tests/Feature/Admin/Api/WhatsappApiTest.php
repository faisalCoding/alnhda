<?php

use App\Jobs\SendLeadWhatsappJob;
use App\Models\Admin;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageRecipient;
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

it('records the message with a row per recipient and queues one job each', function () {
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
        ->assertJsonPath('data.queued', 2);

    $message = WhatsappMessage::query()->sole();

    expect($message->body)->toBe('مرحباً {الاسم}، لدينا عرض جديد.')
        ->and($message->admin_id)->toBe($this->admin->id)
        ->and($message->recipients_count)->toBe(2);

    $recipients = $message->recipients()->orderBy('id')->get();

    expect($recipients)->toHaveCount(2)
        ->and($recipients[0]->name)->toBe('محمد')
        ->and($recipients[0]->phone)->toBe('0555555555')
        ->and($recipients[0]->lead_id)->toBe($first->id)
        ->and($recipients->pluck('status')->unique()->all())
        ->toBe([WhatsappMessageRecipient::STATUS_QUEUED]);

    Queue::assertPushed(SendLeadWhatsappJob::class, 2);
    Queue::assertPushed(fn (SendLeadWhatsappJob $job) => $job->recipientId === $recipients[0]->id
        && $job->clientId === 'admin_'.$this->admin->id);
});

it('keeps a record of the leads that were skipped for a bad phone', function () {
    Queue::fake();
    fakeGateway();

    Lead::factory()->create(['phone' => '0555555555']);
    Lead::factory()->create(['phone' => '—']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/whatsapp/send'), [
            'message' => 'مرحباً',
            'lead_ids' => Lead::query()->pluck('id')->all(),
        ])
        ->assertSuccessful();

    $message = WhatsappMessage::query()->sole();

    expect($message->recipients_count)->toBe(1)
        ->and($message->skipped_count)->toBe(1)
        ->and($message->recipients()->count())->toBe(1);
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

it('delivers a queued message and marks the recipient sent', function () {
    Http::fake(['*/send' => Http::response(['success' => true, 'message_id' => 'true_966@c.us_ABC'])]);
    config()->set('services.whatsapp.send_delay_min', 0);
    config()->set('services.whatsapp.send_delay_max', 0);

    $message = WhatsappMessage::factory()->create(['body' => 'مرحباً {الاسم}']);
    $recipient = WhatsappMessageRecipient::factory()->for($message, 'message')
        ->create(['name' => 'محمد', 'phone' => '0555555555']);

    (new SendLeadWhatsappJob('admin_1', $recipient->id))->handle(app(WhatsappGateway::class));

    // The placeholder is filled per recipient at send time, not when queued.
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/send')
        && $request['phone'] === '966555555555'
        && $request['message'] === 'مرحباً محمد');

    expect($recipient->fresh())
        ->status->toBe(WhatsappMessageRecipient::STATUS_SENT)
        ->provider_message_id->toBe('true_966@c.us_ABC')
        ->sent_at->not->toBeNull();
});

it('marks the recipient failed and keeps the reason when the gateway refuses', function () {
    Http::fake(['*/send' => Http::response(['success' => false, 'message' => 'الجلسة غير جاهزة.'], 503)]);
    config()->set('services.whatsapp.send_delay_min', 0);
    config()->set('services.whatsapp.send_delay_max', 0);

    $recipient = WhatsappMessageRecipient::factory()->create(['phone' => '0555555555']);

    (new SendLeadWhatsappJob('admin_1', $recipient->id))->handle(app(WhatsappGateway::class));

    expect($recipient->fresh())
        ->status->toBe(WhatsappMessageRecipient::STATUS_FAILED)
        ->error->toBe('الجلسة غير جاهزة.')
        ->sent_at->toBeNull();
});

it('does not send twice if the job runs again for an already sent recipient', function () {
    Http::fake(['*/send' => Http::response(['success' => true, 'message_id' => 'x'])]);
    config()->set('services.whatsapp.send_delay_min', 0);
    config()->set('services.whatsapp.send_delay_max', 0);

    $recipient = WhatsappMessageRecipient::factory()->sent()->create();

    (new SendLeadWhatsappJob('admin_1', $recipient->id))->handle(app(WhatsappGateway::class));

    Http::assertNothingSent();
});

it('marks a recipient failed when the job itself blows up', function () {
    $recipient = WhatsappMessageRecipient::factory()->create();

    (new SendLeadWhatsappJob('admin_1', $recipient->id))->failed(new RuntimeException('boom'));

    expect($recipient->fresh())
        ->status->toBe(WhatsappMessageRecipient::STATUS_FAILED)
        ->error->toBe('boom');
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

it('promotes a sent message to delivered and then read from acknowledgements', function (int $ack, string $expected) {
    $recipient = WhatsappMessageRecipient::factory()->sent()->create();

    Http::fake(['*/acks' => Http::response(['acks' => [$recipient->provider_message_id => $ack]])]);

    $this->artisan('whatsapp:sync-acks')->assertSuccessful();

    expect($recipient->fresh()->status)->toBe($expected);
})->with([
    'server received' => [1, WhatsappMessageRecipient::STATUS_SENT],
    'device delivered' => [2, WhatsappMessageRecipient::STATUS_DELIVERED],
    'read' => [3, WhatsappMessageRecipient::STATUS_READ],
    'played' => [4, WhatsappMessageRecipient::STATUS_READ],
    'error' => [-1, WhatsappMessageRecipient::STATUS_FAILED],
]);

it('stamps the delivery time when whatsapp confirms receipt', function () {
    $recipient = WhatsappMessageRecipient::factory()->sent()->create();

    Http::fake(['*/acks' => Http::response(['acks' => [$recipient->provider_message_id => 2]])]);

    $this->artisan('whatsapp:sync-acks')->assertSuccessful();

    expect($recipient->fresh()->delivered_at)->not->toBeNull();
});

it('never walks a delivery status backwards on a late acknowledgement', function () {
    $recipient = WhatsappMessageRecipient::factory()->sent()->create([
        'status' => WhatsappMessageRecipient::STATUS_READ,
    ]);

    // A stale ack for a message already read must not demote it.
    expect($recipient->applyAcknowledgement(1))->toBeFalse()
        ->and($recipient->fresh()->status)->toBe(WhatsappMessageRecipient::STATUS_READ);
});

it('does not report failure when the gateway simply has nothing new', function () {
    WhatsappMessageRecipient::factory()->sent()->create();
    Http::fake(['*/acks' => Http::response(['acks' => []])]);

    // This runs every five minutes; an empty result is normal, not an error.
    $this->artisan('whatsapp:sync-acks')
        ->expectsOutputToContain('حُدّثت حالة 0')
        ->assertSuccessful();
});

it('reports failure when the gateway is unreachable', function () {
    WhatsappMessageRecipient::factory()->sent()->create();
    Http::fake(fn () => throw new ConnectionException('refused'));

    $this->artisan('whatsapp:sync-acks')
        ->expectsOutputToContain('تعذر الوصول إلى البوابة')
        ->assertFailed();
});

it('leaves queued rows alone when syncing acknowledgements', function () {
    $queued = WhatsappMessageRecipient::factory()->create();
    Http::fake(['*/acks' => Http::response(['acks' => []])]);

    $this->artisan('whatsapp:sync-acks')->assertSuccessful();

    Http::assertNothingSent();
    expect($queued->fresh()->status)->toBe(WhatsappMessageRecipient::STATUS_QUEUED);
});

it('accepts acknowledgements pushed by the gateway with the shared key', function () {
    $first = WhatsappMessageRecipient::factory()->sent()->create();
    $second = WhatsappMessageRecipient::factory()->sent()->create();

    $this->withHeader('X-Api-Key', 'test-key')
        ->postJson(panelUrl('/api/whatsapp/ack'), [
            'acks' => [
                ['id' => $first->provider_message_id, 'ack' => 2],
                ['id' => $second->provider_message_id, 'ack' => 3],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.updated', 2);

    expect($first->fresh()->status)->toBe(WhatsappMessageRecipient::STATUS_DELIVERED)
        ->and($second->fresh()->status)->toBe(WhatsappMessageRecipient::STATUS_READ);
});

it('rejects acknowledgements without the shared key', function (?string $key) {
    $recipient = WhatsappMessageRecipient::factory()->sent()->create();

    $request = $key === null ? $this : $this->withHeader('X-Api-Key', $key);

    $request->postJson(panelUrl('/api/whatsapp/ack'), [
        'acks' => [['id' => $recipient->provider_message_id, 'ack' => 3]],
    ])->assertUnauthorized();

    // The status must be untouched by an unauthenticated caller.
    expect($recipient->fresh()->status)->toBe(WhatsappMessageRecipient::STATUS_SENT);
})->with([
    'no header' => [null],
    'wrong key' => ['not-the-key'],
    'empty key' => [''],
]);

it('rejects acknowledgements when no shared key is configured', function () {
    config()->set('services.whatsapp.key', '');

    $this->withHeader('X-Api-Key', '')
        ->postJson(panelUrl('/api/whatsapp/ack'), [
            'acks' => [['id' => 'x', 'ack' => 3]],
        ])
        ->assertUnauthorized();
});

it('exempts the gateway callback from csrf verification', function () {
    // Laravel skips CSRF while testing, so a feature test cannot catch this:
    // without the exemption every real callback answers 419 in production.
    // bootstrap/app.php feeds these into the middleware's static exclusion list.
    $neverVerify = (new ReflectionProperty(
        Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, 'neverVerify'
    ))->getValue();

    expect($neverVerify)->toContain('api/whatsapp/ack');
});

it('ignores acknowledgements for message ids it does not know', function () {
    $this->withHeader('X-Api-Key', 'test-key')
        ->postJson(panelUrl('/api/whatsapp/ack'), [
            'acks' => [['id' => 'unknown-id', 'ack' => 3]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.received', 1);
});

it('validates the acknowledgement payload', function (array $payload, string $field) {
    $this->withHeader('X-Api-Key', 'test-key')
        ->postJson(panelUrl('/api/whatsapp/ack'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no acks' => [[], 'acks'],
    'missing id' => [['acks' => [['ack' => 2]]], 'acks.0.id'],
    'missing level' => [['acks' => [['id' => 'x']]], 'acks.0.ack'],
    'non numeric level' => [['acks' => [['id' => 'x', 'ack' => 'yes']]], 'acks.0.ack'],
]);

it('lists message history with recipients and status counts', function () {
    $message = WhatsappMessage::factory()->create(['body' => 'مرحباً {الاسم}']);
    WhatsappMessageRecipient::factory()->for($message, 'message')->create(['name' => 'محمد']);
    WhatsappMessageRecipient::factory()->for($message, 'message')->sent()->create(['name' => 'سارة']);
    WhatsappMessageRecipient::factory()->for($message, 'message')->failed()->create(['name' => 'خالد']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/whatsapp/messages'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'مرحباً {الاسم}')
        ->assertJsonPath('data.0.counts.queued', 1)
        ->assertJsonPath('data.0.counts.sent', 1)
        ->assertJsonPath('data.0.counts.failed', 1)
        ->assertJsonCount(3, 'data.0.recipients')
        ->assertJsonPath('data.0.recipients.0.name', 'محمد');
});

it('rejects guests from the message history', function () {
    $this->getJson(panelUrl('/api/whatsapp/messages'))->assertUnauthorized();
});

it('renders the message history page', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(panelUrl('/whatsapp-messages'))
        ->assertSuccessful()
        ->assertSee('whatsappMessagesPage()', false)
        ->assertSee('سجل الرسائل');
});

it('redirects guests away from the message history page', function () {
    $this->get(panelUrl('/whatsapp-messages'))->assertRedirect();
});

it('schedules the acknowledgement sync', function () {
    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'whatsapp:sync-acks'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('*/5 * * * *');
});

it('keeps the recipient history after its lead is deleted', function () {
    $lead = Lead::factory()->create(['name' => 'محمد']);
    $recipient = WhatsappMessageRecipient::factory()->sent()->create([
        'lead_id' => $lead->id,
        'name' => 'محمد',
        'phone' => '0555555555',
    ]);

    $lead->delete();

    expect($recipient->fresh())
        ->not->toBeNull()
        ->lead_id->toBeNull()
        ->name->toBe('محمد')
        ->phone->toBe('0555555555');
});

it('keeps the send job timeout below the queue retry window', function () {
    $job = new SendLeadWhatsappJob('admin_1', '0555555555', 'مرحباً');
    $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 90);

    // A job that outlives retry_after is released while still running, and the
    // lead receives the same WhatsApp message twice.
    expect($job->timeout)->toBeLessThan($retryAfter);

    // It must still comfortably cover the human pause plus the gateway timeout.
    expect($job->timeout)->toBeGreaterThan(
        (int) config('services.whatsapp.send_delay_max') + 15
    );
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
