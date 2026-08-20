<?php

use App\Models\Admin;
use App\Models\Employee;
use App\Models\WeeklyTaskCategory;
use App\Models\WeeklyTaskItem;
use App\Models\WeeklyTaskList;
use App\Models\WeeklyTaskTemplate;
use App\Services\WeeklyTaskPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    // A Wednesday, so "this week" started on the Saturday before it.
    $this->travelTo('2026-08-19 10:00:00');
});

function categoryApi(string $path = ''): string
{
    return rtrim('http://panel.localhost/api/weekly-task-categories/'.ltrim($path, '/'), '/');
}

// ---- managing the categories --------------------------------------------

it('creates a category with a colour from the palette', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(categoryApi(), ['name' => 'التسويق', 'color' => 'violet'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'التسويق')
        ->assertJsonPath('data.color', 'violet');
});

it('falls back to a palette colour when none is chosen', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(categoryApi(), ['name' => 'المتابعة'])
        ->assertCreated()
        ->assertJsonPath('data.color', WeeklyTaskCategory::COLORS[0]);
});

it('refuses a colour outside the palette', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(categoryApi(), ['name' => 'التسويق', 'color' => '#ff0000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('color');
});

it('refuses a category with no name', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(categoryApi(), ['color' => 'sky'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('renames a category', function () {
    $category = WeeklyTaskCategory::factory()->create(['name' => 'قديم']);

    $this->actingAs($this->admin, 'admin')
        ->putJson(categoryApi((string) $category->id), ['name' => 'جديد', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'جديد');
});

it('counts the templates filed under a category', function () {
    $category = WeeklyTaskCategory::factory()->create();
    WeeklyTaskTemplate::factory()->count(3)->create(['weekly_task_category_id' => $category->id]);
    WeeklyTaskTemplate::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(categoryApi())
        ->assertOk()
        ->assertJsonPath('data.0.templates_count', 3);
});

// Deleting a heading must not delete the work filed under it.
it('keeps the tasks when their category is deleted, merely unfiled', function () {
    $category = WeeklyTaskCategory::factory()->create();
    $template = WeeklyTaskTemplate::factory()->create(['weekly_task_category_id' => $category->id]);
    $item = WeeklyTaskItem::factory()->create(['weekly_task_category_id' => $category->id]);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(categoryApi((string) $category->id))
        ->assertOk();

    expect($template->fresh())->not->toBeNull()
        ->and($template->fresh()->weekly_task_category_id)->toBeNull()
        ->and($item->fresh())->not->toBeNull()
        ->and($item->fresh()->weekly_task_category_id)->toBeNull();
});

it('keeps the categories away from guests', function (string $method, string $path) {
    $this->json($method, categoryApi($path), ['name' => 'x'])->assertUnauthorized();
})->with([
    'listing' => ['get', ''],
    'creating' => ['post', ''],
]);

// ---- the category travels onto the week ---------------------------------

it('carries the template category onto the generated task', function () {
    $category = WeeklyTaskCategory::factory()->create(['name' => 'التسويق']);
    Employee::factory()->create(['enrolled_on' => '2026-08-01']);
    WeeklyTaskTemplate::factory()->create(['title' => 'نشر ٣ منشورات', 'weekly_task_category_id' => $category->id]);

    app(WeeklyTaskPlanner::class)->generateFor(now());

    expect(WeeklyTaskItem::query()->first()->weekly_task_category_id)->toBe($category->id);
});

it('files a task added by hand under the chosen category', function () {
    $category = WeeklyTaskCategory::factory()->create();
    $list = WeeklyTaskList::factory()->create(['week_start' => '2026-08-15']);

    $this->actingAs($this->admin, 'admin')
        ->postJson("http://panel.localhost/api/weekly-tasks/{$list->id}/items", [
            'title' => 'مهمة إضافية',
            'weekly_task_category_id' => $category->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.category.name', $category->name);
});

it('moves a task to another category without touching its tick', function () {
    $category = WeeklyTaskCategory::factory()->create();
    $item = WeeklyTaskItem::factory()->create(['is_done' => true]);

    $this->actingAs($this->admin, 'admin')
        ->putJson("http://panel.localhost/api/weekly-task-items/{$item->id}", [
            'weekly_task_category_id' => $category->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.weekly_task_category_id', $category->id)
        ->assertJsonPath('data.is_done', true);
});

// ---- the messages --------------------------------------------------------

it('heads each group of tasks with its category in the saturday message', function () {
    $marketing = WeeklyTaskCategory::factory()->create(['name' => 'التسويق', 'sort_order' => 1]);
    $followUp = WeeklyTaskCategory::factory()->create(['name' => 'المتابعة', 'sort_order' => 2]);
    $list = WeeklyTaskList::factory()->for(Employee::factory()->create(['name' => 'أحمد']))->create(['week_start' => '2026-08-15']);

    WeeklyTaskItem::factory()->for($list, 'list')->create(['title' => 'نشر منشور', 'weekly_task_category_id' => $marketing->id, 'sort_order' => 1]);
    WeeklyTaskItem::factory()->for($list, 'list')->create(['title' => 'اتصال بالعملاء', 'weekly_task_category_id' => $followUp->id, 'sort_order' => 2]);

    $message = app(WeeklyTaskPlanner::class)->openingMessage(now());

    expect($message)->toContain('◾ التسويق')
        ->and($message)->toContain('◾ المتابعة')
        ->and(mb_strpos($message, '◾ التسويق'))->toBeLessThan(mb_strpos($message, '◾ المتابعة'))
        ->and($message)->toContain('1. نشر منشور')
        ->and($message)->toContain('2. اتصال بالعملاء');
});

it('sinks the unfiled tasks below every category', function () {
    $marketing = WeeklyTaskCategory::factory()->create(['name' => 'التسويق', 'sort_order' => 9]);
    $list = WeeklyTaskList::factory()->for(Employee::factory()->create())->create(['week_start' => '2026-08-15']);

    WeeklyTaskItem::factory()->for($list, 'list')->create(['title' => 'بلا تصنيف', 'sort_order' => 1]);
    WeeklyTaskItem::factory()->for($list, 'list')->create(['title' => 'مصنّفة', 'weekly_task_category_id' => $marketing->id, 'sort_order' => 2]);

    $message = app(WeeklyTaskPlanner::class)->openingMessage(now());

    expect(mb_strpos($message, '◾ التسويق'))->toBeLessThan(mb_strpos($message, 'أخرى'))
        ->and(mb_strpos($message, 'مصنّفة'))->toBeLessThan(mb_strpos($message, 'بلا تصنيف'));
});

// The message read a certain way for weeks before categories existed; a company
// that never creates one should not see it change.
it('leaves the message unheaded when nothing is categorised', function () {
    $list = WeeklyTaskList::factory()->for(Employee::factory()->create(['name' => 'أحمد']))->create(['week_start' => '2026-08-15']);
    WeeklyTaskItem::factory()->for($list, 'list')->create(['title' => 'مهمة']);

    $message = app(WeeklyTaskPlanner::class)->openingMessage(now());

    expect($message)->not->toContain('◾')
        ->and($message)->not->toContain('أخرى')
        ->and($message)->toContain('1. مهمة');
});

it('heads the thursday summary too, done before pending inside each', function () {
    $marketing = WeeklyTaskCategory::factory()->create(['name' => 'التسويق']);
    $list = WeeklyTaskList::factory()->for(Employee::factory()->create(['name' => 'أحمد']))->create(['week_start' => '2026-08-15']);

    WeeklyTaskItem::factory()->for($list, 'list')->create(['title' => 'لم تُنجز', 'weekly_task_category_id' => $marketing->id, 'is_done' => false, 'sort_order' => 1]);
    WeeklyTaskItem::factory()->for($list, 'list')->create(['title' => 'أُنجزت', 'weekly_task_category_id' => $marketing->id, 'is_done' => true, 'sort_order' => 2]);

    $message = app(WeeklyTaskPlanner::class)->closingMessage(now());

    expect($message)->toContain('◾ التسويق')
        ->and(mb_strpos($message, '✅ أُنجزت'))->toBeLessThan(mb_strpos($message, '⬜ لم تُنجز'))
        ->and($message)->toContain('الإجمالي: 1 من 2 مهمة.');
});

// ---- pasting a whole week in at once ------------------------------------

it('makes one template per line pasted', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', [
            'title' => "نشر ٣ منشورات\nمتابعة العملاء\nتحديث الأسعار",
        ])
        ->assertCreated()
        ->assertJsonCount(3, 'data');

    expect(WeeklyTaskTemplate::query()->pluck('title')->all())
        ->toBe(['نشر ٣ منشورات', 'متابعة العملاء', 'تحديث الأسعار']);
});

it('strips the list markers that come with pasted text', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', [
            'title' => "1. نشر منشور\n- متابعة العملاء\n• تحديث الأسعار",
        ])
        ->assertCreated();

    expect(WeeklyTaskTemplate::query()->pluck('title')->all())
        ->toBe(['نشر منشور', 'متابعة العملاء', 'تحديث الأسعار']);
});

// A bare number opens plenty of real tasks; only an actual marker may go.
it('keeps a task that merely opens with a number', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', ['title' => '3 منشورات أسبوعياً'])
        ->assertCreated();

    expect(WeeklyTaskTemplate::query()->first()->title)->toBe('3 منشورات أسبوعياً');
});

it('drops blank lines and repeats from the paste', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', [
            'title' => "نشر منشور\n\n   \nنشر منشور\nمتابعة",
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'data');
});

