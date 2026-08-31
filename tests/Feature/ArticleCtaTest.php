<?php

use App\Models\Article;
use App\Models\CollectionPage;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the button an article carries, with its own wording', function () {
    $project = Project::factory()->create(['name' => 'مشروع النهضة']);
    $article = Article::factory()->create([
        'cta_label' => 'تصفّح وحدات المشروع',
        'cta_target_type' => Project::class,
        'cta_target_id' => $project->id,
    ]);

    $this->get(route('article', $article))
        ->assertOk()
        ->assertSee('تصفّح وحدات المشروع', false)
        ->assertSee(route('project', $project), false);
});

it('names the button after its destination when no wording was written', function () {
    $project = Project::factory()->create(['name' => 'مشروع النهضة']);
    $article = Article::factory()->create([
        'cta_label' => null,
        'cta_target_type' => Project::class,
        'cta_target_id' => $project->id,
    ]);

    $this->get(route('article', $article))
        ->assertOk()
        ->assertSee('مشروع النهضة', false)
        ->assertSee(route('project', $project), false);
});

it('points a button at a unit', function () {
    $unit = Properties::create([
        'name' => 'شقة رقم 12',
        'project_id' => Project::factory()->create()->id,
        'status' => 'متاح',
    ]);
    $article = Article::factory()->create([
        'cta_target_type' => Properties::class,
        'cta_target_id' => $unit->id,
    ]);

    $this->get(route('article', $article))
        ->assertOk()
        ->assertSee(route('properties', $unit), false);
});

it('points a button at another article', function () {
    $destination = Article::factory()->create(['title' => 'دليل شراء شقة']);
    $article = Article::factory()->create([
        'cta_target_type' => Article::class,
        'cta_target_id' => $destination->id,
    ]);

    $this->get(route('article', $article))
        ->assertOk()
        ->assertSee(route('article', $destination), false);
});

it('drops the button when its destination was deleted, rather than linking to a 404', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create([
        'cta_label' => 'تصفّح وحدات المشروع',
        'cta_target_type' => Project::class,
        'cta_target_id' => $project->id,
    ]);

    $url = route('project', $project);
    $project->delete();

    $this->get(route('article', $article))
        ->assertOk()
        ->assertDontSee('تصفّح وحدات المشروع', false)
        ->assertDontSee($url, false);
});

it('shows no button on an article that carries none', function () {
    $article = Article::factory()->create();

    expect($article->hasCta())->toBeFalse();

    $this->get(route('article', $article))->assertOk();
});

it('points an article button at a collection page', function () {
    $page = CollectionPage::factory()->create(['title' => 'شقق جاهزة للتسليم']);
    $article = Article::factory()->create([
        'cta_target_type' => CollectionPage::class,
        'cta_target_id' => $page->id,
    ]);

    $this->get(route('article', $article))
        ->assertOk()
        ->assertSee(route('collection', $page), false)
        ->assertSee('شقق جاهزة للتسليم', false);
});
