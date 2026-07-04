<?php

use App\Models\Admin;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('logs the admin out and ends the session', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post(panelUrl('/admin/logout'));

    $response->assertRedirect();
    $this->assertGuest('admin');
});
