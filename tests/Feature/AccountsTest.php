<?php

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountTask;
use App\Models\Admin;
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
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $stored = DB::table('accounts')->where('id', $account->id)->value('password');

    expect($stored)->not->toContain('TopSecret!23')
        ->and($account->fresh()->password)->toBe('TopSecret!23');
});

it('keeps the password out of the model array form', function () {
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    expect($account->toArray())->not->toHaveKey('password');
});

// ---- listing -------------------------------------------------------------

it('never sends the password in the account listing', function () {
    Account::factory()->create(['password' => 'TopSecret!23']);

    $response = $this->actingAs($this->admin, 'admin')->getJson(apiUrl('accounts'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('TopSecret!23');
    $response->assertJsonPath('data.0.has_password', true)
        ->assertJsonMissingPath('data.0.password');
});

it('refuses the listing to guests', function () {
    $this->getJson(apiUrl('accounts'))->assertUnauthorized();
});

// ---- creation ------------------------------------------------------------

it('leaves a new account with an empty checklist', function () {
    TaskTemplate::factory()->create(['title' => 'تفعيل التحقق بخطوتين']);
    TaskTemplate::factory()->create(['title' => 'ربط البريد الرسمي']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts'), ['name' => 'إنستغرام', 'identifier' => 'nahda_realestate'])
        ->assertCreated()
        ->assertJsonCount(0, 'data.tasks');

    expect(Account::query()->first()->tasks)->toBeEmpty();
});

it('pulls the templates in only when asked from the card', function () {
    TaskTemplate::factory()->create(['title' => 'تفعيل التحقق بخطوتين', 'sort_order' => 1]);
    TaskTemplate::factory()->create(['title' => 'ربط البريد الرسمي', 'sort_order' => 2]);

    $account = Account::factory()->create();
    expect($account->tasks)->toBeEmpty();

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/apply-templates'))
        ->assertOk()
        ->assertJsonCount(2, 'data.tasks');

    expect($account->fresh()->tasks->pluck('title')->all())
        ->toBe(['تفعيل التحقق بخطوتين', 'ربط البريد الرسمي']);
});

it('leaves the stored password alone when an edit omits it', function () {
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('accounts/'.$account->id), ['name' => 'اسم جديد'])
        ->assertOk();

    expect($account->fresh()->password)->toBe('TopSecret!23')
        ->and($account->fresh()->name)->toBe('اسم جديد');
});

it('deletes an account together with its tasks', function () {
    $account = Account::factory()->create();
    AccountTask::factory()->count(3)->create(['account_id' => $account->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(apiUrl('accounts/'.$account->id))
        ->assertOk();

    expect(AccountTask::query()->count())->toBe(0);
});

// ---- reveal --------------------------------------------------------------

it('hands over the password once the right pin is given', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertOk()
        ->assertJsonPath('data.secret', 'TopSecret!23');
});

it('withholds the password when the pin is wrong', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '9999']);

    $response->assertStatus(422);
    expect($response->getContent())->not->toContain('TopSecret!23');
});

it('locks out after five wrong pins so four digits cannot be brute forced', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($this->admin, 'admin')
            ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '9999'])
            ->assertStatus(422);
    }

    // Even the correct pin is refused once the window is exhausted.
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertStatus(429);
});

it('clears the failure count after a successful reveal', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '9999'])
        ->assertStatus(422);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertOk();

    expect(RateLimiter::attempts('reveal-pin:'.$this->admin->id))->toBe(0);
});

it('says so when no pin has been set yet', function () {
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertStatus(409);
});

it('refuses to reveal anything to a guest', function () {
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
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
    $task = AccountTask::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('account-tasks/'.$task->id), ['is_done' => true])
        ->assertOk();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('clears the completion time when a task is unticked', function () {
    $task = AccountTask::factory()->done()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('account-tasks/'.$task->id), ['is_done' => false])
        ->assertOk();

    expect($task->fresh()->completed_at)->toBeNull();
});

it('adds a platform specific task alongside the template ones', function () {
    $account = Account::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/tasks'), ['title' => 'رفع صورة الغلاف'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'رفع صورة الغلاف');
});

