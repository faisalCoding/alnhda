<?php

use App\Models\Admin;
use App\Models\AppSettings;
use App\Models\FaqEntry;
use App\Models\Project;
use App\Services\HomeFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

it('keeps the home content api away from guests', function () {
    $this->getJson(panelUrl('/api/home-content'))->assertUnauthorized();
});

// ---- the hero ------------------------------------------------------------

it('hands the screen what each field falls back to, not just what is stored', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/home-content'))
        ->assertSuccessful()
        ->assertJsonPath('data.hero.hero_title', null)
        ->assertJsonPath('data.hero_defaults.title', HomeFacts::HERO_DEFAULTS['title']);
});

it('puts written hero text on the page', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['hero_title' => 'فلل للبيع في حي الشاطئ بجدة'])
        ->assertSuccessful();

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('فلل للبيع في حي الشاطئ بجدة', false)
        ->assertDontSee(HomeFacts::HERO_DEFAULTS['title'], false);
});

it('returns to the built-in text when a field is emptied again', function () {
    AppSettings::current()->update(['hero_title' => 'عنوان مؤقت']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['hero_title' => null])
        ->assertSuccessful();

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee(HomeFacts::HERO_DEFAULTS['title'], false);
});

it('refuses a heading too long for the page to show', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['hero_title' => str_repeat('ا', 121)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hero_title');
});

// ---- the guarantees ------------------------------------------------------

it('lets a written guarantee list take over from the projects', function () {
    Project::factory()->create(['guarantees' => ['ضمان من المشروع']]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['home_guarantees' => ['ضمان مكتوب في اللوحة']])
        ->assertSuccessful();

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('ضمان مكتوب في اللوحة', false)
        ->assertDontSee('ضمان من المشروع', false);
});

it('goes back to the project guarantees when the written list is emptied', function () {
    Project::factory()->create(['guarantees' => ['ضمان من المشروع']]);
    AppSettings::current()->update(['home_guarantees' => ['ضمان مؤقت']]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['home_guarantees' => []])
        ->assertSuccessful();

    expect(AppSettings::current()->refresh()->home_guarantees)->toBeNull();

    $this->get(route('welcome'))->assertOk()->assertSee('ضمان من المشروع', false);
});

it('drops blank rows from the guarantee list instead of printing them', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['home_guarantees' => ['ضمان حقيقي', '', '   ']])
        ->assertSuccessful()
        ->assertJsonPath('data.home_guarantees', ['ضمان حقيقي']);
});

// ---- the questions -------------------------------------------------------

it('creates, edits and deletes a question', function () {
    $created = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/faq'), [
            'question' => 'هل تقبلون التمويل العقاري؟',
            'answer' => 'نعم، تتعامل الشركة مع البنوك المعتمدة في المملكة وتساعد المشتري في إجراءات التمويل.',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->get(route('welcome'))->assertOk()->assertSee('هل تقبلون التمويل العقاري؟', false);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content/faq/'.$created), [
            'question' => 'هل يوجد تمويل عقاري؟',
            'answer' => 'نعم، تتعامل الشركة مع البنوك المعتمدة في المملكة وتساعد المشتري في إجراءات التمويل.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.question', 'هل يوجد تمويل عقاري؟');

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/home-content/faq/'.$created))
        ->assertSuccessful();

    expect(FaqEntry::query()->count())->toBe(0);
});

it('refuses a stub answer, which is worse published than absent', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/faq'), ['question' => 'هل عندكم تمويل؟', 'answer' => 'نعم'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('answer');
});

it('shows the written questions in place of the ones the site writes for itself', function () {
    FaqEntry::factory()->create(['question' => 'سؤال من اللوحة']);

    $html = $this->get(route('welcome'))->assertOk()->getContent();

    expect($html)->toContain('سؤال من اللوحة')
        ->and($html)->not->toContain('هل كيان النهضة العقارية شركة مرخّصة رسميًا؟');
});

