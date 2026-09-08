<?php

use App\Http\Controllers\Api\TeamProgressController;
use App\Http\Controllers\Api\WeeklyTaskController;
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
