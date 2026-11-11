<?php

use App\Http\Controllers\Api\LawhatiController;
use App\Http\Controllers\Api\MarketCatalogueController;
use App\Http\Controllers\Api\TeamProgressController;
use App\Http\Controllers\Api\WeeklyTaskController;
use App\Models\Admin;
use App\Models\Employee;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Task API
|--------------------------------------------------------------------------
|
| Consumed by the owner's personal site. A token is issued against a single
| employee, so `self` endpoints can only ever reach that employee's own weeks.
| The team endpoint is read-only and gated behind its own ability.
|
*/

Route::middleware(['auth:sanctum', 'throttle:tasks-api'])->group(function () {
    Route::middleware('abilities:tasks:self')->group(function () {
        Route::get('me', [WeeklyTaskController::class, 'me'])->name('api.me');
        Route::get('me/tasks', [WeeklyTaskController::class, 'index'])->name('api.tasks.index');
        Route::post('me/tasks', [WeeklyTaskController::class, 'store'])->name('api.tasks.store');
        Route::patch('me/tasks/{item}', [WeeklyTaskController::class, 'update'])->name('api.tasks.update');
        Route::delete('me/tasks/{item}', [WeeklyTaskController::class, 'destroy'])->name('api.tasks.destroy');
    });

    Route::middleware('abilities:team:read')->group(function () {
        Route::get('team/progress', TeamProgressController::class)->name('api.team.progress');
    });
});

/*
|--------------------------------------------------------------------------
| لوحتي API
|--------------------------------------------------------------------------
|
| Read by لوحتي, the team's Kanban app, with an employee's sign-in code
| (issued from the employees list in the panel). A code can only name its
| employee and list the team, so cards can be assigned to people.
|
*/

Route::middleware(['auth:sanctum', 'abilities:'.Employee::LAWHATI_TOKEN, 'throttle:lawhati-api'])
    ->prefix('lawhati')
    ->group(function () {
        Route::get('me', [LawhatiController::class, 'me'])->name('api.lawhati.me');
        Route::get('team', [LawhatiController::class, 'team'])->name('api.lawhati.team');
    });

/*
|--------------------------------------------------------------------------
| Market catalogue
|--------------------------------------------------------------------------
|
| The open data portal answers only from inside the Kingdom, so the server
| cannot ask it which quarters have been published. A computer that can runs
| `market:check --push` and sends what it found here, with a token that can
| do nothing else.
|
*/

Route::post('market/catalogue', MarketCatalogueController::class)
    ->middleware(['auth:sanctum', 'abilities:'.Admin::MARKET_CATALOGUE_TOKEN, 'throttle:20,1'])
    ->name('api.market.catalogue');
