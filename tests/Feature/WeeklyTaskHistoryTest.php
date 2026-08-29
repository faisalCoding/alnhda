<?php

use App\Models\Admin;
use App\Models\Employee;
use App\Models\WeeklyTaskTemplate;
use App\Services\WeeklyTaskPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-08-15 09:00:00');
    $this->admin = Admin::factory()->create();
    $this->planner = app(WeeklyTaskPlanner::class);
});

/** يولّد أسبوعًا ثم يقفز أسبوعًا إلى الأمام، فتصير القائمة ماضية. */
function pastWeekFor(Employee $employee, array $titles, int $doneCount = 0): void
{
    foreach ($titles as $index => $title) {
        WeeklyTaskTemplate::factory()->create(['title' => $title, 'sort_order' => $index + 1]);
    }

    app(WeeklyTaskPlanner::class)->generateFor(now());

    $list = $employee->weeklyTaskLists()->whereDate('week_start', now()->toDateString())->first();

    $list->items()->limit($doneCount)->get()->each->update(['is_done' => true, 'completed_at' => now()]);
}

it('shows nothing before a week has passed', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    pastWeekFor($employee, ['مهمة']);

    // ما زلنا داخل نفس الأسبوع، فليس وراءنا شيء بعد.
    expect($this->planner->pastWeeks(now()))->toBeEmpty();
});

it('counts what was done across the week', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    pastWeekFor($employee, ['أ', 'ب', 'ج', 'د'], doneCount: 3);

    $this->travel(7)->days();
    $weeks = $this->planner->pastWeeks(now());

    expect($weeks)->toHaveCount(1)
        ->and($weeks[0]['week_start'])->toBe('2026-08-15')
        ->and($weeks[0]['done'])->toBe(3)
        ->and($weeks[0]['total'])->toBe(4);
});

it('adds up every employee in the same week', function () {
    $ahmed = Employee::factory()->create(['name' => 'أحمد', 'enrolled_on' => '2026-01-01']);
    Employee::factory()->create(['name' => 'سارة', 'enrolled_on' => '2026-01-01']);
    pastWeekFor($ahmed, ['أ', 'ب']);

    // مهمتان لكل موظّف.
    $this->travel(7)->days();
    $weeks = $this->planner->pastWeeks(now());

    expect($weeks[0]['total'])->toBe(4)
        ->and($weeks[0]['lists'])->toHaveCount(2);
});

it('puts the newest week first', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    pastWeekFor($employee, ['مهمة']);

    $this->travel(7)->days();
    $this->planner->generateFor(now());
    $this->travel(7)->days();

    expect($this->planner->pastWeeks(now())->pluck('week_start')->all())
        ->toBe(['2026-08-22', '2026-08-15']);
});

it('stops after the window it is given, so the page does not grow forever', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة']);

    for ($week = 0; $week < 5; $week++) {
        $this->planner->generateFor(now());
        $this->travel(7)->days();
    }

    expect($this->planner->pastWeeks(now(), weeks: 2))->toHaveCount(2)
        ->and($this->planner->pastWeeks(now(), weeks: 10))->toHaveCount(5);
});

it('leaves an inactive employee out of the record', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    pastWeekFor($employee, ['مهمة']);

    $employee->update(['is_active' => false]);
    $this->travel(7)->days();

    expect($this->planner->pastWeeks(now()))->toBeEmpty();
});

it('serves the archive to a signed-in admin with its items', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    pastWeekFor($employee, ['أولى', 'ثانية'], doneCount: 1);

    $this->travel(7)->days();

    $data = $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/weekly-tasks/history'))
        ->assertSuccessful()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0])->toMatchArray(['week_start' => '2026-08-15', 'done' => 1, 'total' => 2])
        ->and($data[0]['lists'][0]['items'])->toHaveCount(2)
        ->and($data[0]['lists'][0]['employee']['name'])->toBe($employee->name);
});

it('keeps the archive away from guests', function () {
    $this->getJson(panelUrl('/api/weekly-tasks/history'))->assertUnauthorized();
});

it('never returns the week now in progress', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    pastWeekFor($employee, ['مهمة']);

    $this->travel(7)->days();
    $this->planner->generateFor(now());

    $weeks = $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/weekly-tasks/history'))
        ->json('data.*.week_start');

    expect($weeks)->not->toContain('2026-08-22');
});
