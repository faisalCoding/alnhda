<?php

use App\Models\Admin;
use App\Models\AdvertisingLicence;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->travelTo('2026-08-20 10:00:00');
});

function licenceApi(string $path = ''): string
{
    return rtrim('http://panel.localhost/api/advertising-licences/'.ltrim($path, '/'), '/');
}

// ---- the page ------------------------------------------------------------

it('serves the licences page to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get('http://panel.localhost/advertising-licences')
        ->assertOk()
        ->assertSee('الرخص الإعلانية');
});

it('keeps the licences page and api away from guests', function () {
    $this->withoutVite()->get('http://panel.localhost/advertising-licences')->assertRedirect(route('admin.login'));
    $this->getJson(licenceApi())->assertUnauthorized();
});

// ---- naming the unit -----------------------------------------------------

it('links a licence to a unit on file and reads its name from it', function () {
    $project = Project::factory()->create(['name' => 'مشروع الواحة']);
    $unit = Properties::factory()->create(['name' => 'فيلا 12', 'project_id' => $project->id]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(licenceApi(), [
            'properties_id' => $unit->id,
            'licence_number' => '7200000001',
            'expires_on' => '2027-01-15',
        ])
        ->assertCreated()
        ->assertJsonPath('data.unit_label', 'فيلا 12')
        ->assertJsonPath('data.project_name', 'مشروع الواحة');
});

it('accepts a unit typed by hand', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(licenceApi(), ['unit_name' => 'وحدة خارج النظام', 'licence_number' => '7200000002'])
        ->assertCreated()
        ->assertJsonPath('data.unit_label', 'وحدة خارج النظام')
        ->assertJsonPath('data.properties_id', null);
});

it('insists on one of the two ways of naming a unit', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(licenceApi(), ['licence_number' => '7200000003'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('unit_name');
});

it('refuses a unit that does not exist', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(licenceApi(), ['properties_id' => 9999, 'licence_number' => '72'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('properties_id');
});

it('keeps the licence when its unit is deleted, falling back to the typed name', function () {
    $unit = Properties::factory()->create(['name' => 'فيلا 12']);
    $licence = AdvertisingLicence::factory()->create([
        'properties_id' => $unit->id,
        'unit_name' => 'فيلا 12 (نسخة يدوية)',
    ]);

    $unit->delete();

    expect(AdvertisingLicence::query()->count())->toBe(1)
        ->and($licence->fresh()->properties_id)->toBeNull()
        ->and($licence->fresh()->unitLabel())->toBe('فيلا 12 (نسخة يدوية)');
});

// ---- the licence number --------------------------------------------------

it('will not record a licence without its number', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(licenceApi(), ['unit_name' => 'وحدة'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('licence_number');
});

// ---- expiry --------------------------------------------------------------

it('counts the days left before a licence lapses', function () {
    expect(AdvertisingLicence::factory()->expiringIn(20)->create()->daysUntilExpiry())->toBe(20);
});

it('reports a lapsed licence as a negative count', function () {
    expect(AdvertisingLicence::factory()->expiringIn(-7)->create()->daysUntilExpiry())->toBe(-7);
});

it('reports no count at all when there is no expiry date', function () {
    // null * -1 is 0 in PHP, which would otherwise read as "expires today".
    expect(AdvertisingLicence::factory()->withoutExpiry()->create()->daysUntilExpiry())->toBeNull();
});

it('lists the soonest to lapse first and undated ones last', function () {
    AdvertisingLicence::factory()->expiringIn(90)->create(['unit_name' => 'بعيدة']);
    AdvertisingLicence::factory()->withoutExpiry()->create(['unit_name' => 'بلا تاريخ']);
    AdvertisingLicence::factory()->expiringIn(3)->create(['unit_name' => 'وشيكة']);

    $order = collect($this->actingAs($this->admin, 'admin')
        ->getJson(licenceApi())->assertOk()->json('data'))->pluck('unit_label')->all();

    expect($order)->toBe(['وشيكة', 'بعيدة', 'بلا تاريخ']);
});

// ---- editing -------------------------------------------------------------

it('moves a licence from a typed unit onto one on file', function () {
    $unit = Properties::factory()->create(['name' => 'فيلا 12']);
    $licence = AdvertisingLicence::factory()->create(['unit_name' => 'مكتوبة يدوياً']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(licenceApi((string) $licence->id), ['properties_id' => $unit->id, 'unit_name' => null])
        ->assertOk()
        ->assertJsonPath('data.unit_label', 'فيلا 12');
});

it('deletes a licence', function () {
    $licence = AdvertisingLicence::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(licenceApi((string) $licence->id))
        ->assertOk();

    expect(AdvertisingLicence::query()->count())->toBe(0);
});
