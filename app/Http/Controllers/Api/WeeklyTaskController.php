<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WeeklyTaskItemResource;
use App\Models\Employee;
use App\Models\WeeklyTaskItem;
use App\Models\WeeklyTaskList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The owner's own weekly tasks, reachable only through a token minted for one
 * employee. Nothing here accepts an employee id from the request, so a leaked
 * token still cannot reach anybody else's week.
 */
class WeeklyTaskController extends Controller
{
    /**
     * Identify the employee behind the token, so the client can confirm the
     * link before it starts writing.
     */
    public function me(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json([
            'id' => $employee->id,
            'name' => $employee->name,
            'role' => $employee->role,
            'week_start' => WeeklyTaskList::weekStartFor(Carbon::now())->toDateString(),
        ]);
    }

    /** @return AnonymousResourceCollection<int, WeeklyTaskItemResource> */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'since' => ['nullable', 'date'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : WeeklyTaskList::weekStartFor(Carbon::now())->subWeeks(3);

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : WeeklyTaskList::weekStartFor(Carbon::now())->addWeek();

        $items = WeeklyTaskItem::query()
            ->with(['list', 'category'])
            ->whereHas('list', fn ($query) => $query
                ->where('employee_id', $this->employee($request)->id)
                ->whereBetween('week_start', [$from, $to]))
            ->when(
                isset($validated['since']),
                fn ($query) => $query->where('updated_at', '>', Carbon::parse($validated['since'])),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return WeeklyTaskItemResource::collection($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'week_start' => ['nullable', 'date'],
            'is_done' => ['boolean'],
        ]);

        $list = $this->listFor(
            $this->employee($request),
            isset($validated['week_start']) ? Carbon::parse($validated['week_start']) : Carbon::now(),
        );

        $item = $list->items()->create([
            'title' => $validated['title'],
            'is_done' => $validated['is_done'] ?? false,
            'completed_at' => ($validated['is_done'] ?? false) ? Carbon::now() : null,
            'sort_order' => (int) $list->items()->max('sort_order') + 1,
        ]);

        return WeeklyTaskItemResource::make($item->load(['list', 'category']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, WeeklyTaskItem $item): JsonResponse
    {
        $this->authorizeItem($request, $item);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_done' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'week_start' => ['sometimes', 'date'],
        ]);

        if (array_key_exists('is_done', $validated)) {
            $validated['completed_at'] = $validated['is_done'] ? Carbon::now() : null;
        }

        if (array_key_exists('week_start', $validated)) {
            $validated['weekly_task_list_id'] = $this->listFor(
                $this->employee($request),
                Carbon::parse($validated['week_start']),
            )->id;
        }

        unset($validated['week_start']);

        $item->update($validated);

        return WeeklyTaskItemResource::make($item->fresh(['list', 'category']))->response();
    }

    public function destroy(Request $request, WeeklyTaskItem $item): Response|JsonResponse
    {
        $this->authorizeItem($request, $item);

        $item->delete();

        return response()->json(status: 204);
    }

    /**
     * The week's list for this employee, created on first write.
     */
    private function listFor(Employee $employee, Carbon $date): WeeklyTaskList
    {
        $weekStart = WeeklyTaskList::weekStartFor($date)->startOfDay();

        // Not firstOrCreate: the stored value carries a time component, so an
        // equality lookup on the date alone would miss and then collide with
        // the (employee, week) unique index.
        return WeeklyTaskList::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start', $weekStart)
            ->first()
            ?? WeeklyTaskList::query()->create([
                'employee_id' => $employee->id,
                'week_start' => $weekStart,
            ]);
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 403, 'This token is not bound to an employee.');

        return $employee;
    }

    /**
     * Route model binding resolves any item id, so ownership is checked here
     * rather than trusted from the URL.
     */
    private function authorizeItem(Request $request, WeeklyTaskItem $item): void
    {
        if ($item->list->employee_id !== $this->employee($request)->id) {
            throw new AccessDeniedHttpException('This task belongs to another employee.');
        }
    }
}