it('does not duplicate tasks when templates are re-applied', function () {
    TaskTemplate::factory()->create(['title' => 'مهمة أولى']);
    $account = Account::factory()->create();
    $account->applyTaskTemplates();

    TaskTemplate::factory()->create(['title' => 'مهمة ثانية']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/apply-templates'))
        ->assertOk();

    expect($account->fresh()->tasks->pluck('title')->all())
        ->toBe(['مهمة أولى', 'مهمة ثانية']);
});

// ---- page ----------------------------------------------------------------

it('serves the accounts page to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get('http://panel.localhost/accounts')
        ->assertOk()
        ->assertSee('الحسابات');
});

it('redirects a guest away from the accounts page', function () {
    $this->withoutVite()
        ->get('http://panel.localhost/accounts')
        ->assertRedirect(route('admin.login'));
});

// ---- categories ----------------------------------------------------------

it('creates a category with a colour from the allowed palette', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('account-categories'), ['name' => 'تواصل اجتماعي', 'color' => 'violet'])
        ->assertCreated()
        ->assertJsonPath('data.color', 'violet');
});

it('refuses a colour outside the palette', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('account-categories'), ['name' => 'تصنيف', 'color' => '#ff0000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('color');
});

it('puts an account in several categories at once', function () {
    $social = AccountCategory::factory()->create(['name' => 'تواصل اجتماعي', 'sort_order' => 1]);
    $design = AccountCategory::factory()->create(['name' => 'أدوات تصميم', 'sort_order' => 2]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts'), [
            'name' => 'إنستغرام',
            'identifier' => 'nahda',
            'category_ids' => [$social->id, $design->id],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.categories')
        ->assertJsonPath('data.categories.0.name', 'تواصل اجتماعي')
        ->assertJsonPath('data.categories.1.name', 'أدوات تصميم');
});

// This is the bug the page showed: the row was saved with its category but the
// create response came back without the relation, so the new card rendered bare.
it('returns the categories on the very response that creates the account', function () {
    $category = AccountCategory::factory()->create(['name' => 'تواصل اجتماعي']);

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts'), [
            'name' => 'إنستغرام',
            'identifier' => 'nahda',
            'category_ids' => [$category->id],
        ])->assertCreated();

    expect($response->json('data.categories'))->toHaveCount(1)
        ->and($response->json('data.category_ids'))->toBe([$category->id]);
});

it('sends the categories alongside each account in the listing', function () {
    $category = AccountCategory::factory()->create(['name' => 'أدوات تصميم', 'color' => 'sky']);
    $account = Account::factory()->create();
    $account->categories()->attach($category);

    $this->actingAs($this->admin, 'admin')
        ->getJson(apiUrl('accounts'))
        ->assertOk()
        ->assertJsonPath('data.0.categories.0.name', 'أدوات تصميم')
        ->assertJsonPath('data.0.categories.0.color', 'sky');
});

it('replaces the whole set of categories on edit', function () {
    $first = AccountCategory::factory()->create();
    $second = AccountCategory::factory()->create();
    $third = AccountCategory::factory()->create();

    $account = Account::factory()->create();
    $account->categories()->attach([$first->id, $second->id]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('accounts/'.$account->id), ['category_ids' => [$third->id]])
        ->assertOk();

    expect($account->fresh()->categories->pluck('id')->all())->toBe([$third->id]);
});

it('strips every category when an empty set is sent', function () {
    $category = AccountCategory::factory()->create();
    $account = Account::factory()->create();
    $account->categories()->attach($category);

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('accounts/'.$account->id), ['category_ids' => []])
        ->assertOk();

    expect($account->fresh()->categories)->toBeEmpty();
});

it('leaves the categories alone when an edit does not mention them', function () {
    $category = AccountCategory::factory()->create();
    $account = Account::factory()->create();
    $account->categories()->attach($category);

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('accounts/'.$account->id), ['name' => 'اسم جديد'])
        ->assertOk();

    expect($account->fresh()->categories->pluck('id')->all())->toBe([$category->id]);
});

it('counts how many accounts sit in each category', function () {
    $category = AccountCategory::factory()->create();

    Account::factory()->count(3)->create()->each(fn ($account) => $account->categories()->attach($category));

    $this->actingAs($this->admin, 'admin')
        ->getJson(apiUrl('account-categories'))
        ->assertOk()
        ->assertJsonPath('data.0.accounts_count', 3);
});

