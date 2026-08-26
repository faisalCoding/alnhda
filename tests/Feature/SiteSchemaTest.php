<?php

use App\Models\Project;
use App\Services\SiteSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<int, array<string, mixed>>
 */
function graphNodes(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    foreach ($matches[1] as $block) {
        $decoded = json_decode(trim($block), true);

        if (isset($decoded['@graph'])) {
            return $decoded['@graph'];
        }
    }

    return [];
}

/**
 * @param  array<int, array<string, mixed>>  $nodes
 */
function nodeOfType(array $nodes, string $type): ?array
{
    return collect($nodes)->firstWhere('@type', $type);
}

it('publishes the organisation, the site and the page as one graph', function () {
    $nodes = graphNodes($this->get(route('projects'))->assertOk()->getContent());

    expect(collect($nodes)->pluck('@type')->all())
        ->toContain('RealEstateAgent', 'WebSite', 'WebPage');
});

it('references the publisher by id instead of repeating it', function () {
    $nodes = graphNodes($this->get(route('projects'))->assertOk()->getContent());

    $website = nodeOfType($nodes, 'WebSite');
    $organisation = nodeOfType($nodes, 'RealEstateAgent');

    expect($website['publisher'])->toBe(['@id' => $organisation['@id']]);
});

it('ties the page to the site it belongs to', function () {
    $nodes = graphNodes($this->get(route('about-us'))->assertOk()->getContent());

    $page = nodeOfType($nodes, 'WebPage');
    $website = nodeOfType($nodes, 'WebSite');

    expect($page['isPartOf'])->toBe(['@id' => $website['@id']])
        ->and($page['url'])->toBe(route('about-us'));
});

it('builds a breadcrumb trail for a fixed page', function () {
    $nodes = graphNodes($this->get(route('articles'))->assertOk()->getContent());

    $crumbs = nodeOfType($nodes, 'BreadcrumbList');

    expect($crumbs['itemListElement'])->toHaveCount(2)
        ->and($crumbs['itemListElement'][0]['name'])->toBe('الرئيسية')
        ->and($crumbs['itemListElement'][1]['name'])->toBe('المقالات');
});

it('leaves the home page without a breadcrumb', function () {
    $nodes = graphNodes($this->get(route('welcome'))->assertOk()->getContent());

    expect(nodeOfType($nodes, 'BreadcrumbList'))->toBeNull();
});

it('does not contradict the trail a record builds for itself', function () {
    $project = Project::factory()->create();

    $html = $this->get(route('project', $project))->assertOk()->getContent();

    // العقد العامة لا تحمل فتاتًا هنا؛ الفتات الوحيد هو الذي تبنيه الصفحة.
    expect(nodeOfType(graphNodes($html), 'BreadcrumbList'))->toBeNull()
        ->and($html)->toContain('BreadcrumbList');
});

it('keeps the licence identifiers a real estate agency must publish', function () {
    $organisation = app(SiteSchema::class)->organization();

    expect(collect($organisation['identifier'])->pluck('value')->all())
        ->toContain('7025720975', '1200019224');
});
