<?php

use App\Models\Admin;
use App\Models\SocialPlatform;
use App\Models\SocialPlatformTask;
use App\Models\TaskTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    RateLimiter::clear('reveal-pin:'.$this->admin->id);
});

function apiUrl(string $path): string
{
    return 'http://panel.localhost/api/'.ltrim($path, '/');
}

// ---- storage -------------------------------------------------------------

it('stores the password encrypted rather than in the clear', function () {
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $stored = DB::table('social_platforms')->where('id', $platform->id)->value('password');

    expect($stored)->not->toContain('TopSecret!23')
        ->and($platform->fresh()->password)->toBe('TopSecret!23');
});

it('keeps the password out of the model array form', function () {
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    expect($platform->toArray())->not->toHaveKey('password');
});

// ---- listing -------------------------------------------------------------

it('never sends the password in the platform listing', function () {
    SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $response = $this->actingAs($this->admin, 'admin')->getJson(apiUrl('social-platforms'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('TopSecret!23');
    $response->assertJsonPath('data.0.has_password', true)
        ->assertJsonMissingPath('data.0.password');
});

it('refuses the listing to guests', function () {
    $this->getJson(apiUrl('social-platforms'))->assertUnauthorized();
});

// ---- creation ------------------------------------------------------------

it('copies the template checklist onto a new platform', function () {
    TaskTemplate::factory()->create(['title' => 'تفعيل التحقق بخطوتين', 'sort_order' => 1]);
    TaskTemplate::factory()->create(['title' => 'ربط البريد الرسمي', 'sort_order' => 2]);

    $response = $this->actingAs($this->admin, 'admin')->postJson(apiUrl('social-platforms'), [
        'name' => 'إنستغرام',
        'identifier' => 'nahda_realestate',
        'password' => 'TopSecret!23',
    ]);

    $response->assertCreated()->assertJsonCount(2, 'data.tasks');

    expect(SocialPlatform::query()->first()->tasks->pluck('title')->all())
        ->toBe(['تفعيل التحقق بخطوتين', 'ربط البريد الرسمي']);
});

it('leaves the stored password alone when an edit omits it', function () {
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('social-platforms/'.$platform->id), ['name' => 'اسم جديد'])
        ->assertOk();

    expect($platform->fresh()->password)->toBe('TopSecret!23')
        ->and($platform->fresh()->name)->toBe('اسم جديد');
});

it('deletes a platform together with its tasks', function () {
    $platform = SocialPlatform::factory()->create();
    SocialPlatformTask::factory()->count(3)->create(['social_platform_id' => $platform->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(apiUrl('social-platforms/'.$platform->id))
        ->assertOk();

    expect(SocialPlatformTask::query()->count())->toBe(0);
});

// ---- reveal --------------------------------------------------------------

it('hands over the password once the right pin is given', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '1234'])
        ->assertOk()
        ->assertJsonPath('data.password', 'TopSecret!23');
});

it('withholds the password when the pin is wrong', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '9999']);

    $response->assertStatus(422);
    expect($response->getContent())->not->toContain('TopSecret!23');
});

it('locks out after five wrong pins so four digits cannot be brute forced', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($this->admin, 'admin')
            ->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '9999'])
            ->assertStatus(422);
    }

    // Even the correct pin is refused once the window is exhausted.
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '1234'])
        ->assertStatus(429);
});

it('clears the failure count after a successful reveal', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '9999'])
        ->assertStatus(422);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '1234'])
        ->assertOk();

    expect(RateLimiter::attempts('reveal-pin:'.$this->admin->id))->toBe(0);
});

it('says so when no pin has been set yet', function () {
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '1234'])
        ->assertStatus(409);
});

it('refuses to reveal anything to a guest', function () {
    $platform = SocialPlatform::factory()->create(['password' => 'TopSecret!23']);

    $this->postJson(apiUrl('social-platforms/'.$platform->id.'/reveal'), ['pin' => '1234'])
        ->assertUnauthorized();
});

// ---- pin management ------------------------------------------------------

it('stores the pin hashed, never in the clear', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('reveal-pin'), [
            'pin' => '4321',
            'pin_confirmation' => '4321',
            'current_password' => 'password',
        ])
        ->assertOk();

    $stored = DB::table('admins')->where('id', $this->admin->id)->value('reveal_pin');

    expect($stored)->not->toBe('4321')
        ->and(Hash::check('4321', $stored))->toBeTrue();
});

it('will not set a pin without the account password', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('reveal-pin'), [
            'pin' => '4321',
            'pin_confirmation' => '4321',
            'current_password' => 'wrong-password',
        ])
        ->assertStatus(422);

    expect($this->admin->fresh()->reveal_pin)->toBeNull();
});

it('rejects a pin that is not four digits', function (string $pin) {
    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('reveal-pin'), [
            'pin' => $pin,
            'pin_confirmation' => $pin,
            'current_password' => 'password',
        ])
        ->assertStatus(422);
})->with(['قصير' => '12', 'طويل' => '123456', 'حروف' => 'abcd']);

// ---- tasks ---------------------------------------------------------------

it('stamps the completion time when a task is ticked', function () {
    $task = SocialPlatformTask::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('social-platform-tasks/'.$task->id), ['is_done' => true])
        ->assertOk();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('clears the completion time when a task is unticked', function () {
    $task = SocialPlatformTask::factory()->done()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('social-platform-tasks/'.$task->id), ['is_done' => false])
        ->assertOk();

    expect($task->fresh()->completed_at)->toBeNull();
});

it('adds a platform specific task alongside the template ones', function () {
    $platform = SocialPlatform::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/tasks'), ['title' => 'رفع صورة الغلاف'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'رفع صورة الغلاف');
});

it('does not duplicate tasks when templates are re-applied', function () {
    TaskTemplate::factory()->create(['title' => 'مهمة أولى']);
    $platform = SocialPlatform::factory()->create();
    $platform->applyTaskTemplates();

    TaskTemplate::factory()->create(['title' => 'مهمة ثانية']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('social-platforms/'.$platform->id.'/apply-templates'))
        ->assertOk();

    expect($platform->fresh()->tasks->pluck('title')->all())
        ->toBe(['مهمة أولى', 'مهمة ثانية']);
});

// ---- page ----------------------------------------------------------------

it('serves the social accounts page to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get('http://panel.localhost/social-accounts')
        ->assertOk()
        ->assertSee('حسابات التواصل');
});

it('redirects a guest away from the social accounts page', function () {
    $this->withoutVite()
        ->get('http://panel.localhost/social-accounts')
        ->assertRedirect(route('admin.login'));
});
