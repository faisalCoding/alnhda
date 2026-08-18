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

it('renders real estate listing and breadcrumb json-ld on a project page', function () {
    $project = Project::factory()->create();

    $html = $this->get(route('project', $project))->assertOk()->getContent();

    $blocks = extractJsonLd($html);

    $types = collect($blocks)->pluck('@type');

    expect($types)->toContain('RealEstateAgent')
        ->toContain('RealEstateListing')
        ->toContain('BreadcrumbList')
        ->not->toContain('Product');
});

it('renders real estate listing json-ld with a SAR offer on a properties page', function () {
    $project = Project::factory()->create();
    $unit = Properties::create([
        'name' => 'فيلا تجريبية رقم 7',
        'project_id' => $project->id,
        'status' => 'متاح',
        'price' => 1500000,
    ]);

    $html = $this->get(route('properties', $unit))->assertOk()->getContent();

    $listing = collect(extractJsonLd($html))->firstWhere('@type', 'RealEstateListing');

    expect($listing)->not->toBeNull()
        ->and($listing['name'])->toBe($unit->name)
        ->and($listing['offers']['priceCurrency'])->toBe('SAR')
        ->and($listing['offers']['price'])->toBe('1500000')
        ->and($listing['offers']['availability'])->toBe('https://schema.org/InStock');
});

it('marks a sold unit offer as sold out in the json-ld', function () {
    $project = Project::factory()->create();
    $unit = Properties::create([
        'name' => 'فيلا مباعة',
        'project_id' => $project->id,
        'status' => 'تم البيع',
        'price' => 2000000,
    ]);

    $html = $this->get(route('properties', $unit))->assertOk()->getContent();

    $listing = collect(extractJsonLd($html))->firstWhere('@type', 'RealEstateListing');

    expect($listing['offers']['availability'])->toBe('https://schema.org/SoldOut');
});

it('declares the document language as arabic with rtl direction', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false);
});

it('links to project pages with crawlable anchors on the projects index', function () {
    $project = Project::factory()->create();

    $this->get(route('projects'))
        ->assertOk()
        ->assertSee('href="'.route('project', $project).'"', false);
});

it('links to project pages with crawlable anchors on the home page carousel', function () {
    $project = Project::factory()->create();

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('href="'.route('project', $project).'"', false);
});

it('does not link to sold project pages from the projects index', function () {
    $soldProject = Project::factory()->create(['status' => 'تم البيع']);

    $this->get(route('projects'))
        ->assertOk()
        ->assertDontSee('href="'.route('project', $soldProject).'"', false);
});

it('links to the projects and articles pages from the footer', function () {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('href="'.route('projects').'"', false)
        ->assertSee('href="'.route('articles').'"', false);
});

it('renders keyword-rich titles without the KN prefix', function () {
    $project = Project::factory()->create(['name' => 'مشروع كيان التجريبي']);

    $this->get(route('projects'))
        ->assertOk()
        ->assertSee('<title>مشاريعنا العقارية - فلل وشقق للبيع في جدة | '.config('app.name').'</title>', false)
        ->assertDontSee('KN |', false);

    $this->get(route('project', $project))
        ->assertOk()
        ->assertSee('<title>مشروع كيان التجريبي - مشروع سكني في جدة | '.config('app.name').'</title>', false)
        ->assertDontSee('KN |', false);
});

it('serves an xml sitemap with an xml declaration and real content urls', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create();

    $response = $this->get(route('sitemap'))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/xml')
        ->and($response->getContent())->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($response->getContent())->toContain(route('project', $project))
        ->and($response->getContent())->toContain(route('article', $article));
});

it('marks auth pages as noindex', function () {
    $this->get('http://localhost/login')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow" />', false);
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
it('gives every static page a meta description of its own', function () {
    $descriptions = collect(['welcome', 'about-us', 'contact-us', 'privacy-policy', 'terms-of-use'])
        ->mapWithKeys(function (string $name): array {
            $html = $this->get(route($name))->assertOk()->getContent();
            preg_match('/<meta name="description" content="(.*?)">/s', $html, $matches);

            return [$name => $matches[1] ?? null];
        });

    $layoutFallback = 'شركة متخصصة وذات خبرة في التطوير العقاري. نقدم أفضل الحلول السكنية والاستثمارية. اكتشف مشاريعنا الآن!';

    expect($descriptions->filter())->toHaveCount(5)
        ->and($descriptions->unique())->toHaveCount(5)
        ->and($descriptions->filter(fn (?string $description): bool => $description === $layoutFallback))->toBeEmpty();
});

it('publishes both company registration numbers in the organization json-ld', function () {
    $html = $this->get(route('welcome'))->assertOk()->getContent();

    $schema = collect(extractJsonLd($html))->firstWhere('@type', 'RealEstateAgent');
    $identifiers = collect($schema['identifier'] ?? []);

    expect($schema)->not->toBeNull()
        ->and($identifiers->pluck('value')->all())->toBe(['7025720975', '1200019224'])
        ->and($identifiers->pluck('@type')->unique()->all())->toBe(['PropertyValue'])
        ->and($identifiers->pluck('name')->filter()->all())->toHaveCount(2);
});

function extractJsonLd(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return collect($matches[1])
        ->map(fn (string $json): ?array => json_decode(trim($json), true))
        ->filter()
        ->values()
        ->all();
}
