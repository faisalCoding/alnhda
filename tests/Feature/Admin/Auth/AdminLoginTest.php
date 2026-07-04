<?php

use App\Models\Admin;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders the admin login page', function () {
    $this->get(panelUrl('/admin/login'))
        ->assertSuccessful()
        ->assertSee('تسجيل الدخول');
});

it('logs an admin in with valid credentials', function () {
    $admin = Admin::factory()->create();

    $response = $this->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated('admin');
});

it('rejects invalid credentials', function () {
    $admin = Admin::factory()->create();

    $response = $this->from(panelUrl('/admin/login'))->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $response->assertInvalid(['email']);
    $this->assertGuest('admin');
});

it('locks the login out after five failed attempts', function () {
    $admin = Admin::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(panelUrl('/admin/login'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->from(panelUrl('/admin/login'))->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertInvalid(['email']);
    $this->assertGuest('admin');
});

it('redirects guests hitting a dashboard page to the login page', function () {
    $this->get(panelUrl('/dashboard'))->assertRedirect();
});
