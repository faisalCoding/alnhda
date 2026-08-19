<?php

use App\Models\Admin;
use App\Models\AppSettings;
use App\Models\Employee;
use App\Models\WeeklyReportSend;
use App\Models\WeeklyTaskItem;
use App\Models\WeeklyTaskList;
use App\Models\WeeklyTaskTemplate;
use App\Services\WeeklyTaskPlanner;
use App\Services\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    // A Wednesday, so "this week" started on the Saturday before it.
    $this->travelTo('2026-08-19 10:00:00');
});

function weeklyApi(string $path): string
{
    return 'http://panel.localhost/api/'.ltrim($path, '/');
}

// ---- the week boundary ---------------------------------------------------

it('opens the week on saturday', function (string $day, string $expected) {
    expect(WeeklyTaskList::weekStartFor(now()->parse($day))->toDateString())->toBe($expected);
})->with([
    'saturday itself' => ['2026-08-22', '2026-08-22'],
    'sunday after' => ['2026-08-23', '2026-08-22'],
    'wednesday' => ['2026-08-19', '2026-08-15'],
    'friday closes the week' => ['2026-08-21', '2026-08-15'],
]);

// ---- generation ----------------------------------------------------------

it('opens a list for every active employee', function () {
    Employee::factory()->count(2)->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'نشر ٣ منشورات']);

    $result = app(WeeklyTaskPlanner::class)->generateFor(now());

    expect($result['created'])->toBe(2)
        ->and(WeeklyTaskList::query()->count())->toBe(2)
        ->and(WeeklyTaskItem::query()->count())->toBe(2);
});

it('skips an employee who is not active', function () {
    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    Employee::factory()->inactive()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();

    expect(app(WeeklyTaskPlanner::class)->generateFor(now())['created'])->toBe(1);
});

it('skips an employee who joins after this week', function () {
    Employee::factory()->enrolledOn('2026-09-01')->create();
    WeeklyTaskTemplate::factory()->create();

    expect(app(WeeklyTaskPlanner::class)->generateFor(now())['created'])->toBe(0);
});

it('includes an employee who joined during the week that just opened', function () {
    Employee::factory()->enrolledOn('2026-08-15')->create();
    WeeklyTaskTemplate::factory()->create();

    expect(app(WeeklyTaskPlanner::class)->generateFor(now())['created'])->toBe(1);
});

// Someone hired on a Wednesday belongs to that week, not the next one.
it('includes an employee added midway through the week', function () {
    Employee::factory()->enrolledOn('2026-08-19')->create();
    WeeklyTaskTemplate::factory()->create();

    expect(app(WeeklyTaskPlanner::class)->generateFor(now())['created'])->toBe(1);
});

it('does not rebuild a list that already exists', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();

    $planner = app(WeeklyTaskPlanner::class);
    $planner->generateFor(now());

    $employee->weeklyTaskLists->first()->items->first()->update(['is_done' => true, 'completed_at' => now()]);

    $second = $planner->generateFor(now());

    expect($second['created'])->toBe(0)
        ->and($second['skipped'])->toBe(1)
        ->and(WeeklyTaskItem::query()->where('is_done', true)->count())->toBe(1);
});

it('gives an employee the shared tasks plus their own', function () {
    $shared = Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    $special = Employee::factory()->create(['name' => 'سارة', 'enrolled_on' => '2026-01-01']);

    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة للجميع', 'sort_order' => 1]);
    WeeklyTaskTemplate::factory()->create(['employee_id' => $special->id, 'title' => 'مهمة سارة', 'sort_order' => 2]);

    app(WeeklyTaskPlanner::class)->generateFor(now());

    expect($shared->weeklyTaskLists->first()->items->pluck('title')->all())->toBe(['مهمة للجميع'])
        ->and($special->weeklyTaskLists->first()->items->pluck('title')->all())->toBe(['مهمة للجميع', 'مهمة سارة']);
});

it('starts a fresh list the following week', function () {
    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();

    $planner = app(WeeklyTaskPlanner::class);
    $planner->generateFor(now());
    $planner->generateFor(now()->addWeek());

    expect(WeeklyTaskList::query()->count())->toBe(2)
        ->and(WeeklyTaskList::query()->pluck('week_start')->map->toDateString()->all())
        ->toBe(['2026-08-15', '2026-08-22']);
});

// ---- the messages --------------------------------------------------------

it('writes the saturday brief from this week lists', function () {
    Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'نشر ٣ منشورات']);
    app(WeeklyTaskPlanner::class)->generateFor(now());

    $message = app(WeeklyTaskPlanner::class)->openingMessage(now());

    expect($message)->toContain('مهام الأسبوع')
        ->toContain('أحمد')
        ->toContain('نشر ٣ منشورات');
});

