<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WeeklyTaskList;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WeeklyTaskPlanner
{
    /**
     * Open this week's list for every active employee enrolled by then.
     *
     * Re-running is safe: a list already opened is left as it is rather than
     * refilled, so ticked tasks are never wiped by a second run.
     *
     * @return array{created: int, skipped: int, week_start: string}
     */
    public function generateFor(CarbonInterface $date): array
    {
        $weekStart = WeeklyTaskList::weekStartFor($date);
        $created = 0;
        $skipped = 0;

        // Anyone enrolled by the end of this week takes part in it. Keying off
        // the week's start instead would leave someone added on a Wednesday
        // with nothing at all until the following Saturday.
        $weekEnd = $weekStart->copy()->addDays(6);

        $employees = Employee::query()
            ->where('is_active', true)
            ->whereDate('enrolled_on', '<=', $weekEnd->toDateString())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($employees as $employee) {
            $exists = WeeklyTaskList::query()
                ->where('employee_id', $employee->id)
                ->whereDate('week_start', $weekStart->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($employee, $weekStart): void {
                $list = WeeklyTaskList::query()->create([
                    'employee_id' => $employee->id,
                    'week_start' => $weekStart->toDateString(),
                ]);

                $list->items()->createMany(
                    $employee->applicableTemplates()
                        ->map(fn ($template): array => [
                            'title' => $template->title,
                            'sort_order' => $template->sort_order,
                        ])->all()
                );
            });

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'week_start' => $weekStart->toDateString()];
    }

    /**
     * @return Collection<int, WeeklyTaskList>
     */
    public function listsForWeek(CarbonInterface $date): Collection
    {
        return WeeklyTaskList::query()
            ->with(['employee', 'items'])
            ->whereDate('week_start', WeeklyTaskList::weekStartFor($date)->toDateString())
            ->whereHas('employee', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (WeeklyTaskList $list): int => $list->employee->sort_order)
            ->values();
    }

    /**
     * The Saturday message: what each employee owes this week.
     */
    public function openingMessage(CarbonInterface $date): ?string
    {
        $lists = $this->listsForWeek($date);

        if ($lists->isEmpty()) {
            return null;
        }

        $weekStart = WeeklyTaskList::weekStartFor($date);
        $lines = ['*مهام الأسبوع*', 'من '.$weekStart->toDateString().' إلى '.$weekStart->copy()->addDays(5)->toDateString(), ''];

        foreach ($lists as $list) {
            if ($list->items->isEmpty()) {
                continue;
            }

            $lines[] = '*'.$list->employee->name.'*';

            foreach ($list->items as $index => $item) {
                $lines[] = ($index + 1).'. '.$item->title;
            }

            $lines[] = '';
        }

        return count($lines) > 3 ? rtrim(implode("\n", $lines)) : null;
    }

    /**
     * The Thursday message: what actually got done.
     */
    public function closingMessage(CarbonInterface $date): ?string
    {
        $lists = $this->listsForWeek($date);

        if ($lists->isEmpty()) {
            return null;
        }

        $weekStart = WeeklyTaskList::weekStartFor($date);
        $lines = ['*ما أُنجز هذا الأسبوع*', 'من '.$weekStart->toDateString().' إلى '.$weekStart->copy()->addDays(5)->toDateString(), ''];

        $totalDone = 0;
        $totalAll = 0;

        foreach ($lists as $list) {
            if ($list->items->isEmpty()) {
                continue;
            }

            $done = $list->items->where('is_done', true);
            $pending = $list->items->where('is_done', false);
            $totalDone += $done->count();
            $totalAll += $list->items->count();

            $lines[] = '*'.$list->employee->name.'* — '.$done->count().' من '.$list->items->count();

            foreach ($done as $item) {
                $lines[] = '✅ '.$item->title;
            }

            foreach ($pending as $item) {
                $lines[] = '⬜ '.$item->title;
            }

            $lines[] = '';
        }

        if ($totalAll === 0) {
            return null;
        }

        $lines[] = 'الإجمالي: '.$totalDone.' من '.$totalAll.' مهمة.';

        return rtrim(implode("\n", $lines));
    }
}
