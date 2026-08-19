<?php

use App\Models\Account;
use App\Models\Admin;
use App\Models\Backlink;
use App\Models\MarketingChecklist;
use App\Models\MarketingChecklistItem;
use App\Models\MarketingMethod;
use App\Models\Subscription;
use App\Models\UsefulLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    RateLimiter::clear('reveal-pin:'.$this->admin->id);
});

function panelApi(string $path): string
{
    return 'http://panel.localhost/api/'.ltrim($path, '/');
}

// ---- pages ---------------------------------------------------------------

it('serves each reference page to an admin', function (string $path, string $heading) {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get('http://panel.localhost/'.$path)
        ->assertOk()
        ->assertSee($heading);
})->with([
    'subscriptions' => ['subscriptions', 'الاشتراكات والمدفوعات'],
    'useful links' => ['useful-links', 'روابط مهمة'],
    'backlinks' => ['backlinks', 'الروابط الخلفية'],
    'marketing tools' => ['marketing-tools', 'أدوات التسويق'],
]);

it('keeps each reference page away from guests', function (string $path) {
    $this->withoutVite()
        ->get('http://panel.localhost/'.$path)
        ->assertRedirect(route('admin.login'));
})->with(['subscriptions', 'useful-links', 'backlinks', 'marketing-tools']);

it('keeps each reference api away from guests', function (string $path) {
    $this->getJson(panelApi($path))->assertUnauthorized();
})->with(['subscriptions', 'useful-links', 'backlinks', 'marketing-methods', 'marketing-checklists']);

// ---- subscriptions -------------------------------------------------------

it('encrypts the payment account rather than storing it plainly', function () {
    $subscription = Subscription::factory()->create(['payment_account' => 'Visa ****4211']);

    $stored = DB::table('subscriptions')->where('id', $subscription->id)->value('payment_account');

    expect($stored)->not->toContain('4211')
        ->and($subscription->fresh()->payment_account)->toBe('Visa ****4211');
});

it('never sends the payment account in the subscription listing', function () {
    Subscription::factory()->create(['payment_account' => 'Visa ****4211']);

    $response = $this->actingAs($this->admin, 'admin')->getJson(panelApi('subscriptions'));

    $response->assertOk()->assertJsonPath('data.0.has_payment_account', true);
    expect($response->getContent())->not->toContain('4211');
});

it('reveals the payment account only with the right pin', function () {
    $this->admin->forceFill(['reveal_pin' => '1234'])->save();
    $subscription = Subscription::factory()->create(['payment_account' => 'Visa ****4211']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions/'.$subscription->id.'/reveal'), ['pin' => '9999'])
        ->assertStatus(422);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions/'.$subscription->id.'/reveal'), ['pin' => '1234'])
        ->assertOk()
        ->assertJsonPath('data.secret', 'Visa ****4211');
});

it('counts the days left until a subscription expires', function () {
    $subscription = Subscription::factory()->expiringIn(10)->create();

    expect($subscription->daysUntilExpiry())->toBe(10);
});

it('reports a lapsed subscription as a negative day count', function () {
    $subscription = Subscription::factory()->expiringIn(-5)->create();

    expect($subscription->daysUntilExpiry())->toBe(-5);
});

it('lists the soonest expiry first', function () {
    Subscription::factory()->expiringIn(90)->create(['name' => 'بعيد']);
    Subscription::factory()->expiringIn(3)->create(['name' => 'قريب']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelApi('subscriptions'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'قريب');
});

it('creates a subscription with all of its fields', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), [
            'name' => 'Canva Pro',
            'expires_on' => '2027-01-15',
            'payment_account' => 'Visa ****4211',
            'note' => 'يُجدَّد سنوياً',
        ])
        ->assertCreated()
        ->assertJsonPath('data.expires_on', '2027-01-15')
        ->assertJsonPath('data.has_payment_account', true);
});

// ---- useful links --------------------------------------------------------

it('creates a useful link', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('useful-links'), [
            'name' => 'Google Search Console',
            'url' => 'https://search.google.com/search-console',
            'benefit' => 'متابعة الفهرسة وأداء البحث',
        ])
        ->assertCreated()
        ->assertJsonPath('data.benefit', 'متابعة الفهرسة وأداء البحث');
});

