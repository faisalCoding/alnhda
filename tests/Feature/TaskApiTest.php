<?php

use App\Models\Employee;
use App\Models\WeeklyTaskItem;
use App\Models\WeeklyTaskList;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

function actingAsEmployee(Employee $employee, array $abilities = ['tasks:self']): Employee
{
    Sanctum::actingAs($employee, $abilities);

    return $employee;
}

function weekList(Employee $employee, ?Carbon $date = null): WeeklyTaskList
{
    return WeeklyTaskList::query()->create([
        'employee_id' => $employee->id,
        'week_start' => WeeklyTaskList::weekStartFor($date ?? Carbon::now())->toDateString(),
    ]);
}

test('the api rejects anyone without a token', function () {
    $this->getJson('/api/me/tasks')->assertUnauthorized();
    $this->postJson('/api/me/tasks', ['title' => 'x'])->assertUnauthorized();
});

test('the token identifies the employee it was minted for', function () {
    $employee = actingAsEmployee(Employee::factory()->create(['name' => 'فيصل']));

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $employee->id)
        ->assertJsonPath('name', 'فيصل');
});

test('it lists only the token holder tasks', function () {
    $me = Employee::factory()->create();
    $colleague = Employee::factory()->create();

    weekList($me)->items()->create(['title' => 'مهمتي', 'sort_order' => 1]);
    weekList($colleague)->items()->create(['title' => 'مهمة زميلي', 'sort_order' => 1]);

    actingAsEmployee($me);

    $response = $this->getJson('/api/me/tasks')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('مهمتي');
});

test('creating a task opens the week list when it does not exist yet', function () {
    $me = actingAsEmployee(Employee::factory()->create());

    $this->postJson('/api/me/tasks', ['title' => 'مراجعة تقرير السيو'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'مراجعة تقرير السيو')
        ->assertJsonPath('data.is_done', false);

    expect(WeeklyTaskList::query()->where('employee_id', $me->id)->count())->toBe(1)
        ->and(WeeklyTaskItem::query()->count())->toBe(1);
});

test('a title is required', function () {
    actingAsEmployee(Employee::factory()->create());

    $this->postJson('/api/me/tasks', [])->assertJsonValidationErrors('title');
});

test('completing a task stamps the completion time', function () {
    $me = actingAsEmployee(Employee::factory()->create());
    $item = weekList($me)->items()->create(['title' => 'مهمة', 'sort_order' => 1]);

    $this->patchJson("/api/me/tasks/{$item->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.is_done', true);

    expect($item->fresh()->completed_at)->not->toBeNull();

    $this->patchJson("/api/me/tasks/{$item->id}", ['is_done' => false])->assertOk();

    expect($item->fresh()->completed_at)->toBeNull();
});

test('a task can be deleted', function () {
    $me = actingAsEmployee(Employee::factory()->create());
    $item = weekList($me)->items()->create(['title' => 'مهمة', 'sort_order' => 1]);

    $this->deleteJson("/api/me/tasks/{$item->id}")->assertNoContent();

    expect(WeeklyTaskItem::query()->find($item->id))->toBeNull();
});

test('a token cannot touch another employee task', function () {
    $colleague = Employee::factory()->create();
    $theirs = weekList($colleague)->items()->create(['title' => 'ليست لي', 'sort_order' => 1]);

    actingAsEmployee(Employee::factory()->create());

    $this->patchJson("/api/me/tasks/{$theirs->id}", ['title' => 'اختراق'])->assertForbidden();
    $this->deleteJson("/api/me/tasks/{$theirs->id}")->assertForbidden();

    expect($theirs->fresh()->title)->toBe('ليست لي');
});

test('the self ability alone cannot read the team', function () {
    actingAsEmployee(Employee::factory()->create(), ['tasks:self']);

    $this->getJson('/api/team/progress')->assertForbidden();
});

test('the team ability alone cannot write tasks', function () {
    actingAsEmployee(Employee::factory()->create(), ['team:read']);

    $this->postJson('/api/me/tasks', ['title' => 'x'])->assertForbidden();
    $this->getJson('/api/team/progress')->assertOk();
});

test('team progress summarises every active employee for the week', function () {
    $me = Employee::factory()->create(['name' => 'فيصل', 'is_active' => true]);
    $colleague = Employee::factory()->create(['name' => 'زميل', 'is_active' => true]);
    Employee::factory()->create(['name' => 'مغادر', 'is_active' => false]);

    $list = weekList($colleague);
    $list->items()->create(['title' => 'منجزة', 'is_done' => true, 'sort_order' => 1]);
    $list->items()->create(['title' => 'معلّقة', 'is_done' => false, 'sort_order' => 2]);

    actingAsEmployee($me, ['tasks:self', 'team:read']);

    $response = $this->getJson('/api/team/progress')->assertOk();

    expect($response->json('employees'))->toHaveCount(2);

    $row = collect($response->json('employees'))->firstWhere('name', 'زميل');

    expect($row['total'])->toBe(2)
        ->and($row['done'])->toBe(1)
        ->and($row['pending'])->toBe(['معلّقة']);
});

test('a since cursor returns only what changed after it', function () {
    $me = actingAsEmployee(Employee::factory()->create());
    $list = weekList($me);
    $list->items()->create(['title' => 'قديمة', 'sort_order' => 1]);

    $cursor = Carbon::now()->addSecond();
    Carbon::setTestNow(Carbon::now()->addMinutes(5));

    $list->items()->create(['title' => 'جديدة', 'sort_order' => 2]);

    $response = $this->getJson('/api/me/tasks?since='.urlencode($cursor->toIso8601String()))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('جديدة');

    Carbon::setTestNow();
});

test('two tasks in the same week reuse one list', function () {
    $me = actingAsEmployee(Employee::factory()->create());

    $this->postJson('/api/me/tasks', ['title' => 'أولى'])->assertCreated();
    $this->postJson('/api/me/tasks', ['title' => 'ثانية'])->assertCreated();

    expect(WeeklyTaskList::query()->where('employee_id', $me->id)->count())->toBe(1)
        ->and(WeeklyTaskItem::query()->count())->toBe(2);
});

test('a task can be moved to another week', function () {
    $me = actingAsEmployee(Employee::factory()->create());
    $item = weekList($me)->items()->create(['title' => 'مؤجَّلة', 'sort_order' => 1]);

    $nextWeek = WeeklyTaskList::weekStartFor(Carbon::now())->addWeek();

    $this->patchJson("/api/me/tasks/{$item->id}", ['week_start' => $nextWeek->toDateString()])
        ->assertOk()
        ->assertJsonPath('data.week_start', $nextWeek->toDateString());

    expect(WeeklyTaskList::query()->where('employee_id', $me->id)->count())->toBe(2);
});
