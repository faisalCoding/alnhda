<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();

    $this->sidebar = $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get('http://panel.localhost/dashboard')
        ->assertOk()
        ->getContent();
});

it('shows every group heading', function (string $heading) {
    expect($this->sidebar)->toContain($heading);
})->with(['المحتوى', 'العملاء', 'الواتساب', 'التسويق', 'الإدارة']);

it('links to every panel page from the sidebar', function (string $routeName) {
    expect($this->sidebar)->toContain('href="'.route($routeName).'"');
})->with([
    'dashboard', 'projects-dashboard', 'articles-dashboard', 'leads-dashboard',
    'visitors-dashboard', 'whatsapp-dashboard', 'whatsapp-messages',
    'marketing-tools', 'backlinks', 'useful-links', 'accounts', 'subscriptions',
]);

it('leaves no panel page out of the sidebar', function () {
    // Every GET view route behind the admin guard should be reachable from the nav.
    $pageRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array('GET', $route->methods(), true)
            && str_starts_with((string) $route->getDomain(), 'panel.')
            && ! str_starts_with($route->uri(), 'api/')
            && ! str_starts_with($route->uri(), 'admin/')
            && $route->getName() !== null)
        ->map(fn ($route): string => (string) $route->getName())
        ->values();

    $html = $this->sidebar;
    $missing = $pageRoutes->reject(fn (string $name): bool => str_contains($html, 'href="'.route($name).'"'));

    expect($missing->all())->toBeEmpty();
});

it('marks the page you are on as the current one', function () {
    $html = $this->sidebar;

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and($html)->toContain('aria-current="page"');
});
