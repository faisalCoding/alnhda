<?php

use App\Models\Project;
use App\Models\Properties;
use App\Services\HomeFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function homeHtml(): string
{
    return test()->get(route('welcome'))->assertOk()->getContent();
}

// ---- the heading ---------------------------------------------------------

it('leads with what the company does and where, not with the slogan', function () {
    $html = homeHtml();
    $h1 = Str::between($html, '<h1', '</h1>');

    expect($h1)->toContain('جدة')
        ->and($h1)->not->toContain('أساسات راسخة');
});

it('keeps the slogan on the page, just out of the heading', function () {
    expect(homeHtml())->toContain('أساسات راسخة');
});

it('carries exactly one h1', function () {
    expect(substr_count(homeHtml(), '<h1'))->toBe(1);
});

// ---- the guarantees ------------------------------------------------------

it('shows the guarantees its projects actually carry', function () {
    Project::factory()->create(['guarantees' => ['ضمان ٢٠ سنة على الهيكل الخرساني']]);

    expect(homeHtml())->toContain('ضمان ٢٠ سنة على الهيكل الخرساني');
});

it('states each guarantee once however many projects repeat it', function () {
    Project::factory()->count(3)->create(['guarantees' => ['ضمان سنتين على التمديدات']]);

    expect(app(HomeFacts::class)->guarantees())->toBe(['ضمان سنتين على التمديدات']);
});

it('leaves the guarantees section out entirely when no project carries one', function () {
    Project::factory()->create(['guarantees' => null]);

    expect(homeHtml())->not->toContain('ما نضمنه لك مكتوبًا');
});

// ---- the questions -------------------------------------------------------

it('answers the questions a buyer asks, in the page itself', function () {
    Project::factory()->create(['guarantees' => ['ضمان ٢٠ سنة'], 'location' => 'جدة مخطط التيسير']);

    $html = homeHtml();

    expect($html)
        ->toContain('ما الضمانات التي تقدمونها على الوحدة؟')
        ->toContain('أين تقع مشاريع كيان النهضة العقارية؟')
        ->toContain('جدة مخطط التيسير')
        ->toContain('هل كيان النهضة العقارية شركة مرخّصة رسميًا؟')
        ->toContain(HomeFacts::FAL_LICENCE);
});

