<?php

use App\Models\Project;
use App\Models\Blog;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Livewire\ControlBoard;


Route::domain(env('APP_URL'))->group(function () {

    Route::get('/', function () {
        return view('welcome');
    })->name('welcome');

    Route::view('/control-board', ControlBoard::class);


    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
        Volt::route('settings/password', 'settings.password')->name('settings.password');
        Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    });

    Route::view('projects', 'projects')->name('projects');
    Route::view('slider', 'slider');

    Route::view('blogs', 'blogs')->name('blogs');

    Route::get('project/{project}', function (Project $project) {
        return view('project', ['project' => $project]);
    })->name('project');

    Route::get('blog/{blog}', function (Blog $blog) {
        return view('blog', ['blog' => $blog]);
    })->name('blog');


    require __DIR__ . '/auth.php';
});

Route::domain('panel.' . env('APP_URL'))->group(function(){
    require __DIR__ . '/admin-auth.php';
});