it('marks done and pending apart in the thursday summary', function () {
    Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة منجزة', 'sort_order' => 1]);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة متبقية', 'sort_order' => 2]);
    app(WeeklyTaskPlanner::class)->generateFor(now());

    WeeklyTaskItem::query()->where('title', 'مهمة منجزة')->update(['is_done' => true, 'completed_at' => now()]);

    $message = app(WeeklyTaskPlanner::class)->closingMessage(now());

    expect($message)->toContain('✅ مهمة منجزة')
        ->toContain('⬜ مهمة متبقية')
        ->toContain('الإجمالي: 1 من 2');
});

it('writes no message at all when nothing is planned', function () {
    expect(app(WeeklyTaskPlanner::class)->openingMessage(now()))->toBeNull()
        ->and(app(WeeklyTaskPlanner::class)->closingMessage(now()))->toBeNull();
});

// ---- commands ------------------------------------------------------------

it('reports nothing rather than sending an empty brief', function () {
    $this->artisan('weekly-tasks:report', ['kind' => 'opening'])
        ->expectsOutputToContain('لا توجد مهام')
        ->assertSuccessful();
});

it('refuses a report kind it does not know', function () {
    $this->artisan('weekly-tasks:report', ['kind' => 'midweek'])->assertFailed();
});

it('holds back the send while reports are switched off', function () {
    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();
    $this->artisan('weekly-tasks:generate')->assertSuccessful();

    $this->artisan('weekly-tasks:report', ['kind' => 'opening'])
        ->expectsOutputToContain('غير مفعّلة')
        ->assertSuccessful();
});

it('prints the brief on a dry run without needing a group', function () {
    Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'نشر ٣ منشورات']);
    $this->artisan('weekly-tasks:generate')->assertSuccessful();

    $this->artisan('weekly-tasks:report', ['kind' => 'opening', '--dry-run' => true])
        ->expectsOutputToContain('نشر ٣ منشورات')
        ->assertSuccessful();
});

// ---- settings ------------------------------------------------------------

it('is not ready until a group is chosen and reports are on', function () {
    $settings = AppSettings::current();

    expect($settings->weeklyReportsAreReady())->toBeFalse();

    $settings->update(['weekly_reports_enabled' => true]);
    expect($settings->fresh()->weeklyReportsAreReady())->toBeFalse();

    $settings->update(['whatsapp_group_id' => '120363000000000000@g.us']);
    expect($settings->fresh()->weeklyReportsAreReady())->toBeTrue();
});

it('saves the chosen group through the api', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(weeklyApi('weekly-report-settings'), [
            'whatsapp_group_id' => '120363000000000000@g.us',
            'whatsapp_group_name' => 'فريق التسويق',
            'weekly_reports_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_ready', true)
        ->assertJsonPath('data.whatsapp_group_name', 'فريق التسويق');
});

// ---- api -----------------------------------------------------------------

it('keeps every weekly endpoint away from guests', function (string $path) {
    $this->getJson(weeklyApi($path))->assertUnauthorized();
})->with(['employees', 'weekly-task-templates', 'weekly-tasks', 'weekly-report-settings']);

it('serves the weekly tasks page to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get('http://panel.localhost/weekly-tasks')
        ->assertOk()
        ->assertSee('المهام الأسبوعية');
});

it('creates an employee enrolled from today by default', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('employees'), ['name' => 'أحمد'])
        ->assertCreated()
        ->assertJsonPath('data.enrolled_on', now()->toDateString())
        ->assertJsonPath('data.is_active', true);
});

it('drops an employee lists when the employee goes', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();
    app(WeeklyTaskPlanner::class)->generateFor(now());

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(weeklyApi('employees/'.$employee->id))
        ->assertOk();

    expect(WeeklyTaskList::query()->count())->toBe(0)
        ->and(WeeklyTaskItem::query()->count())->toBe(0);
});

it('stamps the completion time when a weekly task is ticked', function () {
    $item = WeeklyTaskItem::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->putJson(weeklyApi('weekly-task-items/'.$item->id), ['is_done' => true])
        ->assertOk();

    expect($item->fresh()->completed_at)->not->toBeNull();
});

it('previews a report without sending it', function () {
    Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'نشر ٣ منشورات']);
    app(WeeklyTaskPlanner::class)->generateFor(now());

    $this->actingAs($this->admin, 'admin')
        ->getJson(weeklyApi('weekly-tasks/preview?kind=opening'))
        ->assertOk()
        ->assertJsonPath('data.kind', 'opening');
});

it('refuses to send while no group is set', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('weekly-tasks/send'), ['kind' => 'opening'])
        ->assertStatus(422);
});

