<?php

use App\Models\Admin;
use App\Models\ManualMarketUnit;
use App\Models\MarketIndicatorFile;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Models\MarketTypeStat;
use App\Services\Market\ListingDuplicates;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function manualUnitsUrl(string $suffix = ''): string
{
    return panelUrl('/api/market-prices/manual-units'.$suffix);
}

/**
 * What the form sends for a flat seen in الصفا.
 */
function manualUnitPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'شقة ٣ غرف',
        'project_name' => 'مشروع الواحة',
        'city' => 'جدة',
        'district' => 'الصفا',
        'kind' => 'شقة',
        'area' => 150,
        'price' => 600000,
        'observed_on' => now()->subDays(3)->toDateString(),
        'source_url' => 'https://example.com/listing/1',
    ], $overrides);
}

/**
 * The districts the deeds know for Jeddah, which is what a hand-entered unit
 * has to be placed in.
 */
function jeddahDistricts(string ...$districts): void
{
    $quarter = MarketQuarter::factory()->period(2026, 1)->create();
    MarketPriceStat::factory()->for($quarter, 'quarter')->city('جدة')->create();

    foreach ($districts as $district) {
        MarketPriceStat::factory()->for($quarter, 'quarter')->district($district)->create();
    }
}

it('keeps hand-entered units away from guests', function () {
    $unit = ManualMarketUnit::factory()->create();

    $this->getJson(manualUnitsUrl())->assertUnauthorized();
    $this->postJson(manualUnitsUrl(), manualUnitPayload())->assertUnauthorized();
    $this->putJson(manualUnitsUrl('/'.$unit->id), manualUnitPayload(['price' => 1]))->assertUnauthorized();
    $this->deleteJson(manualUnitsUrl('/'.$unit->id))->assertUnauthorized();

    expect(ManualMarketUnit::query()->count())->toBe(1)
        ->and($unit->fresh()->price)->not->toBe(1);
});

it('lists the units entered for the city, the most recently seen first', function () {
    ManualMarketUnit::factory()->create(['name' => 'الأقدم', 'observed_on' => now()->subDays(40)]);
    ManualMarketUnit::factory()->create(['name' => 'الأحدث', 'observed_on' => now()->subDay()]);
    ManualMarketUnit::factory()->offered('العليا', 150, 6000, city: 'الرياض')->create(['name' => 'في مدينة أخرى']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(manualUnitsUrl())
        ->assertSuccessful()
        ->assertJsonPath('data.city', ['key' => 'جده', 'label' => 'جدة'])
        ->assertJsonCount(2, 'data.units')
        ->assertJsonPath('data.units.0.name', 'الأحدث')
        ->assertJsonPath('data.units.1.name', 'الأقدم')
        ->assertJsonPath('data.fresh_months', ManualMarketUnit::FRESH_MONTHS);

    $this->actingAs($this->admin, 'admin')
        ->getJson(manualUnitsUrl('?city='.urlencode('الرياض')))
        ->assertJsonCount(1, 'data.units')
        ->assertJsonPath('data.units.0.name', 'في مدينة أخرى');
});

it('offers the districts and the kinds the market figures are kept by', function () {
    jeddahDistricts('الصفا', 'الواحة');
    $file = MarketIndicatorFile::factory()->period(2026, 1)->create();
    MarketTypeStat::factory()->for($file, 'file')->type('شقة')->create(['deals_count' => 900]);
    MarketTypeStat::factory()->for($file, 'file')->type('دور')->create(['deals_count' => 30]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(manualUnitsUrl())
        ->assertJsonPath('data.districts', [['key' => 'الصفا', 'label' => 'الصفا'], ['key' => 'الواحه', 'label' => 'الواحة']])
        ->assertJsonPath('data.kinds', ['شقة', 'دور', 'ملحق']);
});

it('sums up what the market asks for each kind, from the units still fresh', function () {
    foreach ([3000, 4000, 5000] as $rate) {
        ManualMarketUnit::factory()->offered('الصفا', 100, $rate)->create(['observed_on' => now()->subDays(10)]);
    }
    ManualMarketUnit::factory()->offered('الصفا', 400, 3500, kind: 'فيلا')->create(['observed_on' => now()->subDays(10)]);
    ManualMarketUnit::factory()->offered('الصفا', 100, 9000)->create(['observed_on' => now()->subYears(2)]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(manualUnitsUrl())
        ->assertJsonCount(5, 'data.units')
        ->assertJsonPath('data.stale', 1)
        ->assertJsonCount(2, 'data.summary')
        ->assertJsonPath('data.summary.0', ['kind' => 'شقة', 'units' => 3, 'lower' => 3500, 'median' => 4000, 'upper' => 4500, 'median_price' => 400000])
        ->assertJsonPath('data.summary.1.kind', 'فيلا')
        ->assertJsonPath('data.summary.1.units', 1);
});

it('stores a unit under the market\'s own names, with the price per metre a comparison reads', function () {
    jeddahDistricts('الصفا');

    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), manualUnitPayload(['city' => 'جده', 'district' => 'الصفا']))
        ->assertCreated()
        ->assertJsonPath('data.label', 'شقة ٣ غرف')
        ->assertJsonPath('data.project_name', 'مشروع الواحة')
        ->assertJsonPath('data.city', 'جدة')
        ->assertJsonPath('data.district', 'الصفا')
        ->assertJsonPath('data.district_key', 'الصفا')
        ->assertJsonPath('data.kind', 'شقة')
        ->assertJsonPath('data.area', 150)
        ->assertJsonPath('data.price', 600000)
        ->assertJsonPath('data.rate', 4000)
        ->assertJsonPath('data.observed_on', now()->subDays(3)->toDateString())
        ->assertJsonPath('data.fresh', true)
        ->assertJsonPath('data.source_url', 'https://example.com/listing/1');

    $stored = ManualMarketUnit::query()->sole();

    expect($stored->city_key)->toBe('جده')
        ->and($stored->district_key)->toBe('الصفا')
        ->and($stored->price_per_meter)->toBe(4000.0);
});

it('stores a unit seen with nothing but a kind, a place, a size and a price', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), [
            'name' => '',
            'project_name' => '',
            'city' => 'جدة',
            'district' => 'الواحة',
            'kind' => 'دور',
            'area' => 200,
            'price' => 800000,
            'observed_on' => '',
            'source_url' => '',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', null)
        // Called by what it is and where, when it was given no name.
        ->assertJsonPath('data.label', 'دور — الواحة')
        ->assertJsonPath('data.project_name', null)
        ->assertJsonPath('data.source_url', null)
        // Seen today, when no day was given.
        ->assertJsonPath('data.observed_on', now()->toDateString());
});

