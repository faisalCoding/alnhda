<?php

use App\Models\Article;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves llms.txt as plain utf-8 text', function () {
    $response = $this->get(route('llms'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8');
});

it('opens with the h1 and blockquote the spec requires', function () {
    $lines = explode("\n", $this->get(route('llms'))->assertOk()->getContent());

    expect($lines[0])->toBe('# كيان النهضة العقارية')
        ->and($lines[2])->toStartWith('> ');
});

it('states the company credentials', function () {
    $content = $this->get(route('llms'))->assertOk()->getContent();

    expect($content)
        ->toContain('7025720975')
        ->toContain('وزارة التجارة')
        ->toContain('1200019224')
        ->toContain('الهيئة العامة للعقار')
        ->toContain('+966564364261')
        ->toContain('جدة');
});

it('lists available projects, units and articles with absolute links', function () {
    $project = Project::factory()->create(['name' => 'مشروع الواحة', 'status' => 'جديد']);
    $property = Properties::factory()->create(['name' => 'وحدة أ', 'project_id' => $project->id]);
    $article = Article::factory()->create(['title' => 'اختيار الشقة المناسبة']);

    $content = $this->get(route('llms'))->assertOk()->getContent();

    expect($content)
        ->toContain('[مشروع الواحة]('.route('project', $project).')')
        ->toContain('[وحدة أ]('.route('properties', $property).')')
        ->toContain('[اختيار الشقة المناسبة]('.route('article', $article).')');
});

it('moves sold projects out of the main listing and into the optional section', function () {
    $sold = Project::factory()->create(['name' => 'مشروع منتهٍ', 'status' => 'تم البيع']);
    Project::factory()->create(['name' => 'مشروع متاح', 'status' => 'جديد']);

    $content = $this->get(route('llms'))->assertOk()->getContent();
    [$main, $optional] = explode('## Optional', $content);

    expect($main)->toContain('مشروع متاح')->not->toContain('مشروع منتهٍ')
        ->and($optional)->toContain('[مشروع منتهٍ]('.route('project', $sold).')');
});

it('squishes newlines out of descriptions so each entry stays on one line', function () {
    $project = Project::factory()->create([
        'name' => 'مشروع الأمواج',
        'status' => 'جديد',
        'description' => "<p>وصف يحتوي على\nسطر جديد</p>\r\n<p>وفقرة أخرى</p>",
    ]);

    $entry = collect(explode("\n", $this->get(route('llms'))->assertOk()->getContent()))
        ->first(fn (string $line): bool => str_contains($line, '[مشروع الأمواج]'));

    expect($entry)->toContain('وصف يحتوي على سطر جديد وفقرة أخرى');
});

it('escapes nothing that would break the markdown link syntax', function () {
    Project::factory()->create(['name' => 'مشروع', 'status' => 'جديد']);

    $entries = collect(explode("\n", $this->get(route('llms'))->assertOk()->getContent()))
        ->filter(fn (string $line): bool => str_starts_with($line, '- ['));

    expect($entries)->not->toBeEmpty()
        ->and($entries->filter(fn (string $line): bool => ! preg_match('/^- \[[^\]]+\]\(https?:\/\/\S+\)/', $line)))
        ->toBeEmpty();
});
