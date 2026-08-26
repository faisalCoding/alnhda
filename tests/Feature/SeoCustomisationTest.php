<?php

use App\Models\Admin;
use App\Models\AppSettings;
use App\Models\Article;
use App\Models\Project;
use App\Models\SeoMeta;
use App\Models\SeoPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

// ── ما يخرج للزائر ───────────────────────────────────────────────────────────

it('prefers a saved page override over the text the page writes for itself', function () {
    SeoPage::factory()->create([
        'route_name' => 'projects',
        'title' => 'عنوان من اللوحة',
        'description' => 'وصف من اللوحة',
    ]);

    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->toContain('عنوان من اللوحة')
        ->and($html)->toContain('وصف من اللوحة')
        ->and($html)->not->toContain('مشاريعنا العقارية - فلل وشقق للبيع في جدة');
});

it('falls back to the page text when no override is saved', function () {
    $html = $this->get(route('projects'))->assertOk()->getContent();

    expect($html)->toContain('مشاريعنا العقارية - فلل وشقق للبيع في جدة');
});

it('prefers a record override over the text derived from the record', function () {
    $project = Project::factory()->create(['name' => 'مشروع الاختبار']);
    $project->seoMeta()->save(new SeoMeta(['title' => 'عنوان خاص بالمشروع', 'description' => 'وصف خاص بالمشروع']));

    $html = $this->get(route('project', $project))->assertOk()->getContent();

    expect($html)->toContain('عنوان خاص بالمشروع')
        ->and($html)->toContain('وصف خاص بالمشروع')
        ->and($html)->not->toContain('مشروع الاختبار - مشروع سكني في جدة');
});

it('lets a half-filled override keep the automatic half', function () {
    $article = Article::factory()->create(['title' => 'عنوان المقال الأصلي']);
    $article->seoMeta()->save(new SeoMeta(['description' => 'وصف مكتوب بعناية']));

    $html = $this->get(route('article', $article))->assertOk()->getContent();

    expect($html)->toContain('عنوان المقال الأصلي')
        ->and($html)->toContain('وصف مكتوب بعناية');
});

it('uses the site default only when neither the record nor the page says anything', function () {
    AppSettings::current()->update(['seo_default_description' => 'الوصف الافتراضي العام']);

    // للمشاريع وصف مشتقّ من نصّها، فالافتراضي لا يظهر فيها.
    expect($this->get(route('projects'))->getContent())->not->toContain('الوصف الافتراضي العام');
});

it('marks a page noindex when the panel says so', function () {
    SeoPage::factory()->hidden()->create(['route_name' => 'terms-of-use']);

    expect($this->get(route('terms-of-use'))->getContent())
        ->toContain('<meta name="robots" content="noindex, nofollow">');
});

it('leaves indexable pages without a robots tag', function () {
    expect($this->get(route('projects'))->getContent())
        ->not->toContain('noindex, nofollow');
});

it('publishes the share image dimensions so previews render as a wide banner', function () {
    $html = $this->get(route('welcome'))->assertOk()->getContent();

    expect($html)->toContain('<meta property="og:image:width"')
        ->and($html)->toContain('<meta property="og:image:height"')
        ->and($html)->toContain('<meta property="og:image:secure_url"');
});

// ── واجهة اللوحة ─────────────────────────────────────────────────────────────

it('keeps guests out of the seo endpoints', function () {
    $this->getJson(panelUrl('/api/seo'))->assertUnauthorized();
    $this->putJson(panelUrl('/api/seo/defaults'), [])->assertUnauthorized();
    $this->putJson(panelUrl('/api/seo/pages/projects'), [])->assertUnauthorized();
});

it('lists every editable page with what it currently says', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/seo'))
        ->assertSuccessful();

    $pages = collect($response->json('data.pages'));

    expect($pages)->toHaveCount(7)
        ->and($pages->pluck('route_name'))->toContain('welcome', 'projects', 'terms-of-use')
        ->and($pages->firstWhere('route_name', 'projects')['auto']['title'])
        ->toBe('مشاريعنا العقارية - فلل وشقق للبيع في جدة');
});

it('saves a page override and reports it back', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/seo/pages/articles'), [
            'title' => 'عنوان جديد للمقالات',
            'description' => 'وصف جديد',
            'noindex' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'عنوان جديد للمقالات');

    expect(SeoPage::query()->where('route_name', 'articles')->value('title'))->toBe('عنوان جديد للمقالات');
});

it('refuses a page that is not on the editable list', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/seo/pages/dashboard'), ['title' => 'محاولة'])
        ->assertNotFound();
});

it('rejects an og type it would only pass on unread', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/seo/pages/articles'), ['og_type' => 'شيء غير معروف'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('og_type');
});

it('saves a record override', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl("/api/seo/records/project/{$project->id}"), ['title' => 'عنوان مخصّص'])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'عنوان مخصّص');

    expect($project->fresh()->seoMeta->title)->toBe('عنوان مخصّص');
});

it('drops the override when the form is emptied instead of storing blanks', function () {
    $project = Project::factory()->create();
    $project->seoMeta()->save(new SeoMeta(['title' => 'عنوان سيُمحى']));

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl("/api/seo/records/project/{$project->id}"), [
            'title' => null,
            'description' => null,
            'image_path' => null,
            'noindex' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', null);

    expect(SeoMeta::query()->count())->toBe(0)
        ->and($project->fresh()->seoMeta)->toBeNull();
});

it('searches records by name', function () {
    Project::factory()->create(['name' => 'مشروع الياسمين']);
    Project::factory()->create(['name' => 'مشروع النرجس']);

    $found = $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/seo/records?type=project&search=').urlencode('الياسمين'))
        ->assertSuccessful()
        ->json('data');

    expect($found)->toHaveCount(1)
        ->and($found[0]['name'])->toBe('مشروع الياسمين')
        ->and($found[0]['auto']['title'])->toBe('مشروع الياسمين - مشروع سكني في جدة');
});

it('refuses an unknown record type', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/seo/records?type=admins'))
        ->assertNotFound();
});

it('crops an uploaded share image to the ratio previews need', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/seo/image'), [
            'image' => UploadedFile::fake()->image('share.jpg', 900, 900),
        ])
        ->assertCreated();

    $path = $response->json('data.image_path');

    expect($path)->toEndWith('.jpg')
        ->and($response->json('data.width'))->toBe(1200)
        ->and($response->json('data.height'))->toBe(630);

    Storage::disk('public')->assertExists($path);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

    expect([$width, $height])->toBe([1200, 630]);
});

it('refuses a file that is not an image', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/seo/image'), ['image' => UploadedFile::fake()->create('notes.pdf', 10)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

it('renders the panel page with its component mounted', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(panelUrl('/seo'))
        ->assertOk()
        ->assertSee('seoPage()', false)
        ->assertSee('نتيجة البحث في جوجل')
        ->assertSee('المشاركة في واتساب');
});

it('turns guests away from the panel page', function () {
    $this->get(panelUrl('/seo'))->assertRedirect();
});