it('rejects a useful link whose url is not a url', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('useful-links'), ['name' => 'موقع', 'url' => 'ليس رابطاً'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('url');
});

it('deletes a useful link', function () {
    $link = UsefulLink::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelApi('useful-links/'.$link->id))
        ->assertOk();

    expect(UsefulLink::query()->count())->toBe(0);
});

// ---- backlinks -----------------------------------------------------------

it('creates a backlink with its target page and visit count', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('backlinks'), [
            'name' => 'دليل عقاري',
            'url' => 'https://example.com/listing',
            'target_url' => 'https://kayanalnhda.sa/projects',
            'visits' => 120,
        ])
        ->assertCreated()
        ->assertJsonPath('data.visits', 120)
        ->assertJsonPath('data.target_url', 'https://kayanalnhda.sa/projects');
});

it('lists backlinks with the most visited first', function () {
    Backlink::factory()->create(['name' => 'قليل', 'visits' => 5]);
    Backlink::factory()->create(['name' => 'كثير', 'visits' => 900]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelApi('backlinks'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'كثير');
});

it('refuses a negative visit count', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('backlinks'), [
            'name' => 'موقع',
            'url' => 'https://example.com',
            'visits' => -3,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('visits');
});

// ---- marketing tools -----------------------------------------------------

it('builds a checklist from chosen marketing methods', function () {
    $first = MarketingMethod::factory()->create(['title' => 'إعلانات جوجل', 'sort_order' => 1]);
    $second = MarketingMethod::factory()->create(['title' => 'محتوى إنستغرام', 'sort_order' => 2]);
    MarketingMethod::factory()->create(['title' => 'طريقة غير مختارة']);

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('marketing-checklists'), [
            'name' => 'حملة مشروع الواحة',
            'method_ids' => [$first->id, $second->id],
        ]);

    $response->assertCreated()->assertJsonPath('data.name', 'حملة مشروع الواحة');

    expect(MarketingChecklist::query()->first()->items->pluck('title')->all())
        ->toBe(['إعلانات جوجل', 'محتوى إنستغرام']);
});

it('creates an empty checklist when no methods are chosen', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('marketing-checklists'), ['name' => 'قائمة فارغة'])
        ->assertCreated()
        ->assertJsonCount(0, 'data.items');
});

it('does not duplicate a method already in the checklist', function () {
    $method = MarketingMethod::factory()->create(['title' => 'إعلانات جوجل']);
    $checklist = MarketingChecklist::factory()->create();
    $checklist->addMethods([$method->id]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('marketing-checklists/'.$checklist->id.'/methods'), ['method_ids' => [$method->id]])
        ->assertOk();

    expect($checklist->fresh()->items)->toHaveCount(1);
});

it('leaves an existing checklist untouched when its source method is renamed', function () {
    $method = MarketingMethod::factory()->create(['title' => 'النص الأصلي']);
    $checklist = MarketingChecklist::factory()->create();
    $checklist->addMethods([$method->id]);

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelApi('marketing-methods/'.$method->id), ['title' => 'نص معدّل'])
        ->assertOk();

    expect($checklist->fresh()->items->first()->title)->toBe('النص الأصلي');
});

it('stamps and clears the completion time on a checklist item', function () {
    $item = MarketingChecklistItem::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelApi('marketing-checklist-items/'.$item->id), ['is_done' => true])
        ->assertOk();

    expect($item->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($this->admin, 'admin')
        ->putJson(panelApi('marketing-checklist-items/'.$item->id), ['is_done' => false])
        ->assertOk();

    expect($item->fresh()->completed_at)->toBeNull();
});

it('adds a one off item that is not in the method library', function () {
    $checklist = MarketingChecklist::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('marketing-checklists/'.$checklist->id.'/items'), ['title' => 'مهمة لمرة واحدة'])
        ->assertCreated();

    expect(MarketingMethod::query()->count())->toBe(0)
        ->and($checklist->fresh()->items)->toHaveCount(1);
});

