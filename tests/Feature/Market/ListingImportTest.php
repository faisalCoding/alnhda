<?php

use App\Exceptions\UnreadableSalesFile;
use App\Models\Admin;
use App\Models\ManualMarketUnit;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Models\Project;
use App\Models\Properties;
use App\Services\Market\ListingDuplicates;
use App\Services\Market\ListingFileImporter;
use Illuminate\Http\UploadedFile;

const LISTINGS_HEADER = 'source_site,listing_id,url,neighborhood,city,title,price_sar,area_m2,rooms,bedrooms,bathrooms,street_width_m,facade,posted_at,updated_at,scraped_at,possible_cross_site_duplicate,property_age,property_age_years';

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

/**
 * @param  list<string>  $rows
 */
function listingsFile(array $rows, string $header = LISTINGS_HEADER): string
{
    $path = tempnam(sys_get_temp_dir(), 'listings').'.csv';
    file_put_contents($path, "\u{FEFF}".$header."\n".implode("\n", $rows)."\n");

    return $path;
}

/**
 * One listing row the way a listings file writes it.
 */
function listingRow(string $site, string $id, string $price = '3000000', string $area = '600', string $duplicates = '', string $title = 'فيلا للبيع في حي المنار', string $district = 'حي المنار', string $extra = '7,7,5,15,شمالية'): string
{
    return implode(',', [
        $site, $id, "https://example.com/{$site}/{$id}", $district, 'جدة', '"'.$title.'"', $price, $area, $extra,
        '2026-10-28T12:27:58+03:00', '2026-11-06T14:37:11+03:00', '2026-11-12T19:08:20+03:00', $duplicates, 'أكثر من 10 سنوات', 'null',
    ]);
}

it('reads every column a listings file has into a unit', function () {
    $report = app(ListingFileImporter::class)->import(listingsFile([
        'aqar,101,https://example.com/a/101,حي المنار,جدة,"فيلا للبيع في شارع المنار, حي المنار",3600000,648,10+,10+,,25 / 12,North west,2026-10-28T23:27:58+03:00,2026-11-06,2026-11-12T19:08:20.353541+03:00,,8 سنوات,8',
    ]));

    expect($report)->toMatchArray(['added' => 1, 'updated' => 0, 'skipped' => [], 'properties' => 1, 'repeated' => 0]);

    $unit = ManualMarketUnit::query()->sole();

    expect($unit->source_site)->toBe('aqar')
        ->and($unit->listing_id)->toBe('101')
        ->and($unit->source_url)->toBe('https://example.com/a/101')
        ->and($unit->name)->toBe('فيلا للبيع في شارع المنار, حي المنار')
        ->and($unit->city)->toBe('جدة')
        ->and($unit->city_key)->toBe('جده')
        // «حي» is dropped, the way the deeds write a district.
        ->and($unit->district)->toBe('المنار')
        ->and($unit->kind)->toBe('فيلا')
        ->and($unit->price)->toBe(3600000)
        ->and($unit->area)->toBe(648.0)
        ->and($unit->price_per_meter)->toBe(5555.56)
        // «10+» is at least ten.
        ->and($unit->rooms)->toBe(10)
        ->and($unit->bedrooms)->toBe(10)
        ->and($unit->bathrooms)->toBeNull()
        ->and($unit->street_width)->toBe('25 / 12')
        ->and($unit->facade)->toBe('شمالية غربية')
        ->and($unit->property_age)->toBe('8 سنوات')
        ->and($unit->property_age_years)->toBe(8)
        // Posted late at night in Riyadh is that day in Riyadh.
        ->and($unit->posted_on->toDateString())->toBe('2026-10-28')
        ->and($unit->listing_updated_on->toDateString())->toBe('2026-11-06')
        ->and($unit->observed_on->toDateString())->toBe('2026-11-12');
});

