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
     * Open this week's list for every active employee enrolled by then, and
     * top up lists already open with any template task they are missing.
     *
     * Re-running never removes or unticks anything: a template added after the
     * week began still reaches it, while work already done stays as it is.
     *
     * @return array{created: int, topped_up: int, added: int, week_start: string}
     */
    public function generateFor(CarbonInterface $date): array
    {
        $weekStart = WeeklyTaskList::weekStartFor($date);
        $created = 0;
        $toppedUp = 0;
        $added = 0;

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
            $list = WeeklyTaskList::query()
                ->where('employee_id', $employee->id)
                ->whereDate('week_start', $weekStart->toDateString())
                ->first();

            $isNew = $list === null;

            $addedHere = 0;

            DB::transaction(function () use ($employee, $weekStart, &$list, &$addedHere): void {
                $list ??= WeeklyTaskList::query()->create([
                    'employee_id' => $employee->id,
                    'week_start' => $weekStart->toDateString(),
                ]);

                // Matching on the title is what lets a task keep its tick: the
                // row is left alone rather than replaced by a fresh copy.
                $present = $list->items()->pluck('title');

                $missing = $employee->applicableTemplates()
                    ->reject(fn ($template): bool => $present->contains($template->title))
                    ->map(fn ($template): array => [
                        'title' => $template->title,
                        'sort_order' => $template->sort_order,
                    ])
                    ->all();

                if ($missing !== []) {
                    $list->items()->createMany($missing);
                    $addedHere = count($missing);
                }
            });

            $added += $addedHere;

            if ($isNew) {
                $created++;
            } elseif ($addedHere > 0) {
                $toppedUp++;
            }
        }

        return [
            'created' => $created,
            'topped_up' => $toppedUp,
            'added' => $added,
            'week_start' => $weekStart->toDateString(),
        ];
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
