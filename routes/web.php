<?php

use App\Livewire\ControlBoard;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

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

    Route::view('articles', 'articles')->name('articles');

    Route::get('project/{project}', function (Project $project) {
        return view('project', ['project' => $project]);
    })->name('project');

    Route::get('project/{project}/download', function (Project $project) {
        if (empty($project->pdf_path) || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($project->pdf_path)) {
            abort(404);
        }
        $filename = $project->name.'.pdf';

        return \Illuminate\Support\Facades\Storage::disk('public')->download($project->pdf_path, $filename);
    })->name('project.download');

    Route::get('article/{article}', function (Article $article) {
        return view('article', ['article' => $article]);
    })->name('article');

    Route::get('properties/{properties}', function (\App\Models\Properties $properties) {
        return view('properties', ['properties' => $properties]);
    })->name('properties');

    Route::get('properties/{properties}/download', function (\App\Models\Properties $properties) {
        if (empty($properties->pdf_path) || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($properties->pdf_path)) {
            abort(404);
        }
        $filename = $properties->name.'.pdf';

        return \Illuminate\Support\Facades\Storage::disk('public')->download($properties->pdf_path, $filename);
    })->name('properties.download');

    Route::view('about-us', 'about-us')->name('about-us');
    Route::view('contact-us', 'contact-us')->name('contact-us');
    Route::view('privacy-policy', 'privacy-policy')->name('privacy-policy');
    Route::view('terms-of-use', 'terms-of-use')->name('terms-of-use');

    Route::get('/sitemap.xml', function () {
        $projects = Project::all();
        $properties = \App\Models\Properties::all();
        $articles = Article::all();

        return response()->view('sitemap', [
            'projects' => $projects,
            'properties' => $properties,
            'articles' => $articles,
        ])->header('Content-Type', 'text/xml');
    })->name('sitemap');

    require __DIR__.'/auth.php';
});

Route::domain('panel.'.env('APP_URL'))->group(function () {
    require __DIR__.'/admin-auth.php';
});
