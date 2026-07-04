<?php

use App\Models\Admin;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('reports an unauthenticated session with a csrf token', function () {
    $this->getJson(panelUrl('/api/session'))
        ->assertSuccessful()
        ->assertJsonPath('authenticated', false)
        ->assertJsonPath('admin', null)
        ->assertJson(fn ($json) => $json->whereType('csrf', 'string')->etc());
});

it('reports the authenticated admin', function () {
    $admin = Admin::factory()->create(['name' => 'مدير النظام']);

    $this->actingAs($admin, 'admin')
        ->getJson(panelUrl('/api/session'))
        ->assertSuccessful()
        ->assertJsonPath('authenticated', true)
        ->assertJsonPath('admin.name', 'مدير النظام')
        ->assertJsonPath('admin.email', $admin->email);
});
