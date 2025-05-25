<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('admin')->middleware('guest:admin')->group(function () {
    Volt::route('login', 'admin_auth.login')
        ->name('login');

    Volt::route('register', 'admin_auth.register')
        ->name('admin.register');


    Route::view('dashboard', 'dashboard')
        ->middleware(['auth:admin', 'verified'])
        ->name('dashboard');
});

Route::post('admin/logout', App\Livewire\Actions\Logout::class)
    ->name('admin.logout');
