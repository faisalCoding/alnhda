<?php

use App\Enums\AreaBand;
use App\Models\Admin;
use App\Models\MarketIndicatorFile;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Models\MarketTypeStat;
use App\Models\Project;
use App\Models\Properties;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function placesUrl(string $city = ''): string
{
    return panelUrl('/api/market-prices/places'.($city === '' ? '' : '?city='.urlencode($city)));
}

/**
 * A quarter of deeds that sold in Jeddah's الصفا and الواحة and in Riyadh's
 * العليا, which is what the forms have to choose from.
 */
function marketWithPlaces(): MarketQuarter
{
    $quarter = MarketQuarter::factory()->period(2026, 1)->create();

    MarketPriceStat::factory()->for($quarter, 'quarter')->city('جدة')->create(['deals_count' => 500]);
    MarketPriceStat::factory()->for($quarter, 'quarter')->city('الرياض', 'الرياض')->create(['deals_count' => 900]);
    MarketPriceStat::factory()->for($quarter, 'quarter')->district('الصفا')->create();
    MarketPriceStat::factory()->for($quarter, 'quarter')->district('الواحة')->create();
    MarketPriceStat::factory()->for($quarter, 'quarter')->district('العليا', 'الرياض', 'الرياض')->create();

    return $quarter;
}

function projectPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'مشروع النهضة',
        'description' => 'وصف تفصيلي للمشروع الجديد',
        'location' => null,
        'status' => 'جديد',
        'project_type' => 'شقة',
        'map_url' => null,
        'guarantees' => [],
    ], $overrides);
}

it('keeps the choices away from guests', function () {
    $this->getJson(placesUrl())->assertUnauthorized();
});

it('offers the cities the deeds sold in, busiest first, and the kinds the market prices', function () {
    marketWithPlaces();
    MarketTypeStat::factory()->for(MarketIndicatorFile::factory()->period(2026, 1)->create(), 'file')->type('شقة')->create(['deals_count' => 900]);
    MarketTypeStat::factory()->for(MarketIndicatorFile::query()->first(), 'file')->type('فيلا')->create(['deals_count' => 90]);
    MarketTypeStat::factory()->for(MarketIndicatorFile::query()->first(), 'file')->type('أخرى')->create(['deals_count' => 5000]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(placesUrl())
        ->assertSuccessful()
        ->assertJsonPath('data.cities', [
            ['key' => 'الرياض', 'label' => 'الرياض', 'region' => 'الرياض'],
            ['key' => 'جده', 'label' => 'جدة', 'region' => 'مكة المكرمة'],
        ])
        ->assertJsonPath('data.kinds', ['شقة', 'فيلا', 'ملحق'])
        // No city asked for, no districts.
        ->assertJsonPath('data.districts', []);
});

it('offers the districts of the city asked for, by name', function () {
    marketWithPlaces();

    $this->actingAs($this->admin, 'admin')
        ->getJson(placesUrl('جدة'))
        ->assertJsonPath('data.city', 'جده')
        ->assertJsonPath('data.districts', [
            ['key' => 'الصفا', 'label' => 'الصفا'],
            ['key' => 'الواحه', 'label' => 'الواحة'],
        ]);
});

it('offers a place once however many quarters and bands it sold in', function () {
    $first = marketWithPlaces();
    $second = MarketQuarter::factory()->period(2026, 2)->create();
    MarketPriceStat::factory()->for($second, 'quarter')->city('جدة')->create(['deals_count' => 800]);
    MarketPriceStat::factory()->for($second, 'quarter')->district('الصفا')->create();
    MarketPriceStat::factory()->for($first, 'quarter')->district('الصفا')->classified('سكني')->create();
    MarketPriceStat::factory()->for($first, 'quarter')->district('الصفا')->band(AreaBand::Under150)->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(placesUrl('جدة'))
        ->assertJsonCount(2, 'data.cities')
        // Jeddah now has more deeds than Riyadh across the two quarters.
        ->assertJsonPath('data.cities.0.label', 'جدة')
        ->assertJsonCount(2, 'data.districts');
});

it('has only the usual kinds to offer before any market file has been uploaded', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(placesUrl('جدة'))
        ->assertJsonPath('data.cities', [])
        ->assertJsonPath('data.districts', [])
        ->assertJsonPath('data.kinds', ['شقة', 'فيلا', 'دور', 'دوبلكس', 'أرض', 'عمارة', 'ملحق']);
});

it('places a new project in a city and district of the market and writes its location from them', function () {
    marketWithPlaces();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects'), projectPayload([
            'location' => 'نص قديم',
            'market_city_key' => 'جده',
            'market_district_key' => 'الصفا',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.market_city_key', 'جده')
        ->assertJsonPath('data.market_district_key', 'الصفا')
        ->assertJsonPath('data.location', 'جدة حي الصفا');

    expect(Project::query()->sole()->location)->toBe('جدة حي الصفا');
});

it('places a project in a city alone', function () {
    marketWithPlaces();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects'), projectPayload(['market_city_key' => 'جدة', 'market_district_key' => null]))
        ->assertCreated()
        ->assertJsonPath('data.market_city_key', 'جده')
        ->assertJsonPath('data.market_district_key', null)
        ->assertJsonPath('data.location', 'جدة');
});

