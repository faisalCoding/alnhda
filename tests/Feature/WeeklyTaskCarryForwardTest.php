<?php

use App\Models\Admin;
use App\Models\Employee;
use App\Models\WeeklyTaskCategory;
use App\Models\WeeklyTaskList;
use App\Models\WeeklyTaskTemplate;
use App\Services\WeeklyTaskPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // سبت ثابت: كل التأكيدات هنا تدور حول حدّ الأسبوع.
    $this->travelTo('2026-08-15 09:00:00');
    $this->planner = app(WeeklyTaskPlanner::class);
});

/** ينشئ أسبوعًا كاملاً ثم يقفز إلى الأسبوع التالي. */
function weekWithLeftovers(Employee $employee, array $titles, array $done = []): WeeklyTaskList
{
    foreach ($titles as $index => $title) {
        WeeklyTaskTemplate::factory()->create(['title' => $title, 'sort_order' => $index + 1]);
    }

    app(WeeklyTaskPlanner::class)->generateFor(now());

    $list = $employee->weeklyTaskLists()->first();

    foreach ($done as $title) {
        $list->items()->where('title', $title)->update(['is_done' => true, 'completed_at' => now()]);
    }

    return $list;
}

it('carries only what was left unfinished', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers($employee, ['منجزة', 'متأخرة'], done: ['منجزة']);

    $result = $this->planner->carryForwardFrom(now());

    expect($result['carried'])->toBe(1)
        ->and($result['employees'])->toBe(1)
        ->and($result['from'])->toBe('2026-08-15')
        ->and($result['to'])->toBe('2026-08-22');

    $next = $employee->weeklyTaskLists()->whereDate('week_start', '2026-08-22')->first();

    expect($next->items->pluck('title')->all())->toBe(['متأخرة'])
        ->and($next->items->first()->carried_from->toDateString())->toBe('2026-08-15')
        ->and($next->items->first()->is_done)->toBeFalse();
});

it('leaves the week that has passed exactly as it was', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    $list = weekWithLeftovers($employee, ['منجزة', 'متأخرة'], done: ['منجزة']);

    $this->planner->carryForwardFrom(now());

    // السجلّ شهادة على ما استُحقّ وما أُنجز؛ تفريغه بأثر رجعي يجعل كل أسبوع
    // ماضٍ يبدو مكتملاً.
    expect($list->fresh()->items->pluck('title')->all())->toBe(['منجزة', 'متأخرة'])
        ->and($list->fresh()->items->where('is_done', true))->toHaveCount(1);
});

it('does not duplicate a task the next week already holds', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers($employee, ['مهمة متكرّرة']);

    // الأسبوع التالي وُلّد من القوالب، فالمهمة نفسها فيه أصلاً.
    $this->travel(7)->days();
    $this->planner->generateFor(now());

    $result = $this->planner->carryForwardFrom(now()->subWeek());

    $next = $employee->weeklyTaskLists()->whereDate('week_start', '2026-08-22')->first();

    expect($result['carried'])->toBe(0)
        ->and($next->items)->toHaveCount(1);
});

it('is safe to press twice', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers($employee, ['متأخرة']);

    $this->planner->carryForwardFrom(now());
    $second = $this->planner->carryForwardFrom(now());

    expect($second['carried'])->toBe(0)
        ->and($employee->weeklyTaskLists()->whereDate('week_start', '2026-08-22')->first()->items)->toHaveCount(1);
});

it('keeps naming the week a task was first owed in', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers($employee, ['متأخرة منذ زمن']);

    $this->planner->carryForwardFrom(now());
    $this->travel(7)->days();
    $this->planner->carryForwardFrom(now());

    $third = $employee->weeklyTaskLists()->whereDate('week_start', '2026-08-29')->first();

    // لا «الأسبوع الماضي»، بل الأسبوع الذي استُحقّت فيه أول مرة.
    expect($third->items->first()->carried_from->toDateString())->toBe('2026-08-15');
});

it('carries the task under the category it had', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    $category = WeeklyTaskCategory::factory()->create(['name' => 'التسويق']);
    WeeklyTaskTemplate::factory()->create(['title' => 'مهمة مصنّفة', 'weekly_task_category_id' => $category->id]);
    $this->planner->generateFor(now());

    $this->planner->carryForwardFrom(now());

    $next = $employee->weeklyTaskLists()->whereDate('week_start', '2026-08-22')->first();

    expect($next->items->first()->weekly_task_category_id)->toBe($category->id);
});

it('creates next week for an employee who has none yet', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers($employee, ['متأخرة']);

    expect($employee->weeklyTaskLists()->whereDate('week_start', '2026-08-22')->exists())->toBeFalse();

    $this->planner->carryForwardFrom(now());

    expect($employee->weeklyTaskLists()->whereDate('week_start', '2026-08-22')->exists())->toBeTrue();
});

it('skips an employee who is no longer active', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers($employee, ['متأخرة']);

    $employee->update(['is_active' => false]);

    expect($this->planner->carryForwardFrom(now())['carried'])->toBe(0);
});

it('reports nothing to carry when the week was finished', function () {
    $employee = Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers($employee, ['منجزة'], done: ['منجزة']);

    expect($this->planner->carryForwardFrom(now()))
        ->toMatchArray(['carried' => 0, 'employees' => 0]);
});

it('exposes carry forward to signed-in admins only', function () {
    $this->postJson(panelUrl('/api/weekly-tasks/carry-forward'))->assertUnauthorized();

    Employee::factory()->create(['enrolled_on' => '2026-01-01']);
    weekWithLeftovers(Employee::first(), ['متأخرة']);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->postJson(panelUrl('/api/weekly-tasks/carry-forward'), ['date' => '2026-08-15'])
        ->assertSuccessful()
        ->assertJsonPath('data.carried', 1)
        ->assertJsonPath('data.to', '2026-08-22');
});