it('keeps the accounts when their category is deleted', function () {
    $category = AccountCategory::factory()->create();
    $account = Account::factory()->create();
    $account->categories()->attach($category);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(apiUrl('account-categories/'.$category->id))
        ->assertOk();

    expect(Account::query()->count())->toBe(1)
        ->and($account->fresh()->categories)->toBeEmpty();
});

it('refuses an account pointed at a category that does not exist', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts'), ['name' => 'منصة', 'identifier' => 'x', 'category_ids' => [9999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_ids.0');
});

it('drops the pivot rows when the account itself goes', function () {
    $category = AccountCategory::factory()->create();
    $account = Account::factory()->create();
    $account->categories()->attach($category);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(apiUrl('accounts/'.$account->id))
        ->assertOk();

    expect(DB::table('account_account_category')->count())->toBe(0);
});

// ---- the one hour reveal window ------------------------------------------

it('stops asking for the pin once a correct one opens the window', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $first = Account::factory()->create(['password' => 'FirstSecret']);
    $second = Account::factory()->create(['password' => 'SecondSecret']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$first->id.'/reveal'), ['pin' => '1234'])
        ->assertOk();

    // No pin at all this time.
    $this->postJson(apiUrl('accounts/'.$second->id.'/reveal'))
        ->assertOk()
        ->assertJsonPath('data.secret', 'SecondSecret');
});

it('asks again once the hour is up', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertOk();

    $this->travel(61)->minutes();

    $response = $this->postJson(apiUrl('accounts/'.$account->id.'/reveal'));

    $response->assertStatus(422);
    expect($response->getContent())->not->toContain('TopSecret!23');
});

it('still answers within the hour', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertOk();

    $this->travel(59)->minutes();

    $this->postJson(apiUrl('accounts/'.$account->id.'/reveal'))
        ->assertOk()
        ->assertJsonPath('data.secret', 'TopSecret!23');
});

it('opens the same window for subscriptions', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'AccountSecret']);
    $subscription = \App\Models\Subscription::factory()->create(['payment_account' => 'Visa ****4211']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertOk();

    $this->postJson(apiUrl('subscriptions/'.$subscription->id.'/reveal'))
        ->assertOk()
        ->assertJsonPath('data.secret', 'Visa ****4211');
});

it('refuses a reveal with no pin when no window is open', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'));

    $response->assertStatus(422);
    expect($response->getContent())->not->toContain('TopSecret!23');
});

it('reports when the window closes', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(apiUrl('reveal-pin'))
        ->assertOk()
        ->assertJsonPath('data.unlocked_until', null);

    $this->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])->assertOk();

    $until = $this->getJson(apiUrl('reveal-pin'))->assertOk()->json('data.unlocked_until');

    expect($until)->not->toBeNull()
        ->and(now()->diffInMinutes($until))->toBeGreaterThan(58);
});

it('closes the window when the pin is changed', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $account = Account::factory()->create(['password' => 'TopSecret!23']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts/'.$account->id.'/reveal'), ['pin' => '1234'])
        ->assertOk();

    $this->putJson(apiUrl('reveal-pin'), [
        'pin' => '4321',
        'pin_confirmation' => '4321',
        'current_password' => 'password',
    ])->assertOk();

    $this->postJson(apiUrl('accounts/'.$account->id.'/reveal'))->assertStatus(422);
});

// ---- platform link -------------------------------------------------------

it('stores and returns the platform link', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts'), [
            'name' => 'إنستغرام',
            'identifier' => 'nahda_realestate',
            'url' => 'https://instagram.com/nahda_realestate',
        ])
        ->assertCreated()
        ->assertJsonPath('data.url', 'https://instagram.com/nahda_realestate');
});

it('accepts an account with no link at all', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts'), ['name' => 'منصة', 'identifier' => 'x'])
        ->assertCreated()
        ->assertJsonPath('data.url', null);
});

it('refuses a link that is not a url', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(apiUrl('accounts'), ['name' => 'منصة', 'identifier' => 'x', 'url' => 'ليس رابطاً'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('url');
});

it('lets the link be cleared on edit', function () {
    $account = Account::factory()->create(['url' => 'https://example.com']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(apiUrl('accounts/'.$account->id), ['url' => null])
        ->assertOk();

    expect($account->fresh()->url)->toBeNull();
});
