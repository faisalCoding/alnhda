<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('shows the unified company number in the about section on the home page', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('الرقم الموحد للشركة', false)
        ->assertSee('7025720975', false);
});

it('shows the ministry logo and the unified number in the footer of every public page', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect($html)
        ->toContain('img/ministry-of-commerce.svg')
        ->toContain('شعار وزارة التجارة')
        ->toContain('7025720975');
})->with(['welcome', 'about-us', 'projects', 'contact-us']);

it('shows the fal licence with its issuing authority in the footer', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect($html)
        ->toContain('1200019224')
        ->toContain('img/fal.webp')
        ->toContain('الهيئة العامة للعقار');
})->with(['welcome', 'about-us', 'projects', 'contact-us']);

it('serves the ministry logo from a path that needs no url encoding', function () {
    $path = public_path('img/ministry-of-commerce.svg');

    expect(file_exists($path))->toBeTrue()
        ->and(rawurlencode(basename($path)))->toBe(basename($path));
});