it('files every pasted line under the one chosen category and employee', function () {
    $category = WeeklyTaskCategory::factory()->create();
    $employee = Employee::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', [
            'title' => "أولى\nثانية",
            'employee_id' => $employee->id,
            'weekly_task_category_id' => $category->id,
        ])
        ->assertCreated();

    expect(WeeklyTaskTemplate::query()->where('weekly_task_category_id', $category->id)->where('employee_id', $employee->id)->count())
        ->toBe(2);
});

it('orders the pasted tasks after the templates already on file', function () {
    WeeklyTaskTemplate::factory()->create(['sort_order' => 7]);

    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', ['title' => "أولى\nثانية"])
        ->assertCreated();

    expect(WeeklyTaskTemplate::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([7, 8, 9]);
});

it('refuses a paste of nothing but blank lines', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', ['title' => "\n\n   \n"])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('refuses a paste longer than fifty tasks', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', [
            'title' => collect(range(1, 51))->map(fn (int $n): string => "مهمة رقم {$n}")->implode("\n"),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    expect(WeeklyTaskTemplate::query()->count())->toBe(0);
});

it('refuses the whole paste when one line is too long', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson('http://panel.localhost/api/weekly-task-templates', [
            'title' => 'مهمة قصيرة'."\n".str_repeat('ط', 256),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    expect(WeeklyTaskTemplate::query()->count())->toBe(0);
});