it('places a unit only in a district the deeds know for the city', function () {
    jeddahDistricts('الصفا', 'الواحة');

    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), manualUnitPayload(['district' => 'حي الأحلام']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('district');

    // The same district however it is spelt is the same district.
    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), manualUnitPayload(['district' => 'الواحه']))
        ->assertCreated()
        ->assertJsonPath('data.district', 'الواحة')
        ->assertJsonPath('data.district_key', 'الواحه');

    expect(ManualMarketUnit::query()->count())->toBe(1);
});

it('takes any district for a city the deeds have not been uploaded for', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), manualUnitPayload(['city' => 'أبها', 'district' => 'أبها/ المنسك ']))
        ->assertCreated()
        ->assertJsonPath('data.city', 'أبها')
        // Written the way the deeds would write it: the city prefix dropped.
        ->assertJsonPath('data.district', 'المنسك');
});

it('rejects a unit that cannot be compared', function (array $overrides, string $field) {
    $payload = array_filter(manualUnitPayload($overrides), fn (mixed $value): bool => $value !== null);

    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(ManualMarketUnit::query()->count())->toBe(0);
})->with([
    'no city' => [['city' => null], 'city'],
    'no district' => [['district' => null], 'district'],
    'a kind the market does not price' => [['kind' => 'قصر'], 'kind'],
    'no area' => [['area' => null], 'area'],
    'a price of nothing' => [['price' => 0], 'price'],
    'seen tomorrow' => [['observed_on' => now()->addDay()->toDateString()], 'observed_on'],
    'a link that is not one' => [['source_url' => 'javascript:alert(1)'], 'source_url'],
]);

