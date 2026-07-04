<?php

use App\Models\Admin;
use App\Models\Visitor;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('rejects guests from the visitors api', function () {
    $this->getJson(panelUrl('/api/visitors'))->assertUnauthorized();
});

it('lists visitors newest first', function () {
    $admin = Admin::factory()->create();
    $older = Visitor::factory()->create(['created_at' => now()->subDay()]);
    $newer = Visitor::factory()->create(['created_at' => now()]);

    $this->actingAs($admin, 'admin')
        ->getJson(panelUrl('/api/visitors'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});