it('reads the kind of property out of the title', function (string $title, ?string $kind) {
    $report = app(ListingFileImporter::class)->import(listingsFile([
        listingRow('aqar', '1', title: $title),
        listingRow('aqar', '2', title: 'فيلا للبيع'),
    ]));

    $unit = ManualMarketUnit::query()->where('listing_id', '1')->first();

    expect($unit?->kind)->toBe($kind);

    if ($kind === null) {
        expect($report['skipped'])->toBe([['row' => 2, 'reason' => 'العنوان لا يذكر نوع العقار.']]);
    }
})->with([
    ['فله للبيع حي السامر', 'فيلا'],
    ['فيلا مع شقة للبيع', 'فيلا'],
    ['دوبلكس للبيع', 'دوبلكس'],
    ['شقة للبيع', 'شقة'],
    ['دور للبيع في المنار', 'دور'],
    ['عقار مميز للبيع', null],
]);

it('takes the kind it is given for a listing whose title does not say', function () {
    app(ListingFileImporter::class)->import(listingsFile([listingRow('aqar', '1', title: 'عقار مميز للبيع')]), 'دوبلكس');

    expect(ManualMarketUnit::query()->sole()->kind)->toBe('دوبلكس');
});

it('updates a listing it has read before instead of adding it twice', function () {
    $importer = app(ListingFileImporter::class);

    $importer->import(listingsFile([listingRow('aqar', '1', price: '3000000'), listingRow('haraj', '1', price: '2000000')]));
    $report = $importer->import(listingsFile([listingRow('aqar', '1', price: '2800000')]));

    expect($report)->toMatchArray(['added' => 0, 'updated' => 1])
        ->and(ManualMarketUnit::query()->count())->toBe(2)
        ->and(ManualMarketUnit::query()->where('source_site', 'aqar')->sole()->price)->toBe(2800000)
        // The same number on another site is another listing.
        ->and(ManualMarketUnit::query()->where('source_site', 'haraj')->sole()->price)->toBe(2000000);
});

it('joins one property listed on several sites into one group', function () {
    $report = app(ListingFileImporter::class)->import(listingsFile([
        listingRow('aqar', '11', duplicates: 'aqar:11;aqar:12;propertyfinder:13;haraj:14'),
        listingRow('aqar', '12', duplicates: 'aqar:11;aqar:12;propertyfinder:13;haraj:14'),
        listingRow('propertyfinder', '13'),
        listingRow('haraj', '14'),
        // Named the other way round only, and with spaces.
        listingRow('bayut', '15', duplicates: 'aqar:11; haraj:14'),
        // On the same site, by number alone.
        listingRow('bayut', '880001', duplicates: 'duplicate ad of 880002'),
        listingRow('bayut', '880002', duplicates: 'duplicate ad of 880001'),
        listingRow('aqar', '99'),
    ]));

    $groups = ManualMarketUnit::query()->get()->groupBy(fn (ManualMarketUnit $unit): string => $unit->groupKey());

    expect($report['properties'])->toBe(3)
        ->and($report['repeated'])->toBe(2)
        ->and($groups)->toHaveCount(3)
        ->and(ManualMarketUnit::query()->where('listing_id', '15')->sole()->duplicate_group)->toBe('aqar:11')
        ->and(ManualMarketUnit::query()->where('listing_id', '880001')->sole()->duplicate_group)->toBe('bayut:880001')
        // Repeated nowhere: no group at all.
        ->and(ManualMarketUnit::query()->where('listing_id', '99')->sole()->duplicate_group)->toBeNull();
});

it('names the listings a duplicates note points at', function (string $note, ?string $site, array $refs) {
    expect(ListingDuplicates::refs($note, $site))->toBe($refs);
})->with([
    ['aqar:6874122;aqar:6883474;propertyfinder:52127786', 'aqar', ['aqar:6874122', 'aqar:6883474', 'propertyfinder:52127786']],
    ['aqar:6874122; haraj:11169632270', 'bayut', ['aqar:6874122', 'haraj:11169632270']],
    ['duplicate ad of 88154537', 'bayut', ['bayut:88154537']],
    ['', 'aqar', []],
]);

