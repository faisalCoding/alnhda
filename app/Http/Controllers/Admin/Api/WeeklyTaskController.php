<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\StoreWeeklyTaskCategoryRequest;
use App\Http\Requests\Admin\StoreWeeklyTaskItemRequest;
use App\Http\Requests\Admin\StoreWeeklyTaskTemplateRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Http\Requests\Admin\UpdateWeeklyReportSettingsRequest;
use App\Http\Requests\Admin\UpdateWeeklyTaskCategoryRequest;
use App\Http\Requests\Admin\UpdateWeeklyTaskItemRequest;
use App\Http\Resources\Admin\EmployeeResource;
use App\Http\Resources\Admin\WeeklyTaskCategoryResource;
use App\Http\Resources\Admin\WeeklyTaskItemResource;
use App\Http\Resources\Admin\WeeklyTaskListResource;
use App\Http\Resources\Admin\WeeklyTaskTemplateResource;
use App\Models\Admin;
use App\Models\AppSettings;
use App\Models\Employee;
use App\Models\WeeklyReportSend;
use App\Models\WeeklyTaskCategory;
use App\Models\WeeklyTaskItem;
use App\Models\WeeklyTaskList;
use App\Models\WeeklyTaskTemplate;
use App\Services\WeeklyTaskPlanner;
use App\Services\WhatsappGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WeeklyTaskController extends Controller
{
    public function __construct(
        private WeeklyTaskPlanner $planner,
        private WhatsappGateway $gateway,
    ) {}

    // ---- employees -------------------------------------------------------

    public function employees(): AnonymousResourceCollection
    {
        return EmployeeResource::collection(
            Employee::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function storeEmployee(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::query()->create([
            ...$request->validated(),
            'enrolled_on' => $request->validated('enrolled_on') ?? now()->toDateString(),
            // Set here as well as in the column, so the created model carries it
            // back in the response rather than a null the resource would relay.
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) Employee::query()->max('sort_order') + 1,
        ]);

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    public function updateEmployee(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($request->validated());

        return new EmployeeResource($employee);
    }

    public function destroyEmployee(Employee $employee): JsonResponse
    {
        $id = $employee->id;
        $employee->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    // ---- categories ------------------------------------------------------

    public function categories(): AnonymousResourceCollection
    {
        return WeeklyTaskCategoryResource::collection(
            WeeklyTaskCategory::query()
                ->withCount('templates')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function storeCategory(StoreWeeklyTaskCategoryRequest $request): JsonResponse
    {
        $category = WeeklyTaskCategory::query()->create([
            ...$request->validated(),
            'color' => $request->validated('color') ?? WeeklyTaskCategory::COLORS[0],
            'sort_order' => (int) WeeklyTaskCategory::query()->max('sort_order') + 1,
        ]);

        return (new WeeklyTaskCategoryResource($category->loadCount('templates')))
            ->response()
            ->setStatusCode(201);
    }

    public function updateCategory(UpdateWeeklyTaskCategoryRequest $request, WeeklyTaskCategory $category): WeeklyTaskCategoryResource
    {
        $category->update($request->validated());

        return new WeeklyTaskCategoryResource($category->loadCount('templates'));
    }

    /**
     * Deleting a category leaves its tasks in place, merely unfiled — including
     * the ones already sitting in an open week.
     */
    public function destroyCategory(WeeklyTaskCategory $category): JsonResponse
    {
        $id = $category->id;
        $category->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    // ---- templates -------------------------------------------------------

    public function templates(): AnonymousResourceCollection
    {
        return WeeklyTaskTemplateResource::collection(
            WeeklyTaskTemplate::query()->with(['employee', 'category'])->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    /**
     * Takes one task per line, so a whole week can be pasted in at once, and
     * always answers with a collection however many lines arrived.
     */
    public function storeTemplate(StoreWeeklyTaskTemplateRequest $request): JsonResponse
    {
        $order = (int) WeeklyTaskTemplate::query()->max('sort_order');
        $created = collect();

        DB::transaction(function () use ($request, &$order, $created): void {
            foreach ($request->titles() as $title) {
                $created->push(WeeklyTaskTemplate::query()->create([
                    'title' => $title,
                    'employee_id' => $request->validated('employee_id'),
                    'weekly_task_category_id' => $request->validated('weekly_task_category_id'),
                    'sort_order' => ++$order,
                ]));
            }
        });

        return WeeklyTaskTemplateResource::collection($created->each->load(['employee', 'category']))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyTemplate(WeeklyTaskTemplate $template): JsonResponse
    {
        $id = $template->id;
        $template->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    // ---- the week --------------------------------------------------------

    public function week(Request $request): AnonymousResourceCollection
    {
        $date = $request->filled('date') ? now()->parse($request->string('date')->toString()) : now();

        return WeeklyTaskListResource::collection($this->planner->listsForWeek($date));
    }

    public function generate(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? now()->parse($request->string('date')->toString()) : now();

        return response()->json(['data' => $this->planner->generateFor($date)]);
    }

    public function storeItem(StoreWeeklyTaskItemRequest $request, WeeklyTaskList $list): JsonResponse
    {
        $item = $list->items()->create([
            ...$request->validated(),
            'sort_order' => (int) $list->items()->max('sort_order') + 1,
        ]);

        return (new WeeklyTaskItemResource($item->load('category')))->response()->setStatusCode(201);
    }

    public function updateItem(UpdateWeeklyTaskItemRequest $request, WeeklyTaskItem $item): WeeklyTaskItemResource
    {
        $attributes = $request->validated();

        if (array_key_exists('is_done', $attributes)) {
            $attributes['completed_at'] = $attributes['is_done'] ? now() : null;
        }

        $item->update($attributes);

        return new WeeklyTaskItemResource($item->load('category'));
    }

    public function destroyItem(WeeklyTaskItem $item): JsonResponse
    {
        $id = $item->id;
        $item->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    // ---- reporting -------------------------------------------------------

    public function settings(): JsonResponse
    {
        $settings = AppSettings::current();

        return response()->json(['data' => [
            'whatsapp_group_id' => $settings->whatsapp_group_id,
            'whatsapp_group_name' => $settings->whatsapp_group_name,
            'weekly_reports_enabled' => $settings->weekly_reports_enabled,
            'is_ready' => $settings->weeklyReportsAreReady(),
        ]]);
    }

    public function updateSettings(UpdateWeeklyReportSettingsRequest $request): JsonResponse
    {
        $settings = AppSettings::current();
        $settings->update($request->validated());

        return $this->settings();
    }

    /**
     * The groups a message has recently passed through, newest first.
     */
    public function seenGroups(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return response()->json($this->gateway->seenGroups($this->gateway->clientIdFor($admin)));
    }

    /**
     * Translate a group name into the id WhatsApp actually addresses. An exact
     * name is adopted straight away; anything else comes back as candidates so
     * the admin can see what was meant rather than guess.
     */
    public function resolveGroup(Request $request): JsonResponse
    {
        $wanted = trim((string) $request->input('name'));

        if ($wanted === '') {
            return response()->json(['message' => 'اكتب اسم المجموعة.'], 422);
        }

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $result = $this->gateway->groups($this->gateway->clientIdFor($admin));

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['error'] ?? 'تعذر الوصول إلى جلسة الواتساب.',
            ], 422);
        }

        $groups = collect($result['groups']);
        $normalise = fn (string $value): string => preg_replace('/\s+/u', ' ', trim($value)) ?? $value;

        $exact = $groups->first(
            fn (array $group): bool => $normalise($group['name']) === $normalise($wanted)
        );

        if ($exact !== null) {
            return response()->json(['data' => ['matched' => $exact, 'candidates' => []]]);
        }

        $candidates = $groups
            ->filter(fn (array $group): bool => str_contains($normalise($group['name']), $normalise($wanted)))
            ->values();

        if ($candidates->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد مجموعة بهذا الاسم في حسابك. تأكد من الاسم حرفياً كما يظهر في واتساب.',
            ], 422);
        }

        return response()->json(['data' => ['matched' => null, 'candidates' => $candidates->all()]]);
    }

    /**
     * Render a report without sending it, so the wording can be checked first.
     */
    public function preview(Request $request): JsonResponse
    {
        $kind = $request->string('kind')->toString() === 'closing' ? 'closing' : 'opening';
        $date = $request->filled('date') ? now()->parse($request->string('date')->toString()) : now();

        $message = $kind === 'closing'
            ? $this->planner->closingMessage($date)
            : $this->planner->openingMessage($date);

        return response()->json(['data' => ['kind' => $kind, 'message' => $message]]);
    }

    /**
     * Post a short message to the configured group. Listing is broken on this
     * build, so actually landing a message is the only real proof the id works.
     */
    public function testGroup(Request $request): JsonResponse
    {
        // The id on screen wins over the stored one, so a group can be proved
        // before it is committed rather than after.
        $groupId = trim((string) $request->input('group_id')) ?: (string) AppSettings::current()->whatsapp_group_id;

        if (blank($groupId)) {
            return response()->json(['message' => 'اعتمد مجموعة أولاً.'], 422);
        }

        /** @var Admin $admin */
        $admin = $request->user('admin');

        $result = $this->gateway->sendToGroup(
            $this->gateway->clientIdFor($admin),
            $groupId,
            'رسالة تجريبية من لوحة كيان النهضة للتأكد من ربط مجموعة التقارير.',
        );

        if (! ($result['sent'] ?? false)) {
            return response()->json(['message' => $result['error'] ?? 'تعذر الإرسال.'], 422);
        }

        return response()->json(['data' => ['sent' => true, 'group_id' => $groupId]]);
    }

    /**
     * Send the report now rather than waiting for its scheduled day.
     */
    public function send(Request $request): JsonResponse
    {
        $kind = $request->string('kind')->toString() === 'closing' ? 'closing' : 'opening';
        $settings = AppSettings::current();

        if (! $settings->weeklyReportsAreReady()) {
            return response()->json(['message' => 'فعّل التقارير واختر مجموعة أولاً.'], 422);
        }

        $message = $kind === 'closing'
            ? $this->planner->closingMessage(now())
            : $this->planner->openingMessage(now());

        if ($message === null) {
            return response()->json(['message' => 'لا توجد مهام لهذا الأسبوع.'], 422);
        }

        /** @var Admin $admin */
        $admin = $request->user('admin');

        $result = $this->gateway->sendToGroup(
            $this->gateway->clientIdFor($admin),
            (string) $settings->whatsapp_group_id,
            $message,
        );

        if (! ($result['sent'] ?? false)) {
            return response()->json(['message' => $result['error'] ?? 'تعذر الإرسال.'], 422);
        }

        // Recorded so the scheduled run does not repeat what was just sent by hand.
        WeeklyReportSend::query()->updateOrCreate(
            ['week_start' => WeeklyTaskList::weekStartFor(now())->toDateString(), 'kind' => $kind],
            ['sent_at' => now()],
        );

        return response()->json(['data' => ['sent' => true]]);
    }
}
