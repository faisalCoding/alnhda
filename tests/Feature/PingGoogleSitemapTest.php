<?php

declare(strict_types=1);

use App\Jobs\PingGoogleSitemap;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('dispatches PingGoogleSitemap job when an article is created', function (): void {
    Queue::fake();

    Article::create([
        'title' => 'مقال تجريبي',
        'content' => 'محتوى تجريبي',
    ]);

    Queue::assertPushed(PingGoogleSitemap::class);
});

it('pings google with the sitemap url', function (): void {
    Http::fake([
        'www.google.com/ping*' => Http::response('', 200),
    ]);

    (new PingGoogleSitemap)->handle();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'www.google.com/ping')
            && str_contains($request->url(), 'sitemap');
    });
});

it('does not throw when google ping fails', function (): void {
    Http::fake([
        'www.google.com/ping*' => Http::response('', 500),
    ]);

    expect(fn () => (new PingGoogleSitemap)->handle())->not->toThrow(\Throwable::class);
});
