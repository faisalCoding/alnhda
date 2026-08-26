<?php

use App\Models\AppSettings;
use App\Models\SeoMeta;
use App\Models\SeoPage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── robots ───────────────────────────────────────────────────────────────────

it('invites indexing and large image previews by default', function () {
    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->toContain('index, follow, max-image-preview:large');
});

it('keeps a page out of the index when the panel says so', function () {
    SeoPage::factory()->create(['route_name' => 'projects', 'noindex' => true]);

    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->toContain('noindex, nofollow')
        ->and($html)->not->toContain('max-image-preview');
});

// ── keywords ─────────────────────────────────────────────────────────────────

it('omits the keywords tag entirely when none are set', function () {
    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->not->toContain('name="keywords"');
});

it('publishes the site-wide keywords', function () {
    AppSettings::current()->update(['seo_keywords' => 'تطوير عقاري, شقق جدة']);

    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->toContain('تطوير عقاري, شقق جدة');
});

it('lets a record override the site-wide keywords', function () {
    AppSettings::current()->update(['seo_keywords' => 'كلمات عامة']);

    $project = \App\Models\Project::factory()->create();
    $project->seoMeta()->save(new SeoMeta(['keywords' => 'كلمات المشروع']));

    $html = $this->get(route('project', $project))->assertOk()->getContent();

    expect($html)->toContain('كلمات المشروع')
        ->and($html)->not->toContain('كلمات عامة');
});

// ── author و theme-color ─────────────────────────────────────────────────────

it('publishes the author and theme colour when set', function () {
    AppSettings::current()->update([
        'seo_author' => 'كيان النهضة العقارية',
        'seo_theme_color' => '#0f3d2e',
    ]);

    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->toContain('name="author" content="كيان النهضة العقارية"')
        ->and($html)->toContain('name="theme-color" content="#0f3d2e"');
});

it('omits both tags when they are not set', function () {
    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->not->toContain('name="author"')
        ->and($html)->not->toContain('name="theme-color"');
});

// ── بطاقات المشاركة ──────────────────────────────────────────────────────────

it('describes the share image for screen readers on twitter too', function () {
    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->toContain('twitter:image:alt');
});
