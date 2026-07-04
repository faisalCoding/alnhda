<?php

use App\Models\Admin;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('hides admin registration when disabled', function () {
    config(['app.admin_registration_enabled' => false]);

    $this->get(panelUrl('/admin/register'))->assertNotFound();

    $this->post(panelUrl('/admin/register'), [
        'name' => 'مدير جديد',
        'email' => 'new-admin@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ])->assertNotFound();

    expect(Admin::query()->count())->toBe(0);
});

it('registers a new admin when enabled', function () {
    config(['app.admin_registration_enabled' => true]);

    $this->get(panelUrl('/admin/register'))->assertSuccessful();

    $response = $this->post(panelUrl('/admin/register'), [
        'name' => 'مدير جديد',
        'email' => 'new-admin@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated('admin');
    expect(Admin::query()->count())->toBe(1);
});

it('rejects a duplicate admin email', function () {
    config(['app.admin_registration_enabled' => true]);
    $existing = Admin::factory()->create();

    $response = $this->from(panelUrl('/admin/register'))->post(panelUrl('/admin/register'), [
        'name' => 'مدير مكرر',
        'email' => $existing->email,
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $response->assertInvalid(['email']);
    expect(Admin::query()->count())->toBe(1);
});
