<?php

use App\Models\Admin;
use App\Models\ManualMarketUnit;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function manualUnitsUrl(string $suffix = ''): string
{
    return panelUrl('/api/market-prices/manual-units'.$suffix);
}

it('keeps hand-entered units away from guests', function () {
    $unit = ManualMarketUnit::factory()->create();

    $this->getJson(manualUnitsUrl())->assertUnauthorized();
    $this->postJson(manualUnitsUrl(), [])->assertUnauthorized();
    $this->deleteJson(manualUnitsUrl('/'.$unit->id))->assertUnauthorized();

    expect(ManualMarketUnit::query()->count())->toBe(1);
});

it('lists the units an admin has entered, newest first', function () {
    ManualMarketUnit::factory()->create(['name' => 'الأولى']);
    ManualMarketUnit::factory()->create(['name' => 'الثانية']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(manualUnitsUrl())
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'الثانية')
        ->assertJsonPath('data.1.name', 'الأولى');
});

it('stores a unit with the price per metre the comparison reads', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), [
            'name' => 'شقة ٣ غرف',
            'project_name' => 'مشروع الواحة',
            'city' => 'جدة',
            'district' => 'الصفا',
            'kind' => 'شقة',
            'area' => 150,
            'price' => 600000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'شقة ٣ غرف')
        ->assertJsonPath('data.project_name', 'مشروع الواحة')
        ->assertJsonPath('data.city', 'جدة')
        ->assertJsonPath('data.district', 'الصفا')
        ->assertJsonPath('data.kind', 'شقة')
        ->assertJsonPath('data.area', 150)
        ->assertJsonPath('data.price', 600000)
        ->assertJsonPath('data.rate', 4000);

    $stored = ManualMarketUnit::query()->sole();

    expect($stored->city_key)->toBe('جده')
        ->and($stored->district_key)->toBe('الصفا')
        ->and($stored->price_per_meter)->toBe(4000.0);
});

it('stores a unit with no project name', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), [
            'name' => 'دور مستقل',
            'project_name' => '',
            'city' => 'جدة',
            'district' => 'الواحة',
            'kind' => 'دور',
            'area' => 200,
            'price' => 800000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.project_name', null);
});

it('rejects a unit that cannot be compared', function (array $payload, string $field) {
    $this->actingAs($this->admin, 'admin')
        ->postJson(manualUnitsUrl(), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(ManualMarketUnit::query()->count())->toBe(0);
})->with([
    'no name' => [['city' => 'جدة', 'district' => 'الصفا', 'kind' => 'شقة', 'area' => 150, 'price' => 600000], 'name'],
    'no district' => [['name' => 'شقة', 'city' => 'جدة', 'kind' => 'شقة', 'area' => 150, 'price' => 600000], 'district'],
    'a kind the market does not price' => [['name' => 'شقة', 'city' => 'جدة', 'district' => 'الصفا', 'kind' => 'قصر', 'area' => 150, 'price' => 600000], 'kind'],
    'no area' => [['name' => 'شقة', 'city' => 'جدة', 'district' => 'الصفا', 'kind' => 'شقة', 'price' => 600000], 'area'],
    'a price of nothing' => [['name' => 'شقة', 'city' => 'جدة', 'district' => 'الصفا', 'kind' => 'شقة', 'area' => 150, 'price' => 0], 'price'],
]);

it('deletes a unit an admin entered', function () {
    $unit = ManualMarketUnit::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(manualUnitsUrl('/'.$unit->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true)
        ->assertJsonPath('data.id', $unit->id);

    expect(ManualMarketUnit::query()->count())->toBe(0);
});
