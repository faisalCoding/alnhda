<?php

use App\Models\Admin;
use App\Models\Project;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->payload = [
        'name' => 'مشروع الاختبار',
        'description' => 'وصف تفصيلي لمشروع الاختبار',
        'status' => 'جديد',
        'project_type' => 'فيلا',
    ];
});

it('creates separate rows when no idempotency key is sent', function () {
    $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/projects'), $this->payload)->assertCreated();
    $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/projects'), $this->payload)->assertCreated();

    expect(Project::query()->count())->toBe(2);
});

it('does not cache failed responses', function () {
    $headers = ['Idempotency-Key' => 'op-44444444-4444-4444-4444-444444444444'];
    $invalid = ['description' => 'بدون اسم'];

    $first = $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/projects'), $invalid, $headers);
    $first->assertUnprocessable();

    $second = $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/projects'), array_merge($this->payload), $headers);
    $second->assertCreated();
    $second->assertHeaderMissing('Idempotency-Replayed');

    expect(Project::query()->count())->toBe(1);
});

it('scopes idempotency keys per admin', function () {
    $otherAdmin = Admin::factory()->create();
    $headers = ['Idempotency-Key' => 'op-55555555-5555-5555-5555-555555555555'];

    $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/projects'), $this->payload, $headers)->assertCreated();

    $response = $this->actingAs($otherAdmin, 'admin')->postJson(panelUrl('/api/projects'), $this->payload, $headers);
    $response->assertCreated();
    $response->assertHeaderMissing('Idempotency-Replayed');

    expect(Project::query()->count())->toBe(2);
});
