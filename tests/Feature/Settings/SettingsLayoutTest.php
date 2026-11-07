<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The settings pages are full-page single-file components with no layout of their
 * own, so they render into whatever Livewire's config names. Livewire 4's own
 * default (layouts::app) is an empty file in this project.
 */
test('settings pages render inside the app layout', function (string $path) {
    $this->actingAs(User::factory()->create());

    $this->get($path)
        ->assertOk()
        ->assertSee('<body', false)
        ->assertSee(__('Platform'))
        ->assertSee(route('dashboard'), false);
})->with([
    'profile' => '/settings/profile',
    'password' => '/settings/password',
    'appearance' => '/settings/appearance',
]);
