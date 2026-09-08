<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WeeklyTaskList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A read-only follow-up view of the whole team for one week: who is done, who
 * is behind, and what is still open. Writes are never possible here — the
 * ability that reaches this endpoint grants nothing else.
 */
class TeamProgressController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => ['nullable', 'date'],
        ]);

        $weekStart = WeeklyTaskList::weekStartFor(
            isset($validated['week_start']) ? Carbon::parse($validated['week_start']) : Carbon::now(),
        );

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['weeklyTaskLists' => fn ($query) => $query
                ->whereDate('week_start', $weekStart)
                ->with('items')])
            ->get();

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'employees' => $employees->map(function (Employee $employee): array {
                $items = $employee->weeklyTaskLists->first()?->items ?? collect();

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'role' => $employee->role,
                    'total' => $items->count(),
                    'done' => $items->where('is_done', true)->count(),
                    'pending' => $items->where('is_done', false)->pluck('title')->values()->all(),
                ];
            })->all(),
        ]);
    }
}
