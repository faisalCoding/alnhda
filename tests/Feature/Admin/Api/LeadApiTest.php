<?php

use App\Models\Admin;
use App\Models\Lead;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

it('rejects guests from the leads api', function () {
    $this->getJson(panelUrl('/api/leads'))->assertUnauthorized();
    $this->postJson(panelUrl('/api/leads'), ['name' => 'x', 'phone' => '05'])->assertUnauthorized();
});

it('lists leads newest first', function () {
    $older = Lead::factory()->create(['name' => 'عميل قديم', 'created_at' => now()->subDay()]);
    $newer = Lead::factory()->create(['name' => 'عميل جديد']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/leads'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

it('creates a lead', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/leads'), [
            'name' => 'محمد العتيبي',
            'phone' => '0555555555',
            'property' => 'فيلا',
            'lead_date' => '2026-08-01',
            'classification' => 'مهتم',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'محمد العتيبي')
        ->assertJsonPath('data.lead_date', '2026-08-01')
        ->assertJsonPath('data.classification', 'مهتم');

    expect(Lead::query()->count())->toBe(1);
});

it('creates a lead with only the required fields', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/leads'), ['name' => 'سارة', 'phone' => '0500000000'])
        ->assertCreated()
        ->assertJsonPath('data.property', null)
        ->assertJsonPath('data.lead_date', null)
        ->assertJsonPath('data.classification', null);
});

it('rejects a lead without the required fields', function (array $payload, string $field) {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/leads'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['phone' => '0555555555'], 'name'],
    'missing phone' => [['name' => 'بدون رقم'], 'phone'],
    'invalid date' => [['name' => 'أحمد', 'phone' => '0555555555', 'lead_date' => 'not-a-date'], 'lead_date'],
]);

it('updates a lead', function () {
    $lead = Lead::factory()->create(['classification' => 'جديد']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/leads/'.$lead->id), [
            'name' => $lead->name,
            'phone' => $lead->phone,
            'classification' => 'تم البيع',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.classification', 'تم البيع');

    expect($lead->refresh()->classification)->toBe('تم البيع');
});

it('deletes a lead', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/leads/'.$lead->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true);

    expect(Lead::query()->count())->toBe(0);
});

it('does not duplicate a lead when the same idempotency key is replayed', function () {
    $headers = ['Idempotency-Key' => 'op-44444444-4444-4444-4444-444444444444'];
    $payload = ['name' => 'عميل من ملف CSV', 'phone' => '0512345678'];

    $first = $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/leads'), $payload, $headers);
    $second = $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/leads'), $payload, $headers);

    $first->assertCreated();
    $second->assertHeader('Idempotency-Replayed', 'true');

    expect(Lead::query()->count())->toBe(1);
});

it('stores every row of a csv import, duplicates included', function () {
    $rows = [
        ['name' => 'محمد', 'phone' => '0555555555'],
        ['name' => 'محمد', 'phone' => '0555555555'],
        ['name' => 'خالد', 'phone' => '0566666666'],
    ];

    foreach ($rows as $index => $row) {
        $this->actingAs($this->admin, 'admin')
            ->postJson(panelUrl('/api/leads'), $row, ['Idempotency-Key' => 'csv-row-'.$index])
            ->assertCreated();
    }

    expect(Lead::query()->count())->toBe(3)
        ->and(Lead::query()->where('phone', '0555555555')->count())->toBe(2);
});

it('counts leads in the dashboard stats', function () {
    Lead::factory()->count(4)->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/dashboard/stats'))
        ->assertSuccessful()
        ->assertJsonPath('data.counts.leads', 4)
        ->assertJsonCount(4, 'data.latest.leads');
});

it('renders the leads dashboard page for an authenticated admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(panelUrl('/leads-dashboard'))
        ->assertSuccessful()
        ->assertSee('leadsPage()', false)
        ->assertSee('العملاء المحتملون');
});

it('redirects guests away from the leads dashboard page', function () {
    $this->get(panelUrl('/leads-dashboard'))->assertRedirect();
});