it('counts a property listed on several sites once when comparing', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'المنار']);
    Properties::factory()->create(['project_id' => $project->id, 'type' => 'فيلا', 'area' => 600, 'price' => 3000000, 'offer' => null]);
    $quarter = MarketQuarter::factory()->period(2026, 1)->create();
    MarketPriceStat::factory()->for($quarter, 'quarter')->district('المنار')->create();

    app(ListingFileImporter::class)->import(listingsFile([
        // One villa at 10,000 a metre, listed five times.
        listingRow('aqar', '1', price: '6000000', duplicates: 'aqar:2;bayut:3;haraj:4;propertyfinder:5'),
        listingRow('aqar', '2', price: '6000000'),
        listingRow('bayut', '3', price: '6000000'),
        listingRow('haraj', '4', price: '6000000'),
        listingRow('propertyfinder', '5', price: '6000000'),
        // Two others at 4,000 and 5,000.
        listingRow('aqar', '6', price: '2400000'),
        listingRow('aqar', '7', price: '3000000'),
    ]));

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices/projects'))
        ->assertJsonPath('data.projects.0.units.0.market.offers.scope', 'district')
        // Three villas, not seven listings: the median is 5,000, not 10,000.
        ->assertJsonPath('data.projects.0.units.0.market.offers.deals', 3)
        ->assertJsonPath('data.projects.0.units.0.market.offers.median', 5000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices/manual-units'))
        ->assertJsonCount(7, 'data.units')
        ->assertJsonPath('data.properties', 3)
        ->assertJsonPath('data.summary.0.units', 3)
        ->assertJsonPath('data.summary.0.median', 5000);

    $sizes = collect($this->actingAs($this->admin, 'admin')->getJson(panelUrl('/api/market-prices/manual-units'))->json('data.units'))
        ->pluck('group_size', 'listing_id');

    expect($sizes->only(['1', '2', '3', '4', '5'])->unique()->values()->all())->toBe([5])
        ->and($sizes->only(['6', '7'])->unique()->values()->all())->toBe([1]);
});

it('reports the rows it could not read and the districts the market does not know', function () {
    $quarter = MarketQuarter::factory()->period(2026, 1)->create();
    MarketPriceStat::factory()->for($quarter, 'quarter')->district('المنار')->create();

    $report = app(ListingFileImporter::class)->import(listingsFile([
        listingRow('aqar', '1'),
        listingRow('aqar', '2', price: ''),
        listingRow('aqar', '3', area: '0'),
        listingRow('aqar', '4', district: 'حي النعيم'),
        ',,,,,,,,,,,,,,,,,,',
    ]));

    expect($report['added'])->toBe(2)
        ->and($report['skipped'])->toBe([['row' => 3, 'reason' => 'لا سعر.'], ['row' => 4, 'reason' => 'لا مساحة.']])
        ->and($report['unknown_districts'])->toBe(['النعيم']);
});

it('refuses a file that is not a listings file', function () {
    expect(fn () => app(ListingFileImporter::class)->import(listingsFile(['أحمد,0500000000'], 'الاسم,الهاتف')))
        ->toThrow(UnreadableSalesFile::class);

    expect(fn () => app(ListingFileImporter::class)->import(listingsFile([listingRow('aqar', '1', price: '')])))
        ->toThrow(UnreadableSalesFile::class);

    expect(ManualMarketUnit::query()->count())->toBe(0);
});

it('imports a listings file from the command line', function () {
    $this->artisan('market:import-listings', ['path' => listingsFile([listingRow('aqar', '1'), listingRow('aqar', '2', price: '')])])
        ->expectsOutputToContain('أُضيف 1 إعلانًا')
        ->expectsOutputToContain('السطر 3')
        ->assertSuccessful();

    $this->artisan('market:import-listings', ['path' => '/nowhere.csv'])->assertFailed();
});

it('imports a listings file uploaded in the panel', function () {
    $file = UploadedFile::fake()->createWithContent('villas.csv', file_get_contents(listingsFile([
        listingRow('aqar', '1'),
        listingRow('bayut', '2', duplicates: 'aqar:1'),
    ])));

    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/market-prices/manual-units/import'), ['file' => $file, 'kind' => ''], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.added', 2)
        ->assertJsonPath('data.properties', 1)
        ->assertJsonPath('data.repeated', 1);
});

it('keeps the listings import away from guests', function () {
    $this->postJson(panelUrl('/api/market-prices/manual-units/import'))->assertUnauthorized();
});

it('turns away an upload that is not a listings file', function () {
    $file = UploadedFile::fake()->createWithContent('leads.csv', "الاسم,الهاتف\nأحمد,0500000000\n");

    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/market-prices/manual-units/import'), ['file' => $file], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});