it('moves a project to another district and rewrites its location', function () {
    marketWithPlaces();
    $project = Project::factory()->create(['location' => 'جدة حي الصفا', 'market_city_key' => 'جده', 'market_district_key' => 'الصفا']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/projects/'.$project->id), projectPayload(['market_city_key' => 'جده', 'market_district_key' => 'الواحه']))
        ->assertSuccessful()
        ->assertJsonPath('data.location', 'جدة حي الواحة')
        ->assertJsonPath('data.market_district_key', 'الواحه');
});

it('keeps a typed location when the project is placed nowhere', function () {
    marketWithPlaces();
    $project = Project::factory()->create(['location' => 'جدة حي الصفا', 'market_city_key' => 'جده', 'market_district_key' => 'الصفا']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/projects/'.$project->id), projectPayload([
            'location' => 'جدة مخطط التيسير',
            'market_city_key' => null,
            'market_district_key' => null,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.location', 'جدة مخطط التيسير')
        ->assertJsonPath('data.market_city_key', null)
        ->assertJsonPath('data.market_district_key', null);
});

it('leaves the placement alone when a request does not mention it', function () {
    marketWithPlaces();
    $project = Project::factory()->create(['location' => 'جدة حي الصفا', 'market_city_key' => 'جده', 'market_district_key' => 'الصفا']);

    // What a copy of the panel from before this change still sends.
    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/projects/'.$project->id), projectPayload(['location' => 'جدة حي الصفا']))
        ->assertSuccessful();

    expect($project->fresh()->market_city_key)->toBe('جده')
        ->and($project->fresh()->market_district_key)->toBe('الصفا');
});

it('places a project only in a district of the chosen city', function (array $place) {
    marketWithPlaces();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects'), projectPayload($place))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('market_district_key');

    expect(Project::query()->count())->toBe(0);
})->with([
    'another city\'s district' => [['market_city_key' => 'جده', 'market_district_key' => 'العليا']],
    'a district that does not exist' => [['market_city_key' => 'جده', 'market_district_key' => 'حي الأحلام']],
    'a district with no city' => [['market_city_key' => null, 'market_district_key' => 'الصفا']],
]);

it('lists a project with where it stands in the market', function () {
    Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/projects'))
        ->assertJsonPath('data.0.market_city_key', 'جده')
        ->assertJsonPath('data.0.market_district_key', 'الصفا');
});

it('lets the type typed in the unit form speak once it is changed there', function () {
    $unit = Properties::factory()->create(['type' => 'شقة', 'market_type' => 'دور']);

    $payload = fn (string $type): array => [
        'name' => 'شقة فاخرة', 'project_id' => $unit->project_id, 'price' => 550000, 'offer' => null, 'status' => 'جديد',
        'rooms' => 5, 'bathrooms' => 3, 'living_rooms' => 1, 'mainds_room' => 1, 'area' => 180, 'doors' => 2, 'type' => $type,
        'parkings' => 1, 'driver_room' => 1, 'facade' => 'شرقية', 'furniture' => true, 'unit_youtube' => null, 'stages_building_youtube' => null,
    ];

    // The type untouched: the kind set on the market screen stays.
    $this->actingAs($this->admin, 'admin')->putJson(panelUrl('/api/properties/'.$unit->id), $payload('شقة'))->assertSuccessful();
    expect($unit->fresh()->market_type)->toBe('دور');

    $this->actingAs($this->admin, 'admin')->putJson(panelUrl('/api/properties/'.$unit->id), $payload('فيلا'))->assertSuccessful();
    expect($unit->fresh()->type)->toBe('فيلا')
        ->and($unit->fresh()->market_type)->toBeNull();
});

it('keeps a unit\'s plot apart from its own area', function () {
    $project = Project::factory()->create();
    $payload = fn (array $overrides = []): array => array_merge([
        'name' => 'فيلا B', 'project_id' => $project->id, 'price' => 1950000, 'offer' => null, 'status' => 'جديد',
        'rooms' => 5, 'bathrooms' => 3, 'living_rooms' => 1, 'mainds_room' => 1, 'area' => 465.27, 'doors' => 2, 'type' => 'فيلا',
        'parkings' => 1, 'driver_room' => 1, 'facade' => 'شرقية', 'furniture' => true, 'unit_youtube' => null, 'stages_building_youtube' => null,
    ], $overrides);

    $unit = Properties::query()->findOrFail(
        $this->actingAs($this->admin, 'admin')->postJson(panelUrl('/api/properties'), $payload(['land_area' => 375]))
            ->assertCreated()
            ->assertJsonPath('data.land_area', 375)
            ->json('data.id')
    );

    // A copy of the panel from before the field leaves it as it was.
    $this->actingAs($this->admin, 'admin')->putJson(panelUrl('/api/properties/'.$unit->id), $payload())->assertSuccessful();
    expect((float) $unit->fresh()->land_area)->toBe(375.0);

    $this->actingAs($this->admin, 'admin')->putJson(panelUrl('/api/properties/'.$unit->id), $payload(['land_area' => null]))->assertSuccessful();
    expect($unit->fresh()->land_area)->toBeNull();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/properties/'.$unit->id), $payload(['land_area' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('land_area');
});
