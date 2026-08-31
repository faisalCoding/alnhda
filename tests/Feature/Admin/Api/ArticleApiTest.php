<?php

use App\Models\Admin;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

it('rejects guests from the articles api', function () {
    $this->getJson(panelUrl('/api/articles'))->assertUnauthorized();
});

it('lists articles', function () {
    Article::factory()->count(3)->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/articles'))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates an article with the default cover image', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), [
            'title' => 'مقال جديد عن العقارات',
            'content' => '<p>محتوى المقال</p>',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'مقال جديد عن العقارات')
        ->assertJsonPath('data.image_article', '/img/article.jpg');
});

it('requires a title to create an article', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), ['content' => '<p>بدون عنوان</p>'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

it('updates an article', function () {
    $article = Article::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/articles/'.$article->id), [
            'title' => 'عنوان محدث',
            'content' => '<p>محتوى محدث</p>',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'عنوان محدث');
});

it('creates an article with a button pointing at a project', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), [
            'title' => 'مقال بزر',
            'content' => '<p>محتوى</p>',
            'cta_label' => 'تصفّح المشروع',
            'cta_target_type' => 'project',
            'cta_target_id' => $project->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.cta_label', 'تصفّح المشروع')
        ->assertJsonPath('data.cta_target_type', 'project')
        ->assertJsonPath('data.cta_target_id', $project->id)
        ->assertJsonPath('data.cta_url', route('project', $project));

    expect(Article::query()->first()->ctaTarget->is($project))->toBeTrue();
});

it('clears the button when an update arrives without a destination', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create([
        'cta_label' => 'تصفّح المشروع',
        'cta_target_type' => Project::class,
        'cta_target_id' => $project->id,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/articles/'.$article->id), [
            'title' => $article->title,
            'content' => $article->content,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.cta_target_type', null)
        ->assertJsonPath('data.cta_label', null);

    expect($article->refresh()->hasCta())->toBeFalse();
});

it('rejects a destination of an unknown kind', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), [
            'title' => 'مقال',
            'cta_target_type' => 'employee',
            'cta_target_id' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cta_target_type');
});

it('rejects a destination that does not exist', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), [
            'title' => 'مقال',
            'cta_target_type' => 'project',
            'cta_target_id' => 999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cta_target_id');
});

it('rejects a destination sent without its kind, and a kind sent without a destination', function (array $payload, string $field) {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), ['title' => 'مقال', ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'id alone' => [['cta_target_id' => 1], 'cta_target_type'],
    'kind alone' => [['cta_target_type' => 'project'], 'cta_target_id'],
]);

it('rejects an article pointing its button at itself', function () {
    $article = Article::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/articles/'.$article->id), [
            'title' => $article->title,
            'cta_target_type' => 'article',
            'cta_target_id' => $article->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cta_target_id');
});

it('rejects button wording longer than the button can show', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), [
            'title' => 'مقال',
            'cta_label' => str_repeat('ا', 61),
            'cta_target_type' => 'project',
            'cta_target_id' => $project->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cta_label');
});

it('deletes an article', function () {
    $article = Article::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/articles/'.$article->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true);

    expect(Article::query()->count())->toBe(0);
});

it('does not duplicate an article when the same idempotency key is replayed', function () {
    $headers = ['Idempotency-Key' => 'op-33333333-3333-3333-3333-333333333333'];
    $payload = ['title' => 'مقال مزامن', 'content' => null];

    $first = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), $payload, $headers);
    $second = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), $payload, $headers);

    $first->assertCreated();
    $second->assertHeader('Idempotency-Replayed', 'true');

    expect(Article::query()->count())->toBe(1);
});

it('uploads an article image and deletes the previous stored one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('articles/old.webp', 'old');
    $article = Article::factory()->create(['image_article' => 'articles/old.webp']);

    $response = $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/articles/'.$article->id.'/image'), [
            'image' => UploadedFile::fake()->image('cover.jpg', 800, 500),
        ], ['Accept' => 'application/json']);

    $response->assertSuccessful();
    Storage::disk('public')->assertExists($response->json('data.image_article'));
    Storage::disk('public')->assertMissing('articles/old.webp');
});

it('keeps the default cover file untouched when replacing it', function () {
    Storage::fake('public');
    $article = Article::factory()->create(['image_article' => '/img/article.jpg']);

    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/articles/'.$article->id.'/image'), [
            'image' => UploadedFile::fake()->image('cover.jpg', 800, 500),
        ], ['Accept' => 'application/json'])
        ->assertSuccessful();

    expect($article->refresh()->image_article)->not->toBe('/img/article.jpg');
});

it('accepts a collection page as a button destination', function () {
    $page = App\Models\CollectionPage::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), [
            'title' => 'مقال',
            'cta_target_type' => 'collection',
            'cta_target_id' => $page->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.cta_target_type', 'collection')
        ->assertJsonPath('data.cta_url', route('collection', $page));
});
