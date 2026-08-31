<?php

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('shows the photograph instead of hiding it behind a colour wash', function () {
    Article::factory()->create(['image_article' => 'blogs/cover.webp']);

    $html = $this->get(route('welcome'))->assertOk()->getContent();
    $card = Str::before(Str::after($html, 'id="articles-heading"'), '</section>');

    expect($card)
        ->toContain(asset('storage/blogs/cover.webp'))
        ->not->toContain('grayscale')
        ->and($card)->not->toContain('blur-sm');
});

it('gives the reader something to judge the article by', function () {
    Article::factory()->create([
        'title' => 'كيف تختار واجهة المبنى',
        'content' => '<p>'.str_repeat('كلمة ', 400).'</p>',
    ]);

    $html = $this->get(route('welcome'))->assertOk()->getContent();

    expect($html)
        ->toContain('كيف تختار واجهة المبنى')
        ->toContain('دقائق قراءة')
        ->toContain('اقرأ المقال');
});

it('writes the date in arabic rather than as digits', function () {
    $article = Article::factory()->create(['created_at' => '2026-03-14 10:00:00']);

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee($article->created_at->translatedFormat('d F Y'), false)
        ->assertDontSee('2026-03-14', false);
});

it('teases six articles at most, and points at the rest', function () {
    Article::factory()->count(9)->create();

    $html = $this->get(route('welcome'))->assertOk()->getContent();
    $section = Str::before(Str::after($html, 'id="articles-heading"'), '</section>');

    expect(substr_count($section, 'اقرأ المقال'))->toBe(6)
        ->and($section)->toContain(route('articles'));
});

it('leaves the section out entirely when nothing is published', function () {
    expect($this->get(route('welcome'))->assertOk()->getContent())
        ->not->toContain('اكتشف احدث المقالات');
});

it('lists every article on the articles page, newest first', function () {
    $old = Article::factory()->create(['title' => 'مقال قديم', 'created_at' => now()->subYear()]);
    $new = Article::factory()->create(['title' => 'مقال جديد', 'created_at' => now()]);

    $html = $this->get(route('articles'))->assertOk()->getContent();

    expect(strpos($html, 'مقال جديد'))->toBeLessThan(strpos($html, 'مقال قديم'));
});

// ---- reading time --------------------------------------------------------

it('counts arabic words the way a reader would', function (int $words, string $expected) {
    $article = Article::factory()->create(['content' => '<p>'.str_repeat('كلمة ', $words).'</p>']);

    expect($article->readingTimeLabel())->toBe($expected);
})->with([
    'one minute' => [100, 'دقيقة قراءة'],
    'two minutes' => [300, 'دقيقتان للقراءة'],
    'a few minutes' => [700, '4 دقائق قراءة'],
    'many minutes' => [2200, '13 دقيقة قراءة'],
]);

it('never claims an article takes no time to read', function () {
    expect(Article::factory()->create(['content' => ''])->readingTimeLabel())->toBe('دقيقة قراءة');
});

it('strips the markup out of the excerpt without gluing words together', function () {
    $article = Article::factory()->create([
        'content' => '<h2>عنوان</h2><p>نص   المقال</p><script>alert(1)</script>',
    ]);

    expect($article->excerpt())->toBe('عنوان نص المقال')
        ->and($article->excerpt())->not->toContain('<');
});

it('does not count script text as words the reader has to read', function () {
    $prose = '<p>'.str_repeat('كلمة ', 100).'</p>';
    $withScript = $prose.'<script>'.str_repeat('var x = 1; ', 400).'</script>';

    expect(Article::factory()->create(['content' => $withScript])->readingMinutes())
        ->toBe(Article::factory()->create(['content' => $prose])->readingMinutes());
});

it('loads the first card on the articles page at once and the rest on demand', function () {
    Article::factory()->count(3)->create();

    $html = $this->get(route('articles'))->assertOk()->getContent();

    // Counted across the page: only a card ever asks to be loaded eagerly here.
    expect(substr_count($html, 'fetchpriority="high"'))->toBe(1)
        ->and(substr_count($html, 'loading="eager"'))->toBe(1);
});

it('defers every card on the home page, where the section is far below the fold', function () {
    Article::factory()->count(3)->create();

    $html = $this->get(route('welcome'))->assertOk()->getContent();
    $section = Str::before(Str::after($html, 'id="articles-heading"'), '</section>');

    expect($section)->not->toContain('fetchpriority="high"');
});
