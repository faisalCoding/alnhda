<?php

use App\Models\Admin;
use App\Models\AppSettings;
use App\Models\Employee;
use App\Models\WeeklyReportSend;
use App\Models\WeeklyTaskTemplate;
use App\Services\WeeklyTaskPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-08-29 11:00:00');
    $this->admin = Admin::factory()->create();

    config()->set('services.whatsapp.url', 'http://127.0.0.1:3000');
    config()->set('services.whatsapp.key', 'test-key');

    AppSettings::current()->update([
        'weekly_reports_enabled' => true,
        'whatsapp_group_id' => '123@g.us',
        'whatsapp_group_name' => 'مجموعة التقارير',
    ]);

    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة الأسبوع']);
    app(WeeklyTaskPlanner::class)->generateFor(now());

    Http::fake(['*/send' => Http::response(['success' => true, 'message_id' => 'abc'])]);
});

// ── العطب نفسه ───────────────────────────────────────────────────────────────

it('finds a record it wrote itself, whatever the time on the column', function () {
    WeeklyReportSend::record('2026-08-29', 'opening');

    // الكاست يكتب العمود بوقت؛ البحث بتاريخ مجرّد يجب أن يظل يطابقه.
    expect(WeeklyReportSend::alreadySent('2026-08-29', 'opening'))->toBeTrue()
        ->and(WeeklyReportSend::sentAt('2026-08-29', 'opening'))->not->toBeNull();
});

it('updates the existing row rather than colliding with the unique index', function () {
    WeeklyReportSend::record('2026-08-29', 'opening');
    $this->travel(2)->hours();
    WeeklyReportSend::record('2026-08-29', 'opening');

    expect(WeeklyReportSend::query()->count())->toBe(1)
        ->and(WeeklyReportSend::sentAt('2026-08-29', 'opening')->format('H:i'))->toBe('13:00');
});

it('keeps the two kinds of report apart', function () {
    WeeklyReportSend::record('2026-08-29', 'opening');

    expect(WeeklyReportSend::alreadySent('2026-08-29', 'closing'))->toBeFalse()
        ->and(WeeklyReportSend::query()->count())->toBe(1);
});

// ── الحارس ───────────────────────────────────────────────────────────────────

it('sends the first time and records it', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/weekly-tasks/send'), ['kind' => 'opening'])
        ->assertSuccessful()
        ->assertJsonPath('data.sent', true)
        ->assertJsonPath('data.resent', false);

    expect(WeeklyReportSend::alreadySent('2026-08-29', 'opening'))->toBeTrue();
});

it('refuses a second send and names when the first went out', function () {
    WeeklyReportSend::record('2026-08-29', 'opening');
    Http::fake(['*/send' => Http::response(['success' => true])]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/weekly-tasks/send'), ['kind' => 'opening'])
        ->assertStatus(409)
        ->assertJsonPath('already_sent', true)
        ->assertJsonFragment(['message' => 'أُرسل هذا التقرير لأسبوع 2026-08-29 بالفعل (2026-08-29 11:00).']);
});

it('does not reach WhatsApp at all when it refuses', function () {
    WeeklyReportSend::record('2026-08-29', 'opening');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/weekly-tasks/send'), ['kind' => 'opening'])
        ->assertStatus(409);

    // الضرر الحقيقي كان خروج الرسالة قبل انفجار التسجيل.
    Http::assertNothingSent();
});

it('sends again when the panel says so on purpose', function () {
    WeeklyReportSend::record('2026-08-29', 'opening');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/weekly-tasks/send'), ['kind' => 'opening', 'force' => true])
        ->assertSuccessful()
        ->assertJsonPath('data.resent', true);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/send'));

    expect(WeeklyReportSend::query()->count())->toBe(1)
        ->and(WeeklyReportSend::sentAt('2026-08-29', 'opening')->format('H:i'))->toBe('11:00');
});

it('lets the closing report through while the opening one is spent', function () {
    WeeklyReportSend::record('2026-08-29', 'opening');

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/weekly-tasks/send'), ['kind' => 'closing'])
        ->assertSuccessful();
});

// ── الأمر المجدول ────────────────────────────────────────────────────────────

it('does not repeat itself on a schedule', function () {
    $this->artisan('weekly-tasks:report opening')->assertSuccessful();
    $this->artisan('weekly-tasks:report opening')->expectsOutputToContain('أُرسل من قبل')->assertSuccessful();

    expect(WeeklyReportSend::query()->count())->toBe(1);
});

it('survives being forced, which used to hit the unique index', function () {
    $this->artisan('weekly-tasks:report opening')->assertSuccessful();

    $this->artisan('weekly-tasks:report opening --force')->assertSuccessful();

    expect(WeeklyReportSend::query()->count())->toBe(1);
});