// ---- one report per week -------------------------------------------------

// The server had schedule:run in cron four times over, so a scheduled report
// could reach the group four times. The record makes the send idempotent.
it('refuses to send the same report twice in one week', function () {
    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();
    app(WeeklyTaskPlanner::class)->generateFor(now());

    WeeklyReportSend::query()->create([
        'week_start' => '2026-08-15',
        'kind' => 'opening',
        'sent_at' => now(),
    ]);

    $this->artisan('weekly-tasks:report', ['kind' => 'opening'])
        ->expectsOutputToContain('أُرسل من قبل')
        ->assertSuccessful();
});

it('still sends the other kind in the same week', function () {
    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();
    app(WeeklyTaskPlanner::class)->generateFor(now());

    WeeklyReportSend::query()->create([
        'week_start' => '2026-08-15',
        'kind' => 'opening',
        'sent_at' => now(),
    ]);

    // Passes the guard and stops later, at the group not being configured.
    $this->artisan('weekly-tasks:report', ['kind' => 'closing'])
        ->expectsOutputToContain('غير مفعّلة')
        ->assertSuccessful();
});

it('sends again for the same week when forced', function () {
    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create();
    app(WeeklyTaskPlanner::class)->generateFor(now());

    WeeklyReportSend::query()->create([
        'week_start' => '2026-08-15',
        'kind' => 'opening',
        'sent_at' => now(),
    ]);

    $this->artisan('weekly-tasks:report', ['kind' => 'opening', '--force' => true])
        ->doesntExpectOutputToContain('أُرسل من قبل')
        ->assertSuccessful();
});

it('keeps one record per week and kind', function () {
    expect(fn () => WeeklyReportSend::query()->create(['week_start' => '2026-08-15', 'kind' => 'opening', 'sent_at' => now()])
        && WeeklyReportSend::query()->create(['week_start' => '2026-08-15', 'kind' => 'opening', 'sent_at' => now()]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('lets the next week be reported normally', function () {
    WeeklyReportSend::query()->create(['week_start' => '2026-08-15', 'kind' => 'opening', 'sent_at' => now()]);

    expect(WeeklyReportSend::alreadySent('2026-08-15', 'opening'))->toBeTrue()
        ->and(WeeklyReportSend::alreadySent('2026-08-22', 'opening'))->toBeFalse();
});

// ---- naming the group ----------------------------------------------------

function fakeGroups(array $groups): void
{
    test()->mock(WhatsappGateway::class, function ($mock) use ($groups) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('groups')->andReturn(['ok' => true, 'groups' => $groups]);
    });
}

it('adopts the group whose name matches exactly', function () {
    fakeGroups([
        ['id' => '1@g.us', 'name' => 'فريق التسويق', 'participants' => 5],
        ['id' => '2@g.us', 'name' => 'الإدارة', 'participants' => 3],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/resolve-group'), ['name' => 'فريق التسويق'])
        ->assertOk()
        ->assertJsonPath('data.matched.id', '1@g.us');
});

it('ignores stray spacing around the name', function () {
    fakeGroups([['id' => '1@g.us', 'name' => 'فريق التسويق', 'participants' => 5]]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/resolve-group'), ['name' => '  فريق   التسويق  '])
        ->assertOk()
        ->assertJsonPath('data.matched.id', '1@g.us');
});

it('offers the near misses rather than guessing', function () {
    fakeGroups([
        ['id' => '1@g.us', 'name' => 'فريق التسويق الداخلي', 'participants' => 5],
        ['id' => '2@g.us', 'name' => 'فريق التسويق الخارجي', 'participants' => 4],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/resolve-group'), ['name' => 'فريق التسويق'])
        ->assertOk()
        ->assertJsonPath('data.matched', null)
        ->assertJsonCount(2, 'data.candidates');
});

it('says so when no group carries that name', function () {
    fakeGroups([['id' => '1@g.us', 'name' => 'الإدارة', 'participants' => 3]]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/resolve-group'), ['name' => 'مجموعة غير موجودة'])
        ->assertStatus(422);
});

it('will not resolve an empty name', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/resolve-group'), ['name' => '   '])
        ->assertStatus(422);
});

it('relays a gateway that cannot be reached', function () {
    $this->mock(WhatsappGateway::class, function ($mock) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('groups')->andReturn(['ok' => false, 'groups' => [], 'error' => 'الجلسة غير جاهزة.']);
    });

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/resolve-group'), ['name' => 'فريق التسويق'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'الجلسة غير جاهزة.');
});

it('keeps group resolution away from guests', function () {
    $this->postJson(weeklyApi('whatsapp/resolve-group'), ['name' => 'x'])->assertUnauthorized();
});