it('keeps the written questions in the order they were arranged', function () {
    $first = FaqEntry::factory()->create(['question' => 'السؤال الأول', 'sort_order' => 0]);
    $second = FaqEntry::factory()->create(['question' => 'السؤال الثاني', 'sort_order' => 1]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/faq/reorder'), ['ids' => [$second->id, $first->id]])
        ->assertSuccessful();

    $html = $this->get(route('welcome'))->assertOk()->getContent();

    expect(strpos($html, 'السؤال الثاني'))->toBeLessThan(strpos($html, 'السؤال الأول'));
});

it('imports the answers the site writes for itself so they can be edited', function () {
    Project::factory()->create(['guarantees' => ['ضمان ٢٠ سنة'], 'location' => 'جدة مخطط التيسير']);

    $expected = app(HomeFacts::class)->derivedFaq();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/faq/import'))
        ->assertSuccessful()
        ->assertJsonCount(count($expected), 'data.faq');

    expect(FaqEntry::query()->ordered()->first()->question)->toBe($expected[0]['question']);
});

it('refuses a second import rather than doubling the list', function () {
    FaqEntry::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/faq/import'))
        ->assertUnprocessable();

    expect(FaqEntry::query()->count())->toBe(1);
});

// ---- the screen ----------------------------------------------------------

it('serves the home content screen to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get(panelUrl('/home-content'))
        ->assertOk()
        ->assertSee('نص الجزء العلوي')
        ->assertSee('homeContentPage()', false);
});

it('keeps the home content screen away from guests', function () {
    $this->withoutVite()->get(panelUrl('/home-content'))->assertRedirect(route('admin.login'));
});

// ---- the hero background -------------------------------------------------

it('puts an uploaded picture behind the heading', function () {
    Storage::fake('public');
    Project::factory()->create(['image_url' => 'uploads/project.webp']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/hero-image'), [
            'image' => UploadedFile::fake()->image('hero.jpg', 2400, 1200),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.hero_image_is_uploaded', true);

    $path = AppSettings::current()->refresh()->hero_image_path;

    expect($path)->toStartWith('hero/')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee(asset('storage/'.$path), false)
        ->assertDontSee(asset('storage/uploads/project.webp'), false);
});

it('deletes the picture it replaces rather than piling uploads up', function () {
    Storage::fake('public');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/hero-image'), ['image' => UploadedFile::fake()->image('one.jpg', 1600, 900)]);

    $first = AppSettings::current()->refresh()->hero_image_path;

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/hero-image'), ['image' => UploadedFile::fake()->image('two.jpg', 1600, 900)]);

    expect(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists(AppSettings::current()->refresh()->hero_image_path))->toBeTrue();
});

it('goes back to the project cover when the upload is removed', function () {
    Storage::fake('public');
    Project::factory()->create(['image_url' => 'uploads/project.webp']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/hero-image'), ['image' => UploadedFile::fake()->image('hero.jpg', 1600, 900)]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/home-content/hero-image'))
        ->assertSuccessful()
        ->assertJsonPath('data.hero_image_is_uploaded', false);

    $this->get(route('welcome'))->assertOk()->assertSee(asset('storage/uploads/project.webp'), false);
});

it('refuses a file that is not an image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/home-content/hero-image'), ['image' => UploadedFile::fake()->create('plans.pdf', 100)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

// ---- hiding sections -----------------------------------------------------

it('hides a section from the page when it is switched off', function () {
    Project::factory()->create(['guarantees' => ['ضمان ٢٠ سنة على الهيكل']]);

    $this->get(route('welcome'))->assertOk()->assertSee('ما نضمنه لك مكتوبًا', false);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['hidden_home_sections' => ['guarantees']])
        ->assertSuccessful();

    $this->get(route('welcome'))
        ->assertOk()
        ->assertDontSee('ما نضمنه لك مكتوبًا', false)
        ->assertSee('أين نبني في جدة', false);
});

it('takes the structured data down with the section it describes', function () {
    Project::factory()->create();

    $this->get(route('welcome'))->assertOk()->assertSee('"@type":"FAQPage"', false);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['hidden_home_sections' => ['faq', 'projects']])
        ->assertSuccessful();

    $this->get(route('welcome'))
        ->assertOk()
        ->assertDontSee('"@type":"FAQPage"', false)
        ->assertDontSee('"@type":"ItemList"', false);
});

it('refuses to hide a section it does not have', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['hidden_home_sections' => ['footer']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hidden_home_sections.0');
});

it('treats an empty list as nothing hidden', function () {
    AppSettings::current()->update(['hidden_home_sections' => ['faq']]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/home-content'), ['hidden_home_sections' => []])
        ->assertSuccessful();

    expect(AppSettings::current()->refresh()->hidden_home_sections)->toBeNull();
});
