<?php

use App\Models\Admin;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the admin login page', function () {
    $this->get(panelUrl('/dashboard'))->assertRedirect(route('admin.login'));
});

test('an authenticated admin can visit every dashboard page', function (string $path) {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(panelUrl($path))
        ->assertSuccessful();
})->with([
    'overview' => '/dashboard',
    'projects' => '/projects-dashboard',
    'articles' => '/articles-dashboard',
    'visitors' => '/visitors-dashboard',
]);
