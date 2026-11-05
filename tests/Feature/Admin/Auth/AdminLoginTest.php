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

    $response->assertInvalid(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
    $this->assertGuest('admin');
});

it('shows the login validation errors in Arabic', function (array $input, string $field, string $message) {
    $this->from(panelUrl('/admin/login'))
        ->post(panelUrl('/admin/login'), $input)
        ->assertInvalid([$field => $message]);
})->with([
    'missing email' => [['email' => '', 'password' => 'password'], 'email', 'أدخل البريد الإلكتروني.'],
    'malformed email' => [['email' => 'not-an-email', 'password' => 'password'], 'email', 'البريد الإلكتروني غير صالح.'],
    'missing password' => [['email' => 'admin@example.com', 'password' => ''], 'password', 'أدخل كلمة المرور.'],
]);

it('locks the login out after five failed attempts', function () {
    $admin = Admin::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(panelUrl('/admin/login'), [
            'email' => $admin->email,
            'password' => 'wrong-password-'.$attempt,
        ]);
    }

    $response = $this->from(panelUrl('/admin/login'))->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertInvalid(['email' => 'تجاوزت عدد محاولات الدخول المسموح بها']);
    $this->assertGuest('admin');
});

it('tells a locked-out admin how long to wait in Arabic', function (int $elapsed, string $wait) {
    $this->freezeTime();
    $admin = Admin::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(panelUrl('/admin/login'), [
            'email' => $admin->email,
            'password' => 'wrong-password-'.$attempt,
        ]);
    }

    $this->travel($elapsed)->seconds();

    $this->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertInvalid(['email' => 'حاول مرة أخرى بعد '.$wait.'.']);
})->with([
    'a full minute' => [0, '60 ثانية'],
    'ten seconds' => [50, '10 ثوانٍ'],
    'two seconds' => [58, 'ثانيتين'],
    'one second' => [59, 'ثانية واحدة'],
]);

it('counts a wrong password sent again by a second click only once', function () {
    $admin = Admin::factory()->create();

    foreach (range(1, 5) as $click) {
        $this->post(panelUrl('/admin/login'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertInvalid(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
    }

    $this->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated('admin');
});

it('still locks out a guesser who mixes repeated and new passwords', function () {
    $admin = Admin::factory()->create();

    foreach (['guess-1', 'guess-1', 'guess-2', 'guess-3', 'guess-3', 'guess-4', 'guess-5'] as $password) {
        $this->post(panelUrl('/admin/login'), [
            'email' => $admin->email,
            'password' => $password,
        ]);
    }

    $this->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertInvalid(['email' => 'تجاوزت عدد محاولات الدخول المسموح بها']);

    $this->assertGuest('admin');
});

it('logs in and loads the dashboard and its first requests without a rate limit', function () {
    $admin = Admin::factory()->create();

    $this->post(panelUrl('/admin/login'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->withoutVite()->get(panelUrl('/dashboard'))->assertOk();

    $this->getJson(panelUrl('/api/session'))
        ->assertOk()
        ->assertJsonPath('authenticated', true);

    $this->getJson(panelUrl('/api/dashboard/stats'))->assertOk();
});

it('guards the login button against a second submit while the first is pending', function () {
    $this->get(panelUrl('/admin/login'))
        ->assertSuccessful()
        ->assertSee('data-login-form', false)
        ->assertSee('data-login-submit', false)
        ->assertSee('جارٍ تسجيل الدخول…');
});

it('redirects guests hitting a dashboard page to the login page', function () {
    $this->get(panelUrl('/dashboard'))->assertRedirect();
});
