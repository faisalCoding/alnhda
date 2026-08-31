<?php

use App\Models\Admin;
use App\Models\Article;
use App\Models\CollectionPage;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function unitFor(?Project $project = null): Properties
{
    return Properties::create([
        'name' => 'شقة رقم 12',
        'project_id' => ($project ?? Project::factory()->create())->id,
        'status' => 'متاح',
    ]);
}

it('keeps collection pages away from guests', function () {
    $this->getJson(panelUrl('/api/collections'))->assertUnauthorized();
});

it('creates a page holding a project, a unit and an article in the order they were arranged', function () {
    $project = Project::factory()->create();
    $unit = unitFor();
    $article = Article::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/collections'), [
            'slug' => 'شقق-جاهزة',
            'title' => 'شقق جاهزة للتسليم',
            'description' => 'وحدات ومشاريع جاهزة.',
            'items' => ['article:'.$article->id, 'project:'.$project->id, 'properties:'.$unit->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'شقق-جاهزة')
        ->assertJsonPath('data.items', ['article:'.$article->id, 'project:'.$project->id, 'properties:'.$unit->id])
        ->assertJsonPath('data.item_details.1.name', $project->name);

    expect(CollectionPage::query()->first()->items()->pluck('item_type')->all())
        ->toBe([Article::class, Project::class, Properties::class]);
});

it('rewrites the whole list when the order changes', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create();
    $page = CollectionPage::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/collections/'.$page->id), [
            'slug' => $page->slug,
            'title' => $page->title,
            'items' => ['project:'.$project->id, 'article:'.$article->id],
        ])
        ->assertSuccessful();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/collections/'.$page->id), [
            'slug' => $page->slug,
            'title' => $page->title,
            'items' => ['article:'.$article->id, 'project:'.$project->id],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.items', ['article:'.$article->id, 'project:'.$project->id]);

    expect($page->items()->count())->toBe(2);
});

it('leaves the page as it stands when an edit carries no list', function () {
    $project = Project::factory()->create();
    $page = CollectionPage::factory()->create();
    $page->items()->create(['item_type' => Project::class, 'item_id' => $project->id, 'sort_order' => 0]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/collections/'.$page->id), [
            'slug' => $page->slug,
            'title' => 'عنوان محدث',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'عنوان محدث')
        ->assertJsonPath('data.items', ['project:'.$project->id]);
});

it('empties the page when an empty list is sent on purpose', function () {
    $project = Project::factory()->create();
    $page = CollectionPage::factory()->create();
    $page->items()->create(['item_type' => Project::class, 'item_id' => $project->id, 'sort_order' => 0]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/collections/'.$page->id), [
            'slug' => $page->slug,
            'title' => $page->title,
            'items' => [],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.items', []);

    expect($page->items()->count())->toBe(0);
});

it('refuses a page whose link is already taken', function () {
    CollectionPage::factory()->create(['slug' => 'شقق-جاهزة']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/collections'), ['slug' => 'شقق-جاهزة', 'title' => 'عنوان'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('keeps the link a page already has available to itself', function () {
    $page = CollectionPage::factory()->create(['slug' => 'شقق-جاهزة']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/collections/'.$page->id), ['slug' => 'شقق-جاهزة', 'title' => 'عنوان آخر'])
        ->assertSuccessful();
});

it('refuses a link that would not survive being pasted', function (string $slug) {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/collections'), ['slug' => $slug, 'title' => 'عنوان'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
})->with([
    'spaces' => 'شقق جاهزة',
    'slash' => 'شقق/جاهزة',
    'query' => 'شقق?x=1',
    'trailing dash' => 'شقق-',
]);

it('accepts an arabic link written with dashes', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/collections'), ['slug' => 'شقق-جاهزة-للتسليم', 'title' => 'عنوان'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'شقق-جاهزة-للتسليم');
});

it('refuses an item that does not exist', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/collections'), [
            'slug' => 'صفحة',
            'title' => 'عنوان',
            'items' => ['project:999'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0');
});

it('refuses a page placed inside a page', function () {
    $other = CollectionPage::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/collections'), [
            'slug' => 'صفحة',
            'title' => 'عنوان',
            'items' => ['collection:'.$other->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0');
});

it('refuses the same record twice on one page', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/collections'), [
            'slug' => 'صفحة',
            'title' => 'عنوان',
            'items' => ['project:'.$project->id, 'project:'.$project->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.1');
});

it('deletes a page and the arrangement with it, leaving the records alone', function () {
    $project = Project::factory()->create();
    $page = CollectionPage::factory()->create();
    $page->items()->create(['item_type' => Project::class, 'item_id' => $project->id, 'sort_order' => 0]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/collections/'.$page->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true);

    expect(CollectionPage::query()->count())->toBe(0)
        ->and(Project::query()->count())->toBe(1);
});

it('leaves out an item whose record was deleted', function () {
    $project = Project::factory()->create();
    $page = CollectionPage::factory()->create();
    $page->items()->create(['item_type' => Project::class, 'item_id' => $project->id, 'sort_order' => 0]);
    $project->delete();

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/collections'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.items', []);
});

it('serves the collection pages screen to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get(panelUrl('/collections-dashboard'))
        ->assertOk()
        ->assertSee('الصفحات المجمّعة')
        ->assertSee('collectionsPage()', false);
});

it('keeps the collection pages screen away from guests', function () {
    $this->withoutVite()
        ->get(panelUrl('/collections-dashboard'))
        ->assertRedirect(route('admin.login'));
});
