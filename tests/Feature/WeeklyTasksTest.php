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
        ->and($second['added'])->toBe(0)
        ->and(WeeklyTaskItem::query()->where('is_done', true)->count())->toBe(1);
});

// A template added after the week opened used to never reach it: generation
// either built a list or skipped it whole.
it('carries a template added mid week into the lists already open', function () {
    $ahmed = Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    $sara = Employee::factory()->create(['name' => 'سارة', 'enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة عامة', 'sort_order' => 1]);

    $planner = app(WeeklyTaskPlanner::class);
    $planner->generateFor(now());

    WeeklyTaskTemplate::factory()->create(['employee_id' => $sara->id, 'title' => 'مهمة سارة', 'sort_order' => 2]);
    $result = $planner->generateFor(now());

    expect($result['added'])->toBe(1)
        ->and($result['topped_up'])->toBe(1)
        ->and($sara->fresh()->weeklyTaskLists->first()->items->pluck('title')->all())
        ->toBe(['مهمة عامة', 'مهمة سارة'])
        ->and($ahmed->fresh()->weeklyTaskLists->first()->items->pluck('title')->all())
        ->toBe(['مهمة عامة']);
});

it('keeps a ticked task ticked while topping the list up', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة أولى', 'sort_order' => 1]);

    $planner = app(WeeklyTaskPlanner::class);
    $planner->generateFor(now());
    $employee->weeklyTaskLists->first()->items->first()->update(['is_done' => true, 'completed_at' => now()]);

    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة ثانية', 'sort_order' => 2]);
    $planner->generateFor(now());

    $items = $employee->fresh()->weeklyTaskLists->first()->items;

    expect($items)->toHaveCount(2)
        ->and($items->firstWhere('title', 'مهمة أولى')->is_done)->toBeTrue()
        ->and($items->firstWhere('title', 'مهمة ثانية')->is_done)->toBeFalse();
});

it('does not count an untouched list as topped up', function () {
    Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    $sara = Employee::factory()->create(['name' => 'سارة', 'enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة عامة']);

    $planner = app(WeeklyTaskPlanner::class);
    $planner->generateFor(now());

    WeeklyTaskTemplate::factory()->create(['employee_id' => $sara->id, 'title' => 'خاصة بسارة']);
    $result = $planner->generateFor(now());

    // Only سارة gained anything, so أحمد must not be counted alongside her.
    expect($result['topped_up'])->toBe(1);
});

it('adds nothing extra when a one off task shares a template title', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    $planner = app(WeeklyTaskPlanner::class);
    $planner->generateFor(now());

    $employee->weeklyTaskLists->first()->items()->create(['title' => 'مهمة يدوية', 'sort_order' => 9]);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة يدوية']);

    expect($planner->generateFor(now())['added'])->toBe(0)
        ->and($employee->fresh()->weeklyTaskLists->first()->items)->toHaveCount(1);
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

// ---- capturing the group from a message ----------------------------------

it('lists the groups a message has passed through', function () {
    $this->mock(WhatsappGateway::class, function ($mock) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('seenGroups')->andReturn([
            'ok' => true,
            'groups' => [
                ['id' => '120363@g.us', 'name' => 'فريق التسويق', 'lastSeenAt' => 1755600000000],
                ['id' => '120364@g.us', 'name' => null, 'lastSeenAt' => 1755500000000],
            ],
        ]);
    });

    $this->actingAs($this->admin, 'admin')
        ->getJson(weeklyApi('whatsapp/seen-groups'))
        ->assertOk()
        ->assertJsonPath('groups.0.id', '120363@g.us')
        ->assertJsonPath('groups.1.name', null);
});

it('relays why nothing could be captured', function () {
    $this->mock(WhatsappGateway::class, function ($mock) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('seenGroups')->andReturn(['ok' => false, 'groups' => [], 'error' => 'الخدمة متوقفة.']);
    });

    $this->actingAs($this->admin, 'admin')
        ->getJson(weeklyApi('whatsapp/seen-groups'))
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'الخدمة متوقفة.');
});

it('keeps captured groups away from guests', function () {
    $this->getJson(weeklyApi('whatsapp/seen-groups'))->assertUnauthorized();
});

it('saves a captured group that arrived without a name', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(weeklyApi('weekly-report-settings'), [
            'whatsapp_group_id' => '120363@g.us',
            'whatsapp_group_name' => 'مهام الفريق',
            'weekly_reports_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_ready', true);
});

// ---- pasting the id straight in ------------------------------------------

it('saves a pasted group id as it stands', function () {
    $this->actingAs($this->admin, 'admin')
        ->putJson(weeklyApi('weekly-report-settings'), [
            'whatsapp_group_id' => '120363043211234567@g.us',
            'whatsapp_group_name' => '120363043211234567@g.us',
            'weekly_reports_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.whatsapp_group_id', '120363043211234567@g.us')
        ->assertJsonPath('data.is_ready', true);
});

it('proves the id by landing a message in the group', function () {
    AppSettings::current()->update(['whatsapp_group_id' => '120363043211234567@g.us']);

    $sentTo = null;
    $this->mock(WhatsappGateway::class, function ($mock) use (&$sentTo) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('sendToGroup')->andReturnUsing(function ($client, $groupId) use (&$sentTo) {
            $sentTo = $groupId;

            return ['sent' => true];
        });
    });

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/test-group'))
        ->assertOk();

    expect($sentTo)->toBe('120363043211234567@g.us');
});

it('will not test before a group is adopted', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/test-group'))
        ->assertStatus(422);
});

it('relays a refused test send', function () {
    AppSettings::current()->update(['whatsapp_group_id' => '120363@g.us']);

    $this->mock(WhatsappGateway::class, function ($mock) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('sendToGroup')->andReturn(['sent' => false, 'error' => 'المجموعة غير موجودة.']);
    });

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/test-group'))
        ->assertStatus(422)
        ->assertJsonPath('message', 'المجموعة غير موجودة.');
});

it('keeps the test send away from guests', function () {
    $this->postJson(weeklyApi('whatsapp/test-group'))->assertUnauthorized();
});

it('tests the group on screen rather than the one already saved', function () {
    AppSettings::current()->update(['whatsapp_group_id' => 'المحفوظة@g.us']);

    $sentTo = null;
    $this->mock(WhatsappGateway::class, function ($mock) use (&$sentTo) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('sendToGroup')->andReturnUsing(function ($client, $groupId) use (&$sentTo) {
            $sentTo = $groupId;

            return ['sent' => true];
        });
    });

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/test-group'), ['group_id' => '120363999@g.us'])
        ->assertOk()
        ->assertJsonPath('data.group_id', '120363999@g.us');

    expect($sentTo)->toBe('120363999@g.us');
});

it('falls back to the saved group when none is sent', function () {
    AppSettings::current()->update(['whatsapp_group_id' => 'المحفوظة@g.us']);

    $sentTo = null;
    $this->mock(WhatsappGateway::class, function ($mock) use (&$sentTo) {
        $mock->shouldReceive('clientIdFor')->andReturn('admin_1');
        $mock->shouldReceive('sendToGroup')->andReturnUsing(function ($client, $groupId) use (&$sentTo) {
            $sentTo = $groupId;

            return ['sent' => true];
        });
    });

    $this->actingAs($this->admin, 'admin')
        ->postJson(weeklyApi('whatsapp/test-group'))
        ->assertOk();

    expect($sentTo)->toBe('المحفوظة@g.us');
});
