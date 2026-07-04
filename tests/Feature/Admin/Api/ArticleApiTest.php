<?php

use App\Jobs\PingGoogleSitemap;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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

it('creates an article with the default cover image and pings the sitemap', function () {
    Queue::fake();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/articles'), [
            'title' => 'مقال جديد عن العقارات',
            'content' => '<p>محتوى المقال</p>',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'مقال جديد عن العقارات')
        ->assertJsonPath('data.image_article', '/img/article.jpg');

    Queue::assertPushed(PingGoogleSitemap::class);
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

it('deletes an article', function () {
    $article = Article::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/articles/'.$article->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true);

    expect(Article::query()->count())->toBe(0);
});

it('does not duplicate an article when the same idempotency key is replayed', function () {
    Queue::fake();
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
