<?php

use App\Models\Admin;
use App\Models\MarketDeed;
use App\Models\MarketIndicatorFile;
use App\Models\MarketQuarter;
use App\Models\MarketTypeStat;
use App\Models\Project;
use App\Models\Properties;
use App\Services\Market\PlaceName;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->quarter = MarketQuarter::factory()->period(2026, 1)->create();
});

function projectsUrl(string $suffix = ''): string
{
    return panelUrl('/api/market-prices/projects'.$suffix);
}

function unitsUrl(Properties $unit): string
{
    return panelUrl('/api/market-prices/units/'.$unit->id);
}

/**
 * A deed in the test's quarter: a district, an area and a price per metre.
 */
function marketDeed(MarketQuarter $quarter, string $district, float $area, float $rate, string $city = 'جدة'): MarketDeed
{
    return MarketDeed::factory()->for($quarter, 'quarter')->in($district, $city)->sold($area, $rate)->create();
}

function unit(Project $project, ?int $price, ?int $area, ?int $offer = null, string $type = 'وحدة عقارية', ?string $marketType = null): Properties
{
    return Properties::factory()->create([
        'project_id' => $project->id,
        'price' => $price,
        'area' => $area,
        'offer' => $offer,
        'type' => $type,
        'market_type' => $marketType,
    ]);
}

/**
 * What the authority says one kind of property sold for in Jeddah, or in one
 * of its districts, during a quarter.
 */
function kindFigure(int $year, int $quarter, string $kind, int $average, ?string $district = null, int $deals = 100): MarketTypeStat
{
    $file = MarketIndicatorFile::query()->where('year', $year)->where('quarter', $quarter)->first()
        ?? MarketIndicatorFile::factory()->period($year, $quarter)->create();

    return MarketTypeStat::factory()
        ->for($file, 'file')
        ->type($kind)
        ->when($district !== null, fn ($factory) => $factory->district($district))
        ->create([
            'deals_count' => $deals,
            'average_price_per_meter' => $average,
            'lowest_price_per_meter' => $average - 1000,
            'highest_price_per_meter' => $average + 2000,
        ]);
}

it('keeps the comparison away from guests', function () {
    $project = Project::factory()->create();
    $unit = unit($project, 600000, 150);

    $this->getJson(projectsUrl())->assertUnauthorized();
    $this->putJson(projectsUrl('/'.$project->id), ['city' => 'جدة', 'district' => 'الصفا'])->assertUnauthorized();
    $this->putJson(unitsUrl($unit), ['city' => 'جدة', 'kind' => 'شقة'])->assertUnauthorized();

    expect($project->fresh()->market_district_key)->toBeNull()
        ->and($unit->fresh()->market_type)->toBeNull();
});

it('lists every unit of a project on its own row', function () {
    $project = Project::factory()->create(['name' => 'برج النهضة', 'project_type' => 'شقة']);
    unit($project, 600000, 150);
    unit($project, 900000, 180, offer: 720000);
    unit($project, null, null);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertSuccessful()
        ->assertJsonPath('data.city.label', 'جدة')
        ->assertJsonPath('data.projects.0.name', 'برج النهضة')
        ->assertJsonCount(3, 'data.projects.0.units')
        ->assertJsonPath('data.projects.0.units.0.area', 150)
        ->assertJsonPath('data.projects.0.units.0.price', 600000)
        ->assertJsonPath('data.projects.0.units.0.rate', 4000)
        // Priced by what is actually asked: the offer, not the list price.
        ->assertJsonPath('data.projects.0.units.1.price', 720000)
        ->assertJsonPath('data.projects.0.units.1.rate', 4000)
        // A unit with no price is listed, and priced at nothing.
        ->assertJsonPath('data.projects.0.units.2.price', null)
        ->assertJsonPath('data.projects.0.units.2.rate', null)
        ->assertJsonPath('data.projects.0.units.2.market.similar', null);
});

it('reads a unit against what its own kind of property sold for', function () {
    $project = Project::factory()->create();
    unit($project, 600000, 150, type: 'شقة');
    unit($project, 2000000, 400, type: 'فيلا');

    kindFigure(2026, 1, 'شقة', 3900, deals: 4000);
    kindFigure(2026, 1, 'فيلا', 3500, deals: 140);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.has_type_figures', true)
        ->assertJsonPath('data.kinds', ['شقة', 'فيلا'])
        ->assertJsonPath('data.projects.0.units.0.kind', 'شقة')
        ->assertJsonPath('data.projects.0.units.0.market.kind.scope', 'city')
        ->assertJsonPath('data.projects.0.units.0.market.kind.average', 3900)
        ->assertJsonPath('data.projects.0.units.0.market.kind.lowest', 2900)
        ->assertJsonPath('data.projects.0.units.0.market.kind.highest', 5900)
        ->assertJsonPath('data.projects.0.units.0.market.kind.deals', 4000)
        ->assertJsonPath('data.projects.0.units.0.market.kind.quarter', '2026-1')
        ->assertJsonPath('data.projects.0.units.1.kind', 'فيلا')
        ->assertJsonPath('data.projects.0.units.1.market.kind.average', 3500);
});