it('stores every field a listing has, the same as a listings file', function () {
    ManualMarketUnit::factory()->listed('haraj', '11184861505')->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), manualUnitPayload([
            'source_site' => ' Aqar ',
            'listing_id' => '6662806',
            'rooms' => 10,
            'bedrooms' => 9,
            'bathrooms' => 5,
            'street_width' => '25 / 12',
            'facade' => 'شمالية غربية',
            'property_age' => '8 سنوات',
            'property_age_years' => 8,
            'posted_on' => '2026-10-28',
            'listing_updated_on' => '2026-11-06',
            'duplicates' => 'haraj:11184861505',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.source_site', 'aqar')
        ->assertJsonPath('data.site_label', 'عقار')
        ->assertJsonPath('data.listing_id', '6662806')
        ->assertJsonPath('data.rooms', 10)
        ->assertJsonPath('data.bedrooms', 9)
        ->assertJsonPath('data.bathrooms', 5)
        ->assertJsonPath('data.street_width', '25 / 12')
        ->assertJsonPath('data.facade', 'شمالية غربية')
        ->assertJsonPath('data.property_age_years', 8)
        ->assertJsonPath('data.posted_on', '2026-10-28')
        ->assertJsonPath('data.listing_updated_on', '2026-11-06')
        ->assertJsonPath('data.duplicates', 'haraj:11184861505')
        // Said to repeat another listing: the two are one group.
        ->assertJsonPath('data.duplicate_group', 'aqar:6662806');
});

it('does not take the same listing twice, but lets it be corrected', function () {
    $this->actingAs($this->admin, 'admin')->postJson(manualUnitsUrl(), manualUnitPayload(['source_site' => 'aqar', 'listing_id' => '1']))->assertCreated();

    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), manualUnitPayload(['source_site' => 'aqar', 'listing_id' => '1']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('listing_id');

    // The same number on another site is another listing.
    $this->actingAs($this->admin, 'admin')->postJson(manualUnitsUrl(), manualUnitPayload(['source_site' => 'haraj', 'listing_id' => '1']))->assertCreated();

    $unit = ManualMarketUnit::query()->where('source_site', 'aqar')->sole();

    $this->actingAs($this->admin, 'admin')
        ->putJson(manualUnitsUrl('/'.$unit->id), manualUnitPayload(['source_site' => 'aqar', 'listing_id' => '1', 'price' => 650000]))
        ->assertSuccessful();

    expect($unit->fresh()->price)->toBe(650000);
});

it('ungroups listings when the one joining them is deleted', function () {
    $joining = ManualMarketUnit::factory()->listed('bayut', '3', ['aqar:1', 'haraj:2'])->create();
    ManualMarketUnit::factory()->listed('aqar', '1')->create();
    ManualMarketUnit::factory()->listed('haraj', '2')->create();
    app(ListingDuplicates::class)->regroup(['جده']);

    expect(ManualMarketUnit::query()->whereNotNull('duplicate_group')->count())->toBe(3);

    $this->actingAs($this->admin, 'admin')->deleteJson(manualUnitsUrl('/'.$joining->id))->assertSuccessful();

    expect(ManualMarketUnit::query()->whereNotNull('duplicate_group')->count())->toBe(0);
});

it('corrects a unit and works its price per metre out again', function () {
    $unit = ManualMarketUnit::factory()->offered('الصفا', 150, 4000)->create(['name' => 'قبل التصحيح']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(manualUnitsUrl('/'.$unit->id), manualUnitPayload(['name' => 'بعد التصحيح', 'area' => 200, 'price' => 900000, 'kind' => 'دور']))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $unit->id)
        ->assertJsonPath('data.name', 'بعد التصحيح')
        ->assertJsonPath('data.kind', 'دور')
        ->assertJsonPath('data.rate', 4500);

    expect(ManualMarketUnit::query()->count())->toBe(1)
        ->and($unit->fresh()->price_per_meter)->toBe(4500.0);
});

it('does not correct a unit into something that cannot be compared', function () {
    $unit = ManualMarketUnit::factory()->offered('الصفا', 150, 4000)->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(manualUnitsUrl('/'.$unit->id), manualUnitPayload(['area' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('area');

    expect($unit->fresh()->area)->toBe(150.0);
});

it('marks a unit seen more than a year ago as too old to compare with', function () {
    ManualMarketUnit::factory()->create(['name' => 'قديمة', 'observed_on' => now()->subMonths(ManualMarketUnit::FRESH_MONTHS)->subDay()]);
    // Entered before the day was recorded: dated by when it was typed in.
    ManualMarketUnit::factory()->create(['name' => 'بلا تاريخ', 'observed_on' => null]);

    $units = collect($this->actingAs($this->admin, 'admin')->getJson(manualUnitsUrl())->json('data.units'))->keyBy('name');

    expect($units['قديمة']['fresh'])->toBeFalse()
        ->and($units['بلا تاريخ']['fresh'])->toBeTrue()
        ->and($units['بلا تاريخ']['observed_on'])->toBe(now()->toDateString())
        ->and(ManualMarketUnit::query()->fresh()->pluck('name')->all())->toBe(['بلا تاريخ']);
});

it('deletes a unit an admin entered', function () {
    $unit = ManualMarketUnit::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(manualUnitsUrl('/'.$unit->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true)
        ->assertJsonPath('data.id', $unit->id);

    expect(ManualMarketUnit::query()->count())->toBe(0);
});
