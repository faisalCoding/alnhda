<?php

use App\Models\Admin;
use App\Models\ManualMarketUnit;
use App\Models\Project;
use App\Models\Properties;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function competitorsUrl(?Properties $unit = null, ?ManualMarketUnit $competitor = null): string
{
    if ($unit === null) {
        return panelUrl('/api/market-prices/competitors');
    }

    return panelUrl('/api/market-prices/units/'.$unit->id.'/competitors'.($competitor ? '/'.$competitor->id : ''));
}

/**
 * One of the company's units, in a project of its own unless given one.
 */
function competingUnit(?int $price, ?int $area, array $attributes = [], ?Project $project = null): Properties
{
    return Properties::factory()->create([
        'project_id' => ($project ?? Project::factory()->create())->id,
        'price' => $price,
        'area' => $area,
        'offer' => null,
        'type' => 'وحدة عقارية',
        ...$attributes,
    ]);
}

it('keeps competitors away from guests', function () {
    $unit = competingUnit(600000, 150);
    $offer = ManualMarketUnit::factory()->create();

    $this->getJson(competitorsUrl())->assertUnauthorized();
    $this->postJson(competitorsUrl($unit), ['manual_market_unit_id' => $offer->id])->assertUnauthorized();
    $this->deleteJson(competitorsUrl($unit, $offer))->assertUnauthorized();

    expect($unit->competitors()->count())->toBe(0);
});