it('guesses a unit\'s kind from the unit, then from its project, until it is set', function () {
    kindFigure(2026, 1, 'شقة', 3900);
    kindFigure(2026, 1, 'فيلا', 3500);
    kindFigure(2026, 1, 'دور', 5200);

    $project = Project::factory()->create(['project_type' => 'دور']);
    unit($project, 600000, 150, type: 'شقة');
    unit($project, 600000, 150, type: 'وحدة عقارية');
    unit($project, 600000, 150, type: 'شقة', marketType: 'فيلا');

    $bare = Project::factory()->create(['project_type' => 'مجمع سكني', 'sort_order' => 5]);
    unit($bare, 600000, 150);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units.0.kind', 'شقة')
        ->assertJsonPath('data.projects.0.units.0.kind_source', 'unit')
        // «وحدة عقارية» is not a kind the market prices; the project's is.
        ->assertJsonPath('data.projects.0.units.1.kind', 'دور')
        ->assertJsonPath('data.projects.0.units.1.kind_source', 'project')
        ->assertJsonPath('data.projects.0.units.2.kind', 'فيلا')
        ->assertJsonPath('data.projects.0.units.2.kind_source', 'set')
        // Nothing to go on: no kind, and no price for one.
        ->assertJsonPath('data.projects.1.units.0.kind', null)
        ->assertJsonPath('data.projects.1.units.0.market.kind', null);
});

it('sets the kind of a unit and goes back to guessing when it is cleared', function () {
    kindFigure(2026, 1, 'شقة', 3900);
    kindFigure(2026, 1, 'دور', 5200);

    $project = Project::factory()->create();
    $unit = unit($project, 600000, 150, type: 'شقة');

    $this->actingAs($this->admin, 'admin')
        ->putJson(unitsUrl($unit), ['city' => 'جدة', 'kind' => 'دور'])
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.units.0.kind', 'دور')
        ->assertJsonPath('data.projects.0.units.0.kind_source', 'set')
        ->assertJsonPath('data.projects.0.units.0.market.kind.average', 5200);

    expect($unit->fresh()->market_type)->toBe('دور')
        // The unit's own type, the one a buyer reads, is not touched.
        ->and($unit->fresh()->type)->toBe('شقة');

    $this->actingAs($this->admin, 'admin')
        ->putJson(unitsUrl($unit), ['city' => 'جدة', 'kind' => null])
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.units.0.kind', 'شقة')
        ->assertJsonPath('data.projects.0.units.0.kind_source', 'unit');

    expect($unit->fresh()->market_type)->toBeNull();
});

it('gives a unit only a kind the market prices', function (array $payload, string $field) {
    kindFigure(2026, 1, 'شقة', 3900);
    $unit = unit(Project::factory()->create(), 600000, 150);

    $this->actingAs($this->admin, 'admin')
        ->putJson(unitsUrl($unit), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($unit->fresh()->market_type)->toBeNull();
})->with([
    'a word that is not a kind' => [['city' => 'جدة', 'kind' => 'قصر فخم'], 'kind'],
    'the catch-all the authority uses' => [['city' => 'جدة', 'kind' => 'أخرى'], 'kind'],
    'no city' => [['kind' => 'شقة'], 'city'],
    'no kind at all' => [['city' => 'جدة'], 'kind'],
]);

it('offers the usual kinds before the authority\'s figures have been uploaded', function () {
    $unit = unit(Project::factory()->create(), 600000, 150, type: 'شقة');

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.has_type_figures', false)
        ->assertJsonPath('data.kinds', ['شقة', 'فيلا', 'دور', 'دوبلكس', 'أرض', 'عمارة'])
        ->assertJsonPath('data.projects.0.units.0.kind', 'شقة')
        ->assertJsonPath('data.projects.0.units.0.market.kind', null);

    $this->actingAs($this->admin, 'admin')
        ->putJson(unitsUrl($unit), ['city' => 'جدة', 'kind' => 'دوبلكس'])
        ->assertSuccessful();
});

it('reads a kind in the project\'s district where the authority published one, in the city otherwise', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);
    unit($project, 600000, 150, type: 'شقة');
    unit($project, 2000000, 400, type: 'فيلا');
    marketDeed($this->quarter, 'الصفا', 150, 4000);

    kindFigure(2026, 1, 'شقة', 3900);
    kindFigure(2026, 1, 'فيلا', 3500);
    // By district only until late 2025, so the district's figure is older.
    kindFigure(2025, 3, 'شقة', 3700);
    kindFigure(2025, 3, 'شقة', 4300, district: 'الصفا', deals: 60);
    kindFigure(2025, 3, 'شقة', 3000, district: 'الواحة');

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.district_label', 'الصفا')
        ->assertJsonPath('data.projects.0.units.0.market.kind.scope', 'district')
        ->assertJsonPath('data.projects.0.units.0.market.kind.average', 4300)
        ->assertJsonPath('data.projects.0.units.0.market.kind.deals', 60)
        ->assertJsonPath('data.projects.0.units.0.market.kind.quarter', '2025-3')
        // No villas published for the district: the city's latest figure.
        ->assertJsonPath('data.projects.0.units.1.market.kind.scope', 'city')
        ->assertJsonPath('data.projects.0.units.1.market.kind.average', 3500)
        ->assertJsonPath('data.projects.0.units.1.market.kind.quarter', '2026-1');
});

