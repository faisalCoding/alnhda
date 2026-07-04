<?php

use App\Http\Controllers\Admin\Api\ArticleController;
use App\Http\Controllers\Admin\Api\ArticleFileController;
use App\Http\Controllers\Admin\Api\DashboardController;
use App\Http\Controllers\Admin\Api\ProjectController;
use App\Http\Controllers\Admin\Api\ProjectFileController;
use App\Http\Controllers\Admin\Api\PropertyController;
use App\Http\Controllers\Admin\Api\PropertyFileController;
use App\Http\Controllers\Admin\Api\PropertyImageController;
use App\Http\Controllers\Admin\Api\SessionController;
use App\Http\Controllers\Admin\Api\VisitorController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\RegisteredAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('guest:admin')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('register', [RegisteredAdminController::class, 'create'])->name('admin.register');
    Route::post('register', [RegisteredAdminController::class, 'store'])->name('admin.register.store');
});

Route::post('admin/logout', [AuthenticatedSessionController::class, 'destroy'])->name('admin.logout');

Route::middleware('auth:admin')->group(function () {
    Route::view('dashboard', 'admin.dashboard')->name('dashboard');
    Route::view('projects-dashboard', 'admin.projects')->name('projects-dashboard');
    Route::view('articles-dashboard', 'admin.articles')->name('articles-dashboard');
    Route::view('visitors-dashboard', 'admin.visitors')->name('visitors-dashboard');
});

Route::prefix('api')->name('panel.api.')->group(function () {
    Route::get('session', [SessionController::class, 'show'])->name('session');

    Route::middleware('auth:admin')->group(function () {
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
        Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('visitors', [VisitorController::class, 'index'])->name('visitors.index');

        Route::middleware('idempotency')->group(function () {
            Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
            Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

            Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
            Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
            Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');

            Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
            Route::put('articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
            Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');

            Route::delete('property-images/{image}', [PropertyImageController::class, 'destroy'])->name('property-images.destroy');
        });

        Route::post('projects/{project}/image', [ProjectFileController::class, 'storeImage'])->name('projects.image');
        Route::post('projects/{project}/pdf', [ProjectFileController::class, 'storePdf'])->name('projects.pdf');
        Route::post('properties/{property}/images', [PropertyFileController::class, 'storeImages'])->name('properties.images');
        Route::post('properties/{property}/pdf', [PropertyFileController::class, 'storePdf'])->name('properties.pdf');
        Route::post('articles/{article}/image', [ArticleFileController::class, 'storeImage'])->name('articles.image');
    });
});
