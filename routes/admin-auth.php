<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('admin')->middleware('guest:admin')->group(function () {
    Volt::route('login', 'admin_auth.login')
        ->name('login');

    Volt::route('register', 'admin_auth.register')
        ->name('admin.register');

});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth:admin'])
    ->name('dashboard');

Route::view('articles-dashboard', 'articles-dashboard')
    ->middleware(['auth:admin'])
    ->name('articles-dashboard');

Route::view('projects-dashboard', 'projects-dashboard')
    ->middleware(['auth:admin'])
    ->name('projects-dashboard');

Route::get('visitors-dashboard', App\Livewire\Admin\Visitors\Index::class)
    ->middleware(['auth:admin'])
    ->name('visitors-dashboard');

Route::get('projects/{project}/edit', App\Livewire\EditProject::class)
    ->middleware(['auth:admin'])
    ->name('projects.edit');

Route::get('properties/{property}/edit', App\Livewire\EditProperty::class)
    ->middleware(['auth:admin'])
    ->name('properties.edit');

Route::get('articles/{article}/edit', App\Livewire\EditArticle::class)
    ->middleware(['auth:admin'])
    ->name('articles.edit');

Route::post('admin/logout', App\Livewire\Actions\Logout::class)
    ->name('admin.logout');
