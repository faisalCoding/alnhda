<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WeeklyTaskItem;
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
                        'weekly_task_category_id' => $template->weekly_task_category_id,
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
     * Weeks already gone, newest first — the record the page's own week cannot
     * show, since it only ever asks for today's.
     *
     * Capped rather than unbounded: the archive grows by one week forever, and
     * a page that loads all of it gets slower every Saturday.
     *
     * @return Collection<int, array{week_start: string, done: int, total: int, lists: Collection<int, WeeklyTaskList>}>
     */
    public function pastWeeks(CarbonInterface $date, int $weeks = 8): Collection
    {
        $currentWeek = WeeklyTaskList::weekStartFor($date);
        $earliest = $currentWeek->copy()->subWeeks($weeks);

        return WeeklyTaskList::query()
            ->with(['employee', 'items.category'])
            ->whereDate('week_start', '<', $currentWeek->toDateString())
            ->whereDate('week_start', '>=', $earliest->toDateString())
            ->whereHas('employee', fn ($query) => $query->where('is_active', true))
            ->get()
            ->groupBy(fn (WeeklyTaskList $list): string => $list->week_start->toDateString())
            ->map(function (Collection $lists, string $weekStart): array {
                $items = $lists->flatMap->items;

                return [
                    'week_start' => $weekStart,
                    'done' => $items->where('is_done', true)->count(),
                    'total' => $items->count(),
                    'lists' => $lists->sortBy(fn (WeeklyTaskList $list): int => $list->employee->sort_order)->values(),
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /**
     * Move what an employee did not finish in one week into the week that
     * follows, creating that week's list where it does not exist yet.
     *
     * The original is left where it is: the week that has passed is a record of
     * what was owed and what got done, and emptying it after the fact would
     * make every past week look finished.
     *
     * Nothing is duplicated — a title already present in the next week is
     * skipped, which is what keeps a carried task and the template that
     * produces it every week from arriving twice.
     *
     * @return array{carried: int, employees: int, from: string, to: string}
     */
    public function carryForwardFrom(CarbonInterface $date): array
    {
        $from = WeeklyTaskList::weekStartFor($date);
        $to = $from->copy()->addWeek();

        $sources = WeeklyTaskList::query()
            ->with(['items', 'employee'])
            ->whereDate('week_start', $from->toDateString())
            ->whereHas('employee', fn ($query) => $query->where('is_active', true))
            ->get();

        $carried = 0;
        $employees = 0;

        foreach ($sources as $source) {
            $outstanding = $source->items->where('is_done', false);

            if ($outstanding->isEmpty()) {
                continue;
            }

            $moved = 0;

            DB::transaction(function () use ($source, $outstanding, $from, $to, &$moved): void {
                // whereDate لا المطابقة المباشرة: العمود يُخزَّن بوقت، فالمقارنة
                // بتاريخ مجرّد لا تجد الصفّ الموجود ثم ترتطم بقيد التفرّد.
                $target = WeeklyTaskList::query()
                    ->where('employee_id', $source->employee_id)
                    ->whereDate('week_start', $to->toDateString())
                    ->first()
                    ?? WeeklyTaskList::query()->create([
                        'employee_id' => $source->employee_id,
                        'week_start' => $to->toDateString(),
                    ]);

                $present = $target->items()->pluck('title');
                $nextOrder = (int) $target->items()->max('sort_order');

                $rows = $outstanding
                    ->reject(fn (WeeklyTaskItem $item): bool => $present->contains($item->title))
                    ->values()
                    ->map(fn (WeeklyTaskItem $item, int $index): array => [
                        'title' => $item->title,
                        'weekly_task_category_id' => $item->weekly_task_category_id,
                        'sort_order' => $nextOrder + $index + 1,
                        // A task outstanding for weeks keeps naming the week it
                        // was first owed in, not merely the one before this.
                        'carried_from' => $item->carried_from?->toDateString() ?? $from->toDateString(),
                    ])
                    ->all();

                if ($rows !== []) {
                    $target->items()->createMany($rows);
                    $moved = count($rows);
                }
            });

            if ($moved > 0) {
                $carried += $moved;
                $employees++;
            }
        }

        return [
            'carried' => $carried,
            'employees' => $employees,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    /**
     * Group the items under their category heading, in the order the categories
     * are arranged, with anything uncategorised last.
     *
     * A list with no categories at all comes back under a single empty heading,
     * which reads exactly as it did before categories existed.
     *
     * @param  Collection<int, \App\Models\WeeklyTaskItem>  $items
     * @return Collection<string, Collection<int, \App\Models\WeeklyTaskItem>>
     */
    private function byCategory(Collection $items): Collection
    {
        if ($items->every(fn ($item): bool => $item->category === null)) {
            return collect(['' => $items]);
        }

        // Uncategorised sinks below every category, whatever its order. Note
        // that sortBy with an array takes comparators, not key extractors.
        $rank = fn ($item): array => $item->category === null
            ? [PHP_INT_MAX, PHP_INT_MAX]
            : [$item->category->sort_order, $item->category->id];

        return $items
            ->sortBy([
                fn ($a, $b): int => $rank($a) <=> $rank($b),
                fn ($a, $b): int => $a->sort_order <=> $b->sort_order,
                fn ($a, $b): int => $a->id <=> $b->id,
            ])
            ->groupBy(fn ($item): string => $item->category->name ?? 'أخرى');
    }

    /**
     * @return Collection<int, WeeklyTaskList>
     */
    public function listsForWeek(CarbonInterface $date): Collection
    {
        return WeeklyTaskList::query()
            ->with(['employee', 'items.category'])
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
            $number = 0;

            foreach ($this->byCategory($list->items) as $heading => $items) {
                if ($heading !== '') {
                    $lines[] = '◾ '.$heading;
                }

                foreach ($items as $item) {
                    $lines[] = (++$number).'. '.$item->title;
                }
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
            $totalDone += $done->count();
            $totalAll += $list->items->count();

            $lines[] = '*'.$list->employee->name.'* — '.$done->count().' من '.$list->items->count();

            foreach ($this->byCategory($list->items) as $heading => $items) {
                if ($heading !== '') {
                    $lines[] = '◾ '.$heading;
                }

                // Done first within the heading, so the week reads as progress.
                foreach ($items->sortByDesc('is_done') as $item) {
                    $lines[] = ($item->is_done ? '✅ ' : '⬜ ').$item->title;
                }
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