it('publishes the same questions as a FAQPage a crawler can read', function () {
    Project::factory()->create(['guarantees' => ['ضمان ٢٠ سنة']]);

    $entries = app(HomeFacts::class)->faq();
    $html = homeHtml();

    expect($html)->toContain('"@type":"FAQPage"');

    foreach ($entries as $entry) {
        expect($html)->toContain(json_encode($entry['question'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
});

it('never publishes a question whose answer it does not have', function () {
    $entries = app(HomeFacts::class)->faq();

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect(trim($entry['answer']))->not->toBeEmpty()
            ->and(mb_strlen($entry['answer']))->toBeGreaterThan(40);
    }
});

// ---- the facts behind them -----------------------------------------------

it('reads the districts and unit kinds from the records themselves', function () {
    $project = Project::factory()->create(['location' => 'جدة حي الشاطئ']);
    Properties::create([
        'name' => 'شقة رقم 3',
        'project_id' => $project->id,
        'status' => 'متاح',
        'type' => 'شقة',
    ]);

    $facts = app(HomeFacts::class);

    expect($facts->districts())->toContain('جدة حي الشاطئ')
        ->and($facts->unitKinds())->toContain('شقق');
});

it('strips the invisible marks a pasted field brings with it', function () {
    Project::factory()->create(['location' => "\u{200F}جدة مخطط التيسير "]);

    expect(app(HomeFacts::class)->districts())->toBe(['جدة مخطط التيسير']);
});

it('joins several facts into a sentence rather than a comma list', function () {
    Project::factory()->create(['guarantees' => ['ضمان الهيكل', 'ضمان التمديدات']]);

    $answer = collect(app(HomeFacts::class)->faq())->firstWhere('question', 'ما الضمانات التي تقدمونها على الوحدة؟');

    expect($answer['answer'])->toContain('ضمان الهيكل وضمان التمديدات');
});

// ---- the districts -------------------------------------------------------

it('names the districts it builds in, which is how a buyer searches', function () {
    Project::factory()->create(['location' => 'جدة مخطط التيسير', 'name' => 'مشروع التيسير 12']);

    $html = homeHtml();

    expect($html)
        ->toContain('أين نبني في جدة')
        ->toContain('جدة مخطط التيسير')
        ->toContain('مشروع التيسير 12');
});

it('gathers the projects of one district under it once', function () {
    Project::factory()->count(2)->create(['location' => 'جدة مخطط التيسير']);

    // Str::between() runs to the LAST close tag on the page, which would sweep
    // in every later section; this stops at the districts section's own.
    $section = Str::before(Str::after(homeHtml(), 'id="districts-heading"'), '</section>');

    expect(substr_count($section, 'جدة مخطط التيسير'))->toBe(1);
});

it('leaves a project with no district out rather than heading a blank card', function () {
    Project::factory()->create(['location' => null, 'name' => 'مشروع بلا موقع']);

    $districts = app(HomeFacts::class)->projectsByDistrict();

    expect($districts)->toBeEmpty();
});

// ---- what a crawler is handed --------------------------------------------

it('publishes its projects as an item list', function () {
    $project = Project::factory()->create();

    expect(homeHtml())
        ->toContain('"@type":"ItemList"')
        ->toContain(json_encode(route('project', $project), JSON_UNESCAPED_SLASHES));
});

it('publishes no empty item list when there are no projects yet', function () {
    expect(homeHtml())->not->toContain('"@type":"ItemList"');
});

it('gives the office a coordinate a map can place', function () {
    $organization = app(\App\Services\SiteSchema::class)->organization();

    expect($organization['geo'])->toBe([
        '@type' => 'GeoCoordinates',
        'latitude' => 21.5660262,
        'longitude' => 39.2480873,
    ])->and($organization['hasMap'])->toBe(\App\Livewire\HeaderNavBar::OFFICE_MAP_URL);
});

// ---- the links -----------------------------------------------------------

it('says where a link goes instead of "read more"', function () {
    $html = homeHtml();

    expect($html)
        ->not->toContain('تعرف على المزيد')
        ->toContain('تعرّف على شركة كيان النهضة العقارية');
});

// ---- the plain-text map --------------------------------------------------

it('repeats the guarantees and the answers in llms.txt', function () {
    Project::factory()->create(['guarantees' => ['ضمان ٢٠ سنة على الهيكل الخرساني']]);

    $this->get(route('llms'))
        ->assertOk()
        ->assertSee('ضمان ٢٠ سنة على الهيكل الخرساني', false)
        ->assertSee('## أسئلة وأجوبة', false)
        ->assertSee('هل كيان النهضة العقارية شركة مرخّصة رسميًا؟', false);
});

// ---- the hero ------------------------------------------------------------

it('carries the site navigation, which the home page used to hide', function () {
    $html = homeHtml();

    expect($html)
        ->toContain(route('projects'))
        ->toContain(route('contact-us'))
        ->toContain(route('about-us'))
        ->toContain('مشاريعنا');
});

it('shows one of the company projects behind the heading, not stock artwork', function () {
    Project::factory()->create(['image_url' => 'uploads/villa.webp', 'name' => 'مشروع الواجهة']);

    $html = homeHtml();

    expect($html)
        ->toContain(asset('storage/uploads/villa.webp'))
        ->toContain('مشروع الواجهة — مشروع سكني لشركة كيان النهضة العقارية');
});

it('falls back to the house photograph when no project carries an image', function () {
    Project::factory()->create(['image_url' => null]);

    expect(homeHtml())->toContain(asset('img/homebg.webp'));
});

it('offers a way forward from the first screen', function () {
    $hero = Str::before(Str::after(homeHtml(), '</header>'), '</section>');

    expect($hero)
        ->toContain('تصفّح مشاريعنا في جدة')
        ->toContain('تواصل معنا');
});

it('no longer carries the property-type box that used to sit under the buttons', function () {
    expect(homeHtml())
        ->not->toContain('عن ماذا تبحث؟')
        ->and(homeHtml())->not->toContain('header-property-request');
});

it('puts the licence and the guarantee in the first screen', function () {
    Project::factory()->create(['guarantees' => ['ضمان ٢٠ سنة على الهيكل الخرساني']]);

    $hero = Str::before(Str::after(homeHtml(), '</header>'), '</section>');

    expect($hero)
        ->toContain(HomeFacts::FAL_LICENCE)
        ->toContain(HomeFacts::UNIFIED_NUMBER)
        ->toContain('ضمان ٢٠ سنة على الهيكل الخرساني');
});

it('stops repeating the logo in the hero now that the navigation carries it', function () {
    // The nav sits before the hero and holds the logo twice — once for the bar,
    // once inside the mobile drawer — so the hero is measured from after it.
    $hero = Str::before(Str::after(homeHtml(), '</header>'), '</section>');

    expect($hero)->toContain('تطوير وبيع فلل وشقق سكنية في جدة')
        ->and($hero)->not->toContain('alnhda-logo.webp');
});

// ---- the headline face ---------------------------------------------------

it('sets the heading in the self-hosted display face', function () {
    $hero = Str::before(Str::after(homeHtml(), '</header>'), '</section>');

    expect($hero)->toMatch('/<h1[^>]*class="[^"]*font-display/');
});

it('serves the display face from this domain, not a font cdn', function () {
    $html = homeHtml();

    expect($html)
        ->toContain("font-family: 'Changa'")
        ->toContain("url('/fonts/changa/changa-200-arabic.woff2')")
        ->and($html)->not->toContain('fonts.gstatic.com/s/changa');

    foreach (['arabic', 'latin', 'latin-ext'] as $subset) {
        expect(public_path("fonts/changa/changa-200-{$subset}.woff2"))->toBeReadableFile();
    }

    expect(public_path('fonts/changa/OFL.txt'))->toBeReadableFile();
});

it('fetches the headline face at once instead of after the stylesheet', function () {
    expect(homeHtml())->toContain('rel="preload" href="/fonts/changa/changa-200-arabic.woff2"');
});

it('preloads the picture the hero actually shows', function () {
    Project::factory()->create(['image_url' => 'uploads/villa.webp']);

    expect(homeHtml())->toContain('rel="preload" href="'.asset('storage/uploads/villa.webp').'"')
        ->and(homeHtml())->not->toContain('rel="preload" href="/img/homebg.webp"');
});
