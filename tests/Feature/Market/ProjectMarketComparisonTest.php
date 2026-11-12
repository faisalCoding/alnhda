<?php

use App\Models\Admin;
use App\Models\MarketDeed;
use App\Models\MarketQuarter;
use App\Models\Project;
use App\Models\Properties;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->quarter = MarketQuarter::factory()->period(2026, 1)->create();
});

function projectsUrl(string $suffix = ''): string
{
    return panelUrl('/api/market-prices/projects'.$suffix);
}

/**
 * A deed in the test's quarter: a district, an area and a price per metre.
 */
function marketDeed(MarketQuarter $quarter, string $district, float $area, float $rate, string $city = 'جدة'): MarketDeed
{
    return MarketDeed::factory()->for($quarter, 'quarter')->in($district, $city)->sold($area, $rate)->create();
}

function unit(Project $project, ?int $price, ?int $area, ?int $offer = null): Properties
{
    return Properties::factory()->create(['project_id' => $project->id, 'price' => $price, 'area' => $area, 'offer' => $offer]);
}

it('keeps the comparison away from guests', function () {
    $project = Project::factory()->create();

    $this->getJson(projectsUrl())->assertUnauthorized();
    $this->putJson(projectsUrl('/'.$project->id), ['city' => 'جدة', 'district' => 'الصفا'])->assertUnauthorized();

    expect($project->fresh()->market_district_key)->toBeNull();
});

it('reads a project that has not been placed against the whole city', function () {
    $project = Project::factory()->create(['name' => 'برج النهضة']);
    unit($project, 600000, 150);
    unit($project, 720000, 180);

    foreach ([3000, 3500, 4000, 4500, 5000] as $rate) {
        marketDeed($this->quarter, 'الصفا', 160, $rate);
    }
    marketDeed($this->quarter, 'الصفا', 900, 1000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertSuccessful()
        ->assertJsonPath('data.city.label', 'جدة')
        ->assertJsonPath('data.districts', [['key' => 'الصفا', 'label' => 'الصفا']])
        ->assertJsonPath('data.projects.0.name', 'برج النهضة')
        ->assertJsonPath('data.projects.0.district', null)
        ->assertJsonPath('data.projects.0.units', 2)
        ->assertJsonPath('data.projects.0.area_min', 150)
        ->assertJsonPath('data.projects.0.area_max', 180)
        // Both units ask 4,000 a metre.
        ->assertJsonPath('data.projects.0.rate', 4000)
        // 150 less a fifth to 180 plus a fifth: the 900 m² plot is not a comparable.
        ->assertJsonPath('data.projects.0.market.area_from', 120)
        ->assertJsonPath('data.projects.0.market.area_to', 216)
        ->assertJsonPath('data.projects.0.market.district', null)
        ->assertJsonPath('data.projects.0.market.city.deals', 5)
        ->assertJsonPath('data.projects.0.market.city.median', 4000)
        ->assertJsonPath('data.projects.0.market.city.lower', 3500)
        ->assertJsonPath('data.projects.0.market.city.upper', 4500);
});

it('places a project in a district and reads it against that district', function () {
    $project = Project::factory()->create();
    unit($project, 600000, 150);

    marketDeed($this->quarter, 'الصفا', 150, 5000);
    marketDeed($this->quarter, 'الصفا', 160, 5200);
    marketDeed($this->quarter, 'الواحة', 150, 3000);

    $this->actingAs($this->admin, 'admin')
        ->putJson(projectsUrl('/'.$project->id), ['city' => 'جدة', 'district' => 'الصفا'])
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.district', 'الصفا')
        ->assertJsonPath('data.projects.0.district_label', 'الصفا')
        ->assertJsonPath('data.projects.0.market.district.deals', 2)
        ->assertJsonPath('data.projects.0.market.district.median', 5100)
        ->assertJsonPath('data.projects.0.market.city.deals', 3);

    expect($project->fresh()->market_city_key)->toBe('جده')
        ->and($project->fresh()->market_district_key)->toBe('الصفا');
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

it('prices a unit by what is actually asked for it', function () {
    $project = Project::factory()->create();
    unit($project, 550000, 100, offer: 500000);
    unit($project, 400000, 100);
    unit($project, null, 100);
    unit($project, 400000, null);
    unit($project, 0, 0);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units', 2)
        ->assertJsonPath('data.projects.0.unpriced_units', 3)
        ->assertJsonPath('data.projects.0.price_min', 400000)
        ->assertJsonPath('data.projects.0.price_max', 500000)
        // 5,000 and 4,000 a metre.
        ->assertJsonPath('data.projects.0.rate', 4500);
});

it('lists a project with nothing priced and compares it with nothing', function () {
    Project::factory()->create(['name' => 'أرض فضاء']);
    marketDeed($this->quarter, 'الصفا', 150, 5000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.units', 0)
        ->assertJsonPath('data.projects.0.rate', null)
        ->assertJsonPath('data.projects.0.market', null);
});

it('compares with the latest four quarters only', function () {
    $project = Project::factory()->create();
    unit($project, 600000, 150);

    foreach ([[2024, 4, 1000], [2025, 1, 2000], [2025, 2, 3000], [2025, 3, 4000], [2025, 4, 5000]] as [$year, $quarter, $rate]) {
        marketDeed(MarketQuarter::factory()->period($year, $quarter)->create(), 'الصفا', 150, $rate);
    }
    marketDeed($this->quarter, 'الصفا', 150, 6000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.market.city.deals', 4)
        ->assertJsonPath('data.projects.0.market.city.from', '2025-2')
        ->assertJsonPath('data.projects.0.market.city.to', '2026-1')
        ->assertJsonPath('data.projects.0.market.city.median', 4500);
});

it('does not compare a project placed in another city with this one', function () {
    $project = Project::factory()->create(['market_city_key' => 'الرياض', 'market_district_key' => 'العليا']);
    unit($project, 600000, 150);
    marketDeed($this->quarter, 'الصفا', 150, 5000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.elsewhere', true)
        ->assertJsonPath('data.projects.0.district', null)
        ->assertJsonPath('data.projects.0.market.district', null);
});

it('reports a market with no comparable deeds as empty, not as zero', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);
    unit($project, 600000, 150);
    marketDeed($this->quarter, 'الصفا', 900, 1000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(projectsUrl())
        ->assertJsonPath('data.projects.0.market.district.deals', 0)
        ->assertJsonPath('data.projects.0.market.district.median', null);
});

it('keeps the market placement out of what the public site says about a project', function () {
    $project = Project::factory()->create(['market_city_key' => 'جده', 'market_district_key' => 'الصفا']);

    $this->get(route('project', $project))->assertOk()->assertDontSee('market_district_key');
});
