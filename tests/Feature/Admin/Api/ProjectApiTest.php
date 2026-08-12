<?php

use App\Models\Admin;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

it('rejects guests from the projects api', function () {
    $this->getJson(panelUrl('/api/projects'))->assertUnauthorized();
    $this->postJson(panelUrl('/api/projects'), [])->assertUnauthorized();
});

it('lists projects with their properties count', function () {
    $project = Project::factory()->create();
    Properties::factory()->count(2)->create(['project_id' => $project->id]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/projects'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $project->id)
        ->assertJsonPath('data.0.properties_count', 2);
});

it('creates a project without an image and filters empty guarantees', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects'), [
            'name' => 'مشروع النهضة',
            'description' => 'وصف تفصيلي للمشروع الجديد',
            'location' => 'جدة حي المنار',
            'status' => 'جديد',
            'project_type' => 'فيلا',
            'map_url' => 'https://maps.google.com/example',
            'guarantees' => ['ضمان الهيكل الإنشائي', '   ', ''],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'مشروع النهضة')
        ->assertJsonPath('data.guarantees', ['ضمان الهيكل الإنشائي']);

    $project = Project::query()->sole();
    expect($project->image_url)->toBeNull();
});

it('validates project payloads', function (array $payload, string $errorField) {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorField);
})->with([
    'missing name' => [['description' => 'وصف تفصيلي للمشروع', 'status' => 'جديد', 'project_type' => 'فيلا'], 'name'],
    'short description' => [['name' => 'مشروع', 'description' => 'قصير', 'status' => 'جديد', 'project_type' => 'فيلا'], 'description'],
    'invalid map url' => [['name' => 'مشروع', 'description' => 'وصف تفصيلي للمشروع', 'status' => 'جديد', 'project_type' => 'فيلا', 'map_url' => 'not-a-url'], 'map_url'],
]);

it('updates a project', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelUrl('/api/projects/'.$project->id), [
            'name' => 'اسم محدث للمشروع',
            'description' => 'وصف محدث تفصيلي للمشروع',
            'status' => 'مكتمل',
            'project_type' => 'شقة',
            'guarantees' => ['ضمان جديد'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'اسم محدث للمشروع')
        ->assertJsonPath('data.status', 'مكتمل');

    expect($project->refresh()->guarantees)->toBe(['ضمان جديد']);
});

it('deletes a project without units', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/projects/'.$project->id))
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', true);

    expect(Project::query()->count())->toBe(0);
});

it('refuses to delete a project that still has units', function () {
    $project = Project::factory()->create();
    Properties::factory()->create(['project_id' => $project->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelUrl('/api/projects/'.$project->id))
        ->assertUnprocessable();

    expect(Project::query()->count())->toBe(1);
});

it('does not duplicate a project when the same idempotency key is replayed', function () {
    $payload = [
        'name' => 'مشروع مزامن',
        'description' => 'وصف تفصيلي للمشروع المزامن',
        'status' => 'جديد',
        'project_type' => 'فيلا',
    ];
    $headers = ['Idempotency-Key' => 'op-11111111-1111-1111-1111-111111111111'];

    $first = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects'), $payload, $headers);
    $second = $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects'), $payload, $headers);

    $first->assertCreated();
    $second->assertCreated();
    $second->assertHeader('Idempotency-Replayed', 'true');

    expect(Project::query()->count())->toBe(1)
        ->and($second->json('data.id'))->toBe($first->json('data.id'));
});

it('uploads a project image and deletes the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('uploads/old.webp', 'old-image');
    $project = Project::factory()->create(['image_url' => 'uploads/old.webp']);

    $response = $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/projects/'.$project->id.'/image'), [
            'image' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ], ['Accept' => 'application/json']);

    $response->assertSuccessful();

    $newPath = $response->json('data.image_url');
    expect($newPath)->not->toBe('uploads/old.webp');
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing('uploads/old.webp');
});

it('offers the sold status option in the projects dashboard form', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(panelUrl('/projects-dashboard'))
        ->assertSuccessful()
        ->assertSee('<option value="تم البيع">تم البيع</option>', false);
});

