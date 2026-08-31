<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * The logo is one file referenced from five places. A swap that misses one of
 * them shows two different logos on the same site, which is the failure worth
 * catching here.
 */
const LOGO_PATH = 'img/alnhda-logo.webp';

it('serves the logo file itself', function () {
    $path = public_path(LOGO_PATH);

    expect(file_exists($path))->toBeTrue()
        ->and(rawurlencode(basename($path)))->toBe(basename($path));
});

it('carries the logo on every public page', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee(LOGO_PATH, false);
})->with(['welcome', 'about-us', 'projects', 'articles', 'contact-us']);

it('leaves no page still pointing at the logo that was replaced', function () {
    $stale = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file): bool => str_contains($file->getContents(), 'alnhdafooterlogo'))
        ->map(fn ($file): string => $file->getRelativePathname())
        ->values();

    expect($stale->all())->toBeEmpty();
});

it('declares the size the logo really is, so the header does not jump while it loads', function () {
    [$width, $height] = getimagesize(public_path(LOGO_PATH));

    $html = $this->get(route('welcome'))->assertOk()->getContent();

    expect($html)->toContain('width="'.$width.'" height="'.$height.'"');
});