it('finds the project\'s district in the authority\'s files however they spell it', function (string $deeds, string $authority) {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => PlaceName::key($deeds)]);
    unit($project, 600000, 150, type: 'شقة');
    marketDeed($this->quarter, $deeds, 150, 4000);

    kindFigure(2026, 1, 'شقة', 3900);
    kindFigure(2025, 3, 'شقة', 4300, district: $authority);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units.0.market.kind.scope', 'district')
        ->assertJsonPath('data.projects.0.units.0.market.kind.average', 4300);
})->with([
    'a closing hamza' => ['الصفا', 'الصفاء'],
    'a space inside a name' => ['الأمير عبد المجيد', 'الأمير عبدالمجيد'],
    'a hamza on the alef' => ['أم السلم', 'ام السلم'],
]);

it('does not take one district for another', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);
    unit($project, 600000, 150, type: 'شقة');
    marketDeed($this->quarter, 'الصفا', 150, 4000);

    kindFigure(2026, 1, 'شقة', 3900);
    kindFigure(2025, 3, 'شقة', 4300, district: 'الصالحية');

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units.0.market.kind.scope', 'city');
});

it('merges the classifications of a kind into one figure', function () {
    unit(Project::factory()->create(), 600000, 150, type: 'أرض');

    $file = MarketIndicatorFile::factory()->period(2026, 1)->create();
    MarketTypeStat::factory()->for($file, 'file')->type('أرض', 'سكني')->create(['deals_count' => 300, 'average_price_per_meter' => 2000]);
    MarketTypeStat::factory()->for($file, 'file')->type('أرض', 'تجاري')->create(['deals_count' => 100, 'average_price_per_meter' => 9000]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units.0.market.kind.deals', 400)
        ->assertJsonPath('data.projects.0.units.0.market.kind.average', 3750);
});

it('sets beside each unit the deeds of about its own size', function () {
    $project = Project::factory()->create();
    unit($project, 600000, 150);
    unit($project, 2000000, 500);

    foreach ([3000, 3500, 4000, 4500, 5000] as $rate) {
        marketDeed($this->quarter, 'الصفا', 160, $rate);
    }
    marketDeed($this->quarter, 'الصفا', 520, 1800);
    marketDeed($this->quarter, 'الصفا', 900, 1000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        // 120 to 180 m²: the five flats, not the plots.
        ->assertJsonPath('data.projects.0.units.0.market.similar.scope', 'city')
        ->assertJsonPath('data.projects.0.units.0.market.similar.deals', 5)
        ->assertJsonPath('data.projects.0.units.0.market.similar.median', 4000)
        ->assertJsonPath('data.projects.0.units.0.market.similar.lower', 3500)
        ->assertJsonPath('data.projects.0.units.0.market.similar.upper', 4500)
        // 400 to 600 m²: its own comparables, not the first unit's.
        ->assertJsonPath('data.projects.0.units.1.market.similar.deals', 1)
        ->assertJsonPath('data.projects.0.units.1.market.similar.median', 1800);
});

it('places a project in a district and reads its units against that district', function () {
    $project = Project::factory()->create();
    unit($project, 600000, 150);

    marketDeed($this->quarter, 'الصفا', 150, 5000);
    marketDeed($this->quarter, 'الصفا', 160, 5200);
    marketDeed($this->quarter, 'الواحة', 150, 3000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.districts', [['key' => 'الصفا', 'label' => 'الصفا'], ['key' => 'الواحه', 'label' => 'الواحة']])
        ->assertJsonPath('data.projects.0.units.0.market.similar.deals', 3);

    $this->actingAs($this->admin, 'admin')
        ->putJson(projectsUrl('/'.$project->id), ['city' => 'جدة', 'district' => 'الصفا'])
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.district', 'الصفا')
        ->assertJsonPath('data.projects.0.district_label', 'الصفا')
        ->assertJsonPath('data.projects.0.units.0.market.similar.scope', 'district')
        ->assertJsonPath('data.projects.0.units.0.market.similar.deals', 2)
        ->assertJsonPath('data.projects.0.units.0.market.similar.median', 5100);

    expect($project->fresh()->market_city_key)->toBe('جده')
        ->and($project->fresh()->market_district_key)->toBe('الصفا');
});

it('falls back to the city when nothing of a unit\'s size sold in its district', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);
    unit($project, 600000, 150);
    marketDeed($this->quarter, 'الصفا', 900, 1000);
    marketDeed($this->quarter, 'الواحة', 150, 3000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units.0.market.similar.scope', 'city')
        ->assertJsonPath('data.projects.0.units.0.market.similar.deals', 1);
});

it('says nothing of similar deeds when none of that size sold anywhere', function () {
    unit(Project::factory()->create(), 600000, 150);
    marketDeed($this->quarter, 'الصفا', 900, 1000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units.0.market.similar', null);
});

it('takes a project out of its district', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);
    marketDeed($this->quarter, 'الصفا', 150, 5000);

    $this->actingAs($this->admin, 'admin')
        ->putJson(projectsUrl('/'.$project->id), ['city' => 'جدة', 'district' => null])
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.district', null);

    expect($project->fresh()->market_city_key)->toBeNull()
        ->and($project->fresh()->market_district_key)->toBeNull();
});

it('places a project only in a district the deeds know', function (array $payload, string $field) {
    $project = Project::factory()->create();
    marketDeed($this->quarter, 'الصفا', 150, 5000);
    marketDeed($this->quarter, 'العليا', 150, 5000, 'الرياض');

    $this->actingAs($this->admin, 'admin')
        ->putJson(projectsUrl('/'.$project->id), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($project->fresh()->market_district_key)->toBeNull();
})->with([
    'a district nobody sold in' => [['city' => 'جدة', 'district' => 'حي لا وجود له'], 'district'],
    'another city\'s district' => [['city' => 'جدة', 'district' => 'العليا'], 'district'],
    'no city' => [['district' => 'الصفا'], 'city'],
    'no district at all' => [['city' => 'جدة'], 'district'],
]);

it('compares with the latest four quarters of deeds only', function () {
    unit(Project::factory()->create(), 600000, 150);

    foreach ([[2024, 4, 1000], [2025, 1, 2000], [2025, 2, 3000], [2025, 3, 4000], [2025, 4, 5000]] as [$year, $quarter, $rate]) {
        marketDeed(MarketQuarter::factory()->period($year, $quarter)->create(), 'الصفا', 150, $rate);
    }
    marketDeed($this->quarter, 'الصفا', 150, 6000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units.0.market.similar.deals', 4)
        ->assertJsonPath('data.projects.0.units.0.market.similar.from', '2025-2')
        ->assertJsonPath('data.projects.0.units.0.market.similar.to', '2026-1')
        ->assertJsonPath('data.projects.0.units.0.market.similar.median', 4500);
});

it('does not read a project placed in another city against this one\'s districts', function () {
    $project = Project::factory()->create(['market_city_key' => 'الرياض', 'market_district_key' => 'العليا']);
    unit($project, 600000, 150);
    marketDeed($this->quarter, 'الصفا', 150, 5000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.elsewhere', true)
        ->assertJsonPath('data.projects.0.district', null)
        ->assertJsonPath('data.projects.0.units.0.market.similar.scope', 'city');
});

it('lists a project that has no units', function () {
    Project::factory()->create(['name' => 'أرض فضاء']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.name', 'أرض فضاء')
        ->assertJsonPath('data.projects.0.units', []);
});

it('keeps the market placement out of what the public site says about a project and a unit', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);
    $unit = unit($project, 600000, 150, marketType: 'دور');

    $this->get(route('project', $project))->assertOk()->assertDontSee('market_district_key');
    $this->get(route('properties', $unit))->assertOk()->assertDontSee('market_type');
});
