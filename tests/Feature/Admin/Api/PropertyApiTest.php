<?php

use App\Models\Admin;
use App\Models\ImageProperties;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function validPropertyPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'شقة فاخرة',
        'price' => 550000,
        'offer' => null,
        'status' => 'جديد',
        'rooms' => 5,
        'bathrooms' => 3,
        'living_rooms' => 1,
        'mainds_room' => 1,
        'area' => 180,
        'doors' => 2,
        'type' => 'شقة',
        'parkings' => 1,
        'driver_room' => 1,
        'facade' => 'شرقية جنوبية',
        'furniture' => true,
        'unit_youtube' => null,
        'stages_building_youtube' => null,
    ], $overrides);
}

it('rejects guests from the properties api', function () {
    $this->getJson(panelUrl('/api/properties'))->assertUnauthorized();
});

it('lists properties with images and filters by project', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $property = Properties::factory()->create(['project_id' => $projectA->id]);
    Properties::factory()->create(['project_id' => $projectB->id]);
    ImageProperties::query()->create(['url' => 'uploads/a.webp', 'properties_id' => $property->id]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/properties'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/properties?project_id='.$projectA->id))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $property->id)
        ->assertJsonCount(1, 'data.0.images');
});

it('creates a property', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/properties'), validPropertyPayload(['project_id' => $project->id]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'شقة فاخرة')
        ->assertJsonPath('data.furniture', true);

    expect(Properties::query()->count())->toBe(1);
});

it('validates property payloads', function (array $overrides, string $errorField) {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/properties'), validPropertyPayload(array_merge(['project_id' => $project->id], $overrides)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorField);
})->with([
    'missing name' => [['name' => null], 'name'],
    'missing project' => [['project_id' => null], 'project_id'],
    'unknown project' => [['project_id' => 99999], 'project_id'],
    'non numeric price' => [['price' => 'كثير'], 'price'],
    'non numeric offer' => [['offer' => 'abc'], 'offer'],
]);

it('updates a property', function () {
    $property = Properties::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/properties/'.$property->id), validPropertyPayload([
            'project_id' => $property->project_id,
            'name' => 'وحدة محدثة',
            'price' => 700000,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'وحدة محدثة')
        ->assertJsonPath('data.price', 700000);
});

it('does not duplicate a property when the same idempotency key is replayed', function () {
    $project = Project::factory()->create();
    $headers = ['Idempotency-Key' => 'op-22222222-2222-2222-2222-222222222222'];

    $first = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/properties'), validPropertyPayload(['project_id' => $project->id]), $headers);
    $second = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/properties'), validPropertyPayload(['project_id' => $project->id]), $headers);

    $first->assertCreated();
    $second->assertHeader('Idempotency-Replayed', 'true');

    expect(Properties::query()->count())->toBe(1)
        ->and($second->json('data.id'))->toBe($first->json('data.id'));
});

it('deletes a property along with its image files', function () {
    Storage::fake('public');
    Storage::disk('public')->put('uploads/unit.webp', 'image');
    $property = Properties::factory()->create();
    ImageProperties::query()->create(['url' => 'uploads/unit.webp', 'properties_id' => $property->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/properties/'.$property->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true);

    expect(Properties::query()->count())->toBe(0)
        ->and(ImageProperties::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing('uploads/unit.webp');
});

it('uploads multiple property images', function () {
    Storage::fake('public');
    $property = Properties::factory()->create();

    $response = $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/properties/'.$property->id.'/images'), [
            'photos' => [
                UploadedFile::fake()->image('one.jpg', 600, 400),
                UploadedFile::fake()->image('two.jpg', 600, 400),
            ],
        ], ['Accept' => 'application/json']);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.images');

    expect(ImageProperties::query()->where('properties_id', $property->id)->count())->toBe(2);
});

it('uploads a property pdf and deletes the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('presentations/old.pdf', '%PDF-1.4 old');
    $property = Properties::factory()->create(['pdf_path' => 'presentations/old.pdf']);

    $response = $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/properties/'.$property->id.'/pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('unit.pdf', '%PDF-1.4 fake pdf content'),
        ], ['Accept' => 'application/json']);

    $response->assertSuccessful();
    Storage::disk('public')->assertExists($response->json('data.pdf_path'));
    Storage::disk('public')->assertMissing('presentations/old.pdf');
});

it('deletes a single property image file and row', function () {
    Storage::fake('public');
    Storage::disk('public')->put('uploads/single.webp', 'image');
    $property = Properties::factory()->create();
    $image = ImageProperties::query()->create(['url' => 'uploads/single.webp', 'properties_id' => $property->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/property-images/'.$image->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true);

    expect(ImageProperties::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing('uploads/single.webp');
});
