<?php

use App\Jobs\SendLeadWhatsappJob;
use App\Models\Admin;
use App\Models\Lead;
use App\Services\WhatsappGateway;
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