it('chooses a market unit as a competitor and shows it under the unit with its details', function () {
    $project = Project::factory()->create(['name' => 'برج النهضة']);
    $unit = competingUnit(600000, 150, ['market_type' => 'شقة'], $project);
    $offer = ManualMarketUnit::factory()
        ->offered('الصفا', 160, 5000)
        ->listed('aqar', '6816659')
        ->create(['bedrooms' => 4, 'facade' => 'غربية', 'source_url' => 'https://sa.aqar.fm/6816659']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(competitorsUrl($unit), ['manual_market_unit_id' => $offer->id, 'note' => 'نفس الشارع'])
        ->assertCreated()
        ->assertJsonPath('data.competitors', 1);

    $this->actingAs($this->admin, 'admin')
        ->getJson(competitorsUrl().'?city=جدة')
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.name', 'برج النهضة')
        ->assertJsonPath('data.projects.0.units.0.id', $unit->id)
        ->assertJsonPath('data.projects.0.units.0.kind', 'شقة')
        ->assertJsonPath('data.projects.0.units.0.rate', 4000)
        ->assertJsonCount(1, 'data.projects.0.units.0.competitors')
        ->assertJsonPath('data.projects.0.units.0.competitors.0.id', $offer->id)
        ->assertJsonPath('data.projects.0.units.0.competitors.0.listing_id', '6816659')
        ->assertJsonPath('data.projects.0.units.0.competitors.0.bedrooms', 4)
        ->assertJsonPath('data.projects.0.units.0.competitors.0.facade', 'غربية')
        ->assertJsonPath('data.projects.0.units.0.competitors.0.source_url', 'https://sa.aqar.fm/6816659')
        ->assertJsonPath('data.projects.0.units.0.competitors.0.note', 'نفس الشارع')
        // 4,000 a metre against 5,000: the unit asks a fifth less.
        ->assertJsonPath('data.projects.0.units.0.competitors.0.gap', -20)
        ->assertJsonPath('data.projects.0.units.0.summary.properties', 1)
        ->assertJsonPath('data.projects.0.units.0.summary.gap', -20);
});

it('keeps a competitor chosen twice once, with the newer note', function () {
    $unit = competingUnit(600000, 150);
    $offer = ManualMarketUnit::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(competitorsUrl($unit), ['manual_market_unit_id' => $offer->id, 'note' => 'أولى'])
        ->assertCreated();

    $this->actingAs($this->admin, 'admin')
        ->postJson(competitorsUrl($unit), ['manual_market_unit_id' => $offer->id, 'note' => ''])
        ->assertCreated();

    expect($unit->competitors()->count())->toBe(1)
        ->and($unit->competitors()->first()->pivot->note)->toBeNull();
});

it('lets one market unit compete with several of our units', function () {
    $first = competingUnit(600000, 150);
    $second = competingUnit(700000, 150);
    $offer = ManualMarketUnit::factory()->create();

    foreach ([$first, $second] as $unit) {
        $this->actingAs($this->admin, 'admin')
            ->postJson(competitorsUrl($unit), ['manual_market_unit_id' => $offer->id])
            ->assertCreated();
    }

    expect($offer->competingWith()->count())->toBe(2);
});

it('refuses a competitor that is not a market unit', function (array $payload, string $field) {
    $unit = competingUnit(600000, 150);

    $this->actingAs($this->admin, 'admin')
        ->postJson(competitorsUrl($unit), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($unit->competitors()->count())->toBe(0);
})->with([
    'nothing chosen' => [[], 'manual_market_unit_id'],
    'no such unit' => [['manual_market_unit_id' => 999999], 'manual_market_unit_id'],
    'a note too long' => [['manual_market_unit_id' => 0, 'note' => str_repeat('م', 501)], 'note'],
]);

it('answers not found for a unit of ours that does not exist', function () {
    $offer = ManualMarketUnit::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/market-prices/units/999999/competitors'), ['manual_market_unit_id' => $offer->id])
        ->assertNotFound();
});

it('takes a competitor off a unit and keeps the market unit', function () {
    $unit = competingUnit(600000, 150);
    $kept = ManualMarketUnit::factory()->create();
    $removed = ManualMarketUnit::factory()->create();
    $unit->competitors()->attach([$kept->id, $removed->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(competitorsUrl($unit, $removed))
        ->assertSuccessful()
        ->assertJsonPath('data.competitors', 1);

    expect($unit->competitors()->pluck('manual_market_units.id')->all())->toBe([$kept->id])
        ->and(ManualMarketUnit::query()->whereKey($removed->id)->exists())->toBeTrue();
});

it('lets the link go when either side is deleted', function () {
    $unit = competingUnit(600000, 150);
    $offer = ManualMarketUnit::factory()->create();
    $other = ManualMarketUnit::factory()->create();
    $unit->competitors()->attach([$offer->id, $other->id]);

    $offer->delete();
    expect($unit->competitors()->count())->toBe(1);

    $unit->delete();
    expect($other->competingWith()->count())->toBe(0);
});

it('counts a property listed on several sites once in the summary', function () {
    $unit = competingUnit(600000, 150, ['market_type' => 'شقة']);
    $aqar = ManualMarketUnit::factory()->offered('الصفا', 150, 5000)->listed('aqar', '1', ['bayut:2'], 'group-1')->create();
    $bayut = ManualMarketUnit::factory()->offered('الصفا', 150, 5000)->listed('bayut', '2', ['aqar:1'], 'group-1')->create();
    $cheaper = ManualMarketUnit::factory()->offered('الصفا', 150, 3000)->create();
    $unit->competitors()->attach([$aqar->id, $bayut->id, $cheaper->id]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(competitorsUrl())
        ->assertSuccessful()
        // Every listing chosen is shown, cheapest a metre first…
        ->assertJsonCount(3, 'data.projects.0.units.0.competitors')
        ->assertJsonPath('data.projects.0.units.0.competitors.0.id', $cheaper->id)
        // …but the property listed twice is one of two properties, not two of three.
        ->assertJsonPath('data.projects.0.units.0.summary.properties', 2)
        ->assertJsonPath('data.projects.0.units.0.summary.median', 4000)
        ->assertJsonPath('data.projects.0.units.0.summary.gap', 0);
});

it('measures a villa by its land, as the listings are', function () {
    $unit = competingUnit(1800000, 400, ['market_type' => 'فيلا', 'land_area' => 300]);
    $offer = ManualMarketUnit::factory()->offered('المنار', 300, 6000, 'فيلا')->create();
    $unit->competitors()->attach($offer->id);

    $this->actingAs($this->admin, 'admin')
        ->getJson(competitorsUrl())
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.units.0.basis', 'land')
        ->assertJsonPath('data.projects.0.units.0.land_area', 300)
        ->assertJsonPath('data.projects.0.units.0.rate', 6000)
        ->assertJsonPath('data.projects.0.units.0.competitors.0.gap', 0);
});

it('shows a unit with no price or no competitors without a gap', function () {
    $project = Project::factory()->create();
    $unpriced = competingUnit(null, 150, project: $project);
    $unpriced->competitors()->attach(ManualMarketUnit::factory()->create()->id);
    competingUnit(600000, 150, project: $project);

    $this->actingAs($this->admin, 'admin')
        ->getJson(competitorsUrl())
        ->assertSuccessful()
        ->assertJsonPath('data.projects.0.units.0.rate', null)
        ->assertJsonPath('data.projects.0.units.0.competitors.0.gap', null)
        ->assertJsonPath('data.projects.0.units.0.summary.gap', null)
        ->assertJsonPath('data.projects.0.units.1.competitors', [])
        ->assertJsonPath('data.projects.0.units.1.summary', null);
});
