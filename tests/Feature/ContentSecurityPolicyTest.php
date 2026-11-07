<?php

use App\Models\Article;
use App\Models\Project;
use App\Models\Properties;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * A unit with photos and both videos, so the gallery, the lightbox and the
 * video thumbnails all render.
 */
function unitWithEverything(): Properties
{
    $unit = Properties::create([
        'name' => 'فيلا تجريبية',
        'project_id' => Project::factory()->create()->id,
        'status' => 'متاح',
        'unit_youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'stages_building_youtube' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $unit->propertiesImages()->create(['url' => 'uploads/one.webp', 'order_by' => 0]);
    $unit->propertiesImages()->create(['url' => 'uploads/two.webp', 'order_by' => 1]);

    return $unit;
}

/**
 * @return array{policy: string, nonce: string, html: string}
 */
function securedPage(TestResponse $response): array
{
    $policy = (string) $response->assertOk()->headers->get('Content-Security-Policy');

    preg_match("/script-src [^;]*'nonce-([^']+)'/", $policy, $nonce);

    return ['policy' => $policy, 'nonce' => $nonce[1] ?? '', 'html' => (string) $response->getContent()];
}

/**
 * The value of every attribute Alpine evaluates, decoded the way the browser
 * hands it to Alpine.
 *
 * @return list<string>
 */
function alpineExpressions(string $html): array
{
    preg_match_all('/\s(?:x-data|x-init|x-effect|x-show|x-text|x-html|x-model|x-bind:[\w.-]+|x-on:[\w.:-]+|:[\w-]+|@[\w.:-]+)="([^"]*)"/', $html, $matches);

    return array_map(fn (string $value): string => html_entity_decode($value, ENT_QUOTES), $matches[1]);
}

dataset('public pages', [
    'home' => fn () => route('welcome'),
    'projects' => fn () => route('projects'),
    'project' => fn () => route('project', Project::factory()->create()),
    'unit' => fn () => route('properties', unitWithEverything()),
    'articles' => fn () => route('articles'),
    'article' => fn () => route('article', Article::factory()->create()),
    'contact' => fn () => route('contact-us'),
    'login' => fn () => route('login'),
]);

dataset('settings pages', [
    'profile' => '/settings/profile',
    'password' => '/settings/password',
    'appearance' => '/settings/appearance',
]);

beforeEach(function () {
    Project::factory()->create(['status' => 'متاح']);
    Project::factory()->create(['status' => 'تم البيع']);
});

it('runs only the scripts the page vouched for', function (string $url) {
    ['policy' => $policy, 'nonce' => $nonce, 'html' => $html] = securedPage($this->get($url));

    preg_match('/script-src ([^;]*)/', $policy, $scriptSrc);
    preg_match_all('/<script\b(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)[^>]*>/', $html, $inlineScripts);

    expect($nonce)->not->toBe('')
        ->and($scriptSrc[1])->not->toContain("'unsafe-inline'")
        ->not->toContain("'unsafe-eval'")
        ->and($inlineScripts[0])->not->toBeEmpty()
        ->each->toContain('nonce="'.$nonce.'"');
})->with('public pages');

it('carries no inline event handlers, which a nonce cannot cover', function (string $url) {
    $html = securedPage($this->get($url))['html'];

    preg_match_all('/<[a-z][^>]*\son[a-z]+\s*=/i', $html, $handlers);

    expect($handlers[0])->toBe([]);
})->with('public pages');

it('keeps every Alpine expression to what the CSP build of Alpine evaluates', function (string $url) {
    $html = securedPage($this->get($url))['html'];

    $offending = array_filter(
        alpineExpressions($html),
        fn (string $expression): bool => (bool) preg_match('/=>|\bfunction\b|\b(window|document|navigateTo|lightboxSwiper)\b|`|;|\w\s*\([^)]*\)\s*\{/', $expression),
    );

    expect(array_values($offending))->toBe([]);
})->with('public pages');

it('binds nothing on an iframe, which the CSP build of Alpine refuses to evaluate', function () {
    $html = securedPage($this->get(route('properties', unitWithEverything())))['html'];

    preg_match_all('/<iframe\b[^>]*>/', $html, $iframes);

    expect($iframes[0])->not->toBeEmpty()
        ->each->not->toMatch('/\s(x-(?!cloak|ref)[\w:.-]+|:[\w-]+|@[\w.:-]+)=/')
        ->and($html)->toMatch('/x-data="youtubePlayer"\s+data-video="https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?autoplay=1&amp;rel=0&amp;iv_load_policy=3"/');
});

it('holds the settings pages to the same rules', function (string $path) {
    $this->actingAs(User::factory()->create());

    ['nonce' => $nonce, 'html' => $html] = securedPage($this->get($path));

    preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/', $html, $inlineScripts);

    $offending = array_filter(
        alpineExpressions($html),
        fn (string $expression): bool => (bool) preg_match('/=>|\bfunction\b|\b(window|document)\b|@this|`|;/', $expression),
    );

    expect($inlineScripts[0])->each->toContain('nonce="'.$nonce.'"')
        ->and(array_values($offending))->toBe([]);
})->with('settings pages');

it('loads the build of Livewire that never turns strings into code', function () {
    $html = securedPage($this->get(route('welcome')))['html'];

    expect($html)->toMatch('/<script src="[^"]*\/livewire(\.min)?\.js\?id=[^"]*-csp"/');
});

it('hands every response a nonce of its own', function () {
    $first = securedPage($this->get(route('welcome')))['nonce'];
    $second = securedPage($this->get(route('welcome')))['nonce'];

    expect($first)->not->toBe($second);
});

it('closes what a policy should close and allows only the hosts the site uses', function () {
    $policy = securedPage($this->get(route('welcome')))['policy'];

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('https://*.googletagmanager.com')
        ->toContain('https://fonts.googleapis.com')
        ->toContain('https://fonts.gstatic.com')
        ->toContain('https://maps.google.com')
        ->toContain('https://www.youtube-nocookie.com');
});

it('swaps a broken photo for a stand-in without an inline handler', function () {
    $html = securedPage($this->get(route('properties', unitWithEverything())))['html'];

    expect($html)->toMatch('/<img src="https:\/\/img\.youtube\.com\/[^"]*"[^>]*data-fallback="[^"]*img\/article\.webp"/s')
        ->toContain("document.addEventListener('error'");
});

it('leaves the admin panel to its own scripts', function () {
    $this->get(panelUrl('/admin/login'))
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy');
});
