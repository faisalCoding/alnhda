<?php

use App\Models\Article;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders a dynamic canonical url on a project page', function () {
    $project = Project::factory()->create();

    $this->get(route('project', $project))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('project', $project).'">', false);
});

it('renders a dynamic meta description from the project description', function () {
    $project = Project::factory()->create([
        'description' => 'وصف تجريبي مميز لمشروع عقاري فاخر في مدينة جدة.',
    ]);

    $expected = Str::limit(strip_tags($project->description), 155);

    $this->get(route('project', $project))
        ->assertOk()
        ->assertSee($expected, false);
});

it('renders a dynamic meta description from the article content', function () {
    $article = Article::factory()->create([
        'content' => '<p>محتوى تجريبي للمقال عن التطوير العقاري في المملكة.</p>',
    ]);

    $expected = Str::limit(strip_tags($article->content), 155);

    $this->get(route('article', $article))
        ->assertOk()
        ->assertSee($expected, false);
});

it('renders an h1 with the unit name on a properties page', function () {
    $project = Project::factory()->create();
    $unit = Properties::create([
        'name' => 'فيلا تجريبية رقم 7',
        'project_id' => $project->id,
        'status' => 'متاح',
    ]);

    $this->get(route('properties', $unit))
        ->assertOk()
        ->assertSee('<h1', false)
        ->assertSee($unit->name, false);
});

it('builds a dynamic meta description from the unit specs', function () {
    $project = Project::factory()->create(['location' => 'جدة حي السامر']);
    $unit = Properties::create([
        'name' => 'فيلا رقم 12',
        'project_id' => $project->id,
        'status' => 'متاح',
        'type' => 'فيلا',
        'rooms' => 5,
        'bathrooms' => 4,
        'area' => 350,
    ]);

    $this->get(route('properties', $unit))
        ->assertOk()
        ->assertSee('فيلا رقم 12 - فيلا', false)
        ->assertSee('جدة حي السامر', false)
        ->assertSee('5 غرف', false)
        ->assertSee('بمساحة 350 م²', false);
});

it('renders rtl direction, twitter card and locale meta on the layout', function () {
    $project = Project::factory()->create();

    $this->get(route('project', $project))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('<meta property="og:locale" content="ar_SA" />', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', false);
});

it('renders valid product and breadcrumb json-ld on a project page', function () {
    $project = Project::factory()->create();

    $html = $this->get(route('project', $project))->assertOk()->getContent();

    $blocks = extractJsonLd($html);

    $types = collect($blocks)->pluck('@type');

    expect($types)->toContain('RealEstateAgent')
        ->toContain('Product')
        ->toContain('BreadcrumbList');
});

it('renders product json-ld with a SAR offer on a properties page', function () {
    $project = Project::factory()->create();
    $unit = Properties::create([
        'name' => 'فيلا تجريبية رقم 7',
        'project_id' => $project->id,
        'status' => 'متاح',
        'price' => 1500000,
    ]);

    $html = $this->get(route('properties', $unit))->assertOk()->getContent();

    $product = collect(extractJsonLd($html))->firstWhere('@type', 'Product');

    expect($product)->not->toBeNull()
        ->and($product['name'])->toBe($unit->name)
        ->and($product['offers']['priceCurrency'])->toBe('SAR')
        ->and($product['offers']['price'])->toBe('1500000');
});

it('renders article json-ld with publish dates', function () {
    $article = Article::factory()->create();

    $html = $this->get(route('article', $article))->assertOk()->getContent();

    $schema = collect(extractJsonLd($html))->firstWhere('@type', 'Article');

    expect($schema)->not->toBeNull()
        ->and($schema['headline'])->toBe($article->title)
        ->and($schema)->toHaveKeys(['datePublished', 'dateModified']);
});

/**
 * Extract and decode every JSON-LD block from an HTML document.
 *
 * @return array<int, array<string, mixed>>
 */
function extractJsonLd(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return collect($matches[1])
        ->map(fn (string $json): ?array => json_decode(trim($json), true))
        ->filter()
        ->values()
        ->all();
}
