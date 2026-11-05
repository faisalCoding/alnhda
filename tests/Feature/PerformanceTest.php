<?php

use App\Models\Project;
use App\Models\Properties;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('eagerly loads the first row of project cards and lazy loads the rest', function () {
    Project::factory()->count(5)->create();

    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect(substr_count($html, 'loading="eager"'))->toBe(3)
        ->and(substr_count($html, 'loading="lazy"'))->toBeGreaterThanOrEqual(2);
});

it('does not load the unused bunny font or swiper css on public pages', function () {
    $html = $this->get(route('welcome'))->assertOk()->getContent();

    expect($html)->not->toContain('fonts.bunny.net')
        ->not->toContain('swiper-bundle.min.css');
});

it('loads the swiper stylesheet only on the unit page that uses it', function () {
    $project = Project::factory()->create();
    $unit = Properties::create([
        'name' => 'فيلا تجريبية',
        'project_id' => $project->id,
        'status' => 'متاح',
    ]);

    $html = $this->get(route('properties', $unit))->assertOk()->getContent();

    expect($html)->toContain('swiper-bundle.min.css');
});

it('lazy loads the heavy about page image with explicit dimensions', function () {
    $html = $this->get(route('about-us'))->assertOk()->getContent();

    expect($html)->toContain('rebarandplan.webp')
        ->toContain('loading="lazy" width="1200" height="1600"');
});

it('asks for the project cover before the rest of the page', function () {
    $project = Project::factory()->create(['image_url' => 'uploads/cover.webp']);

    $html = $this->get(route('project', $project))->assertOk()->getContent();

    expect($html)->toMatch('/<img src="[^"]*uploads\/cover\.webp" alt="[^"]*" fetchpriority="high"/');
});

it('asks for the first photo of a unit first and leaves the rest to the gallery', function () {
    $project = Project::factory()->create();
    $unit = Properties::create([
        'name' => 'فيلا تجريبية',
        'project_id' => $project->id,
        'status' => 'متاح',
    ]);

    foreach (['uploads/one.webp', 'uploads/two.webp', 'uploads/three.webp'] as $order => $url) {
        $unit->propertiesImages()->create(['url' => $url, 'order_by' => $order]);
    }

    $html = $this->get(route('properties', $unit))->assertOk()->getContent();

    expect(substr_count($html, 'fetchpriority="high"'))->toBe(1)
        ->and($html)->toMatch('/uploads\/one\.webp"[^>]*fetchpriority="high"/');
});
