<?php

use App\Models\Article;
use App\Models\CollectionPage;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pageWith(array $records): CollectionPage
{
    $page = CollectionPage::factory()->create();

    foreach (array_values($records) as $position => $record) {
        $page->items()->create([
            'item_type' => $record::class,
            'item_id' => $record->id,
            'sort_order' => $position,
        ]);
    }

    return $page;
}

it('is reached by its written link, not by a number', function () {
    $page = CollectionPage::factory()->create(['slug' => 'شقق-جاهزة']);

    expect(route('collection', $page))->toEndWith('/collections/'.rawurlencode('شقق-جاهزة'));

    $this->get(route('collection', $page))->assertOk();
});

it('shows the title and the description above the records', function () {
    $page = CollectionPage::factory()->create([
        'title' => 'شقق جاهزة للتسليم',
        'description' => 'مجموعة مختارة من الوحدات الجاهزة.',
    ]);

    $this->get(route('collection', $page))
        ->assertOk()
        ->assertSee('شقق جاهزة للتسليم', false)
        ->assertSee('مجموعة مختارة من الوحدات الجاهزة.', false);
});

it('links to every record it gathers, whatever its kind', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create();
    $unit = Properties::create([
        'name' => 'شقة رقم 12',
        'project_id' => $project->id,
        'status' => 'متاح',
    ]);

    $page = pageWith([$project, $unit, $article]);

    $this->get(route('collection', $page))
        ->assertOk()
        ->assertSee(route('project', $project), false)
        ->assertSee(route('properties', $unit), false)
        ->assertSee(route('article', $article), false);
});

it('keeps the order the records were arranged in', function () {
    $first = Project::factory()->create(['name' => 'المشروع الأول']);
    $second = Project::factory()->create(['name' => 'المشروع الثاني']);

    $page = pageWith([$second, $first]);
    $html = $this->get(route('collection', $page))->assertOk()->getContent();

    expect(strpos($html, 'المشروع الثاني'))->toBeLessThan(strpos($html, 'المشروع الأول'));
});

it('drops a record that was deleted instead of breaking the page', function () {
    $project = Project::factory()->create(['name' => 'مشروع محذوف']);
    $kept = Article::factory()->create();
    $page = pageWith([$project, $kept]);

    $project->delete();

    $this->get(route('collection', $page))
        ->assertOk()
        ->assertDontSee('مشروع محذوف', false)
        ->assertSee(route('article', $kept), false);
});

it('says so plainly when nothing has been added yet', function () {
    $page = CollectionPage::factory()->create();

    $this->get(route('collection', $page))
        ->assertOk()
        ->assertSee('لا يوجد محتوى في هذه الصفحة بعد.', false);
});

it('borrows its share image from the first record it gathers', function () {
    $article = Article::factory()->create(['image_article' => 'articles/cover.webp']);
    $page = pageWith([$article]);

    $this->get(route('collection', $page))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('storage/articles/cover.webp').'"', false);
});

it('lists itself in the sitemap and in llms.txt', function () {
    $page = CollectionPage::factory()->create(['slug' => 'شقق-جاهزة']);

    $this->get(route('sitemap'))->assertOk()->assertSee(route('collection', $page), false);
    $this->get(route('llms'))->assertOk()->assertSee(route('collection', $page), false);
});

it('stays out of both once it is switched to noindex', function () {
    $page = CollectionPage::factory()->create(['slug' => 'صفحة-حملة']);
    $page->seoMeta()->create(['noindex' => true]);

    $this->get(route('sitemap'))->assertOk()->assertDontSee(route('collection', $page), false);
    $this->get(route('llms'))->assertOk()->assertDontSee(route('collection', $page), false);
    $this->get(route('collection', $page))->assertOk()->assertSee('noindex, nofollow', false);
});

it('publishes its records as one item list for crawlers', function () {
    $project = Project::factory()->create();
    $page = pageWith([$project]);

    $this->get(route('collection', $page))
        ->assertOk()
        ->assertSee('"@type":"CollectionPage"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee(route('project', $project), false);
});