it('returns a browser-usable absolute image url regardless of APP_URL scheme', function () {
    config(['app.url' => 'localhost']);
    $project = Project::factory()->create(['image_url' => 'uploads/photo.webp']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/projects'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.image_full_url', fn (string $url) => str_starts_with($url, 'http') && str_ends_with($url, '/storage/uploads/photo.webp'));
});

it('rejects a non-image upload for the project image', function () {
    Storage::fake('public');
    $project = Project::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/projects/'.$project->id.'/image'), [
            'image' => UploadedFile::fake()->create('document.txt', 10),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

it('uploads a project pdf and deletes the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('presentations/old.pdf', '%PDF-1.4 old');
    $project = Project::factory()->create(['pdf_path' => 'presentations/old.pdf']);

    $response = $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/projects/'.$project->id.'/pdf'), [
            'pdf' => UploadedFile::fake()->createWithContent('brochure.pdf', '%PDF-1.4 fake pdf content'),
        ], ['Accept' => 'application/json']);

    $response->assertSuccessful();

    $newPath = $response->json('data.pdf_path');
    expect($newPath)->not->toBe('presentations/old.pdf');
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing('presentations/old.pdf');
});

it('orders the dashboard list by the chosen order', function () {
    $first = Project::factory()->create(['sort_order' => 3, 'name' => 'ثالث']);
    $second = Project::factory()->create(['sort_order' => 1, 'name' => 'أول']);
    $third = Project::factory()->create(['sort_order' => 2, 'name' => 'ثانٍ']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/projects'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'أول')
        ->assertJsonPath('data.1.name', 'ثانٍ')
        ->assertJsonPath('data.2.name', 'ثالث');

    expect([$first->id, $second->id, $third->id])->toHaveCount(3);
});

it('persists a new order and renumbers positions from one', function () {
    $a = Project::factory()->create(['sort_order' => 1]);
    $b = Project::factory()->create(['sort_order' => 2]);
    $c = Project::factory()->create(['sort_order' => 3]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects/reorder'), ['ids' => [$c->id, $a->id, $b->id]])
        ->assertSuccessful()
        ->assertJsonPath('data.ordered', 3);

    expect($c->fresh()->sort_order)->toBe(1)
        ->and($a->fresh()->sort_order)->toBe(2)
        ->and($b->fresh()->sort_order)->toBe(3);
});

it('validates the reorder payload', function (array $payload, string $field) {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/projects/reorder'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no ids' => [[], 'ids'],
    'unknown project' => [['ids' => [999999]], 'ids.0'],
    'duplicate project' => [['ids' => [1, 1]], 'ids.0'],
]);

it('rejects guests from reordering', function () {
    $project = Project::factory()->create(['sort_order' => 7]);

    $this->postJson(panelUrl('/api/projects/reorder'), ['ids' => [$project->id]])->assertUnauthorized();

    expect($project->fresh()->sort_order)->toBe(7);
});

it('shows projects in the chosen order on the projects page', function () {
    Project::factory()->create(['sort_order' => 2, 'name' => 'المشروع الأخير']);
    Project::factory()->create(['sort_order' => 1, 'name' => 'المشروع الأول']);

    $html = $this->get(route('projects'))->assertSuccessful()->getContent();

    expect(strpos($html, 'المشروع الأول'))->toBeLessThan(strpos($html, 'المشروع الأخير'));
});

it('shows projects in the chosen order on the home page', function () {
    Project::factory()->create(['sort_order' => 2, 'name' => 'مشروع لاحق', 'status' => 'جديد']);
    Project::factory()->create(['sort_order' => 1, 'name' => 'مشروع سابق', 'status' => 'جديد']);

    $html = $this->get(route('welcome'))->assertSuccessful()->getContent();

    expect(strpos($html, 'مشروع سابق'))->toBeLessThan(strpos($html, 'مشروع لاحق'));
});

it('places a newly created project at the top until it is ordered', function () {
    Project::factory()->create(['sort_order' => 1, 'name' => 'قديم']);

    $created = Project::query()->create([
        'name' => 'جديد', 'description' => 'وصف كافٍ للاختبار', 'status' => 'جديد', 'project_type' => 'فيلا',
    ]);

    expect(Project::query()->ordered()->first()->id)->toBe($created->id);
});
