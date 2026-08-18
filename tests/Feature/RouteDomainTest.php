<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

it('binds public routes to the configured application domain', function () {
    expect(Route::getRoutes()->getByName('welcome')->getDomain())
        ->toBe(config('app.domain'))
        ->not->toBeEmpty();
});

it('binds admin routes to the panel subdomain', function () {
    expect(Route::getRoutes()->getByName('dashboard')->getDomain())
        ->toBe('panel.'.config('app.domain'))
        ->not->toBe('panel.');
});

it('derives the application domain from app url with any scheme stripped', function (string $appUrl, string $expected) {
    $_SERVER['APP_URL'] = $appUrl;

    try {
        expect((require config_path('app.php'))['domain'])->toBe($expected);
    } finally {
        unset($_SERVER['APP_URL']);
    }
})->with([
    'https' => ['https://kayanalnhda.sa', 'kayanalnhda.sa'],
    'http' => ['http://kayanalnhda.sa', 'kayanalnhda.sa'],
    'trailing slash' => ['https://kayanalnhda.sa/', 'kayanalnhda.sa'],
    'no scheme' => ['kayanalnhda.sa', 'kayanalnhda.sa'],
    'localhost' => ['localhost', 'localhost'],
]);

it('has no duplicate route names so the routes can be cached', function () {
    $duplicates = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->countBy()
        ->filter(fn (int $count) => $count > 1)
        ->all();

    expect($duplicates)->toBeEmpty();
});

it('sends guests on the panel subdomain to the admin login', function () {
    $this->get(panelUrl('/dashboard'))->assertRedirect(route('admin.login'));
});

it('sends guests on the public site to the user login', function () {
    $this->get(route('settings.profile'))->assertRedirect(route('login'));
});

it('never calls env() outside of config files', function () {
    $offenders = collect(Finder::create()
        ->files()
        ->in([base_path('app'), base_path('routes'), base_path('bootstrap')])
        ->name('*.php'))
        ->filter(fn ($file) => preg_match('/(?<![\w>$])env\s*\(/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBeEmpty();
});