it('deletes a checklist together with its items', function () {
    $checklist = MarketingChecklist::factory()->create();
    MarketingChecklistItem::factory()->count(3)->create(['marketing_checklist_id' => $checklist->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(panelApi('marketing-checklists/'.$checklist->id))
        ->assertOk();

    expect(MarketingChecklistItem::query()->count())->toBe(0);
});

it('refuses a checklist built from a method that does not exist', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('marketing-checklists'), ['name' => 'قائمة', 'method_ids' => [9999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('method_ids.0');
});

// ---- subscription link ---------------------------------------------------

// ---- linking, amounts and dates ------------------------------------------

it('links a record to an account and sends the account back with it', function () {
    $account = Account::factory()->create(['name' => 'إنستغرام']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), [
            'account_id' => $account->id,
            'amount' => 1250.50,
            'paid_on' => '2026-08-01',
        ])
        ->assertCreated()
        ->assertJsonPath('data.display_name', 'إنستغرام')
        ->assertJsonPath('data.amount', '1250.50')
        ->assertJsonPath('data.paid_on', '2026-08-01');
});

it('keeps the record when its linked account is deleted', function () {
    $account = Account::factory()->create();
    $subscription = Subscription::factory()->create(['account_id' => $account->id]);

    $account->delete();

    expect(Subscription::query()->count())->toBe(1)
        ->and($subscription->fresh()->account_id)->toBeNull();
});

it('refuses a link to an account that does not exist', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), ['name' => 'س', 'account_id' => 9999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('account_id');
});

it('refuses a negative amount', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), ['name' => 'س', 'amount' => -10])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');
});

it('calls a record with a renewal date a subscription', function () {
    $subscription = Subscription::factory()->expiringIn(30)->create();

    expect($subscription->isSubscription())->toBeTrue();
});

it('calls a record with no renewal date a payment', function () {
    $payment = Subscription::factory()->payment()->create();

    expect($payment->isSubscription())->toBeFalse();
});

it('flags which kind each record is in the listing', function () {
    Subscription::factory()->expiringIn(30)->create(['name' => 'اشتراك']);
    Subscription::factory()->payment()->create(['name' => 'دفعة']);

    $data = collect($this->actingAs($this->admin, 'admin')
        ->getJson(panelApi('subscriptions'))
        ->assertOk()
        ->json('data'))
        ->pluck('is_subscription', 'name');

    expect($data['اشتراك'])->toBeTrue()
        ->and($data['دفعة'])->toBeFalse();
});

it('records a payment with an amount but no expiry', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), [
            'name' => 'تصميم شعار',
            'amount' => 800,
            'paid_on' => '2026-07-15',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_subscription', false)
        ->assertJsonPath('data.expires_on', null);
});

// ---- fields come from the linked account ---------------------------------

it('takes its name, identifier and link from the account it belongs to', function () {
    $account = Account::factory()->create([
        'name' => 'إنستغرام',
        'identifier' => 'nahda_realestate',
        'url' => 'https://instagram.com/nahda_realestate',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), ['account_id' => $account->id, 'amount' => 300])
        ->assertCreated()
        ->assertJsonPath('data.display_name', 'إنستغرام')
        ->assertJsonPath('data.identifier', 'nahda_realestate')
        ->assertJsonPath('data.url', 'https://instagram.com/nahda_realestate');
});

it('needs a name of its own only when it is not linked', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), ['amount' => 800])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('accepts a linked record with no name given', function () {
    $account = Account::factory()->create(['name' => 'إنستغرام']);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), ['account_id' => $account->id])
        ->assertCreated()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.display_name', 'إنستغرام');
});

it('falls back to its own name once its account is deleted', function () {
    $account = Account::factory()->create(['name' => 'إنستغرام']);
    $subscription = Subscription::factory()->create(['account_id' => $account->id, 'name' => 'إعلانات ميتا']);

    $account->delete();

    expect($subscription->fresh()->displayName())->toBe('إعلانات ميتا');
});

it('leaves an unlinked record with no identifier to show', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelApi('subscriptions'), ['name' => 'تصميم شعار', 'amount' => 800])
        ->assertCreated()
        ->assertJsonPath('data.identifier', null)
        ->assertJsonPath('data.url', null);
});

it('reports no day count for a record that never expires', function () {
    $payment = Subscription::factory()->payment()->create();

    // null * -1 is 0 in PHP, which used to make a payment read as expiring today.
    expect($payment->daysUntilExpiry())->toBeNull();
});

it('sends a null day count to the panel for a payment', function () {
    Subscription::factory()->payment()->create(['name' => 'دفعة']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelApi('subscriptions'))
        ->assertOk()
        ->assertJsonPath('data.0.days_until_expiry', null);
});
