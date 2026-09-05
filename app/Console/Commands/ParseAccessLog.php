<?php

namespace App\Console\Commands;

use App\Models\ServerLogDay;
use App\Services\Analytics\AccessLogReader;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ParseAccessLog extends Command
{
    protected $signature = 'analytics:parse-log
        {--date= : The day to summarise, defaults to yesterday}
        {--backfill= : Summarise this many days back, reading the rotated archive too}';

    protected $description = 'Summarise a day of the web server access log';

    public function handle(AccessLogReader $reader): int
    {
        $path = (string) config('services.access_log.path');
        $candidates = $this->candidates($path);

        if ($candidates === []) {
            $this->warn('سجل الخادم غير موجود أو غير مقروء: '.$path);

            return self::SUCCESS;
        }

        $days = $this->days();
        $written = 0;

        foreach ($days as $date) {
            foreach ($candidates as $candidate) {
                $summary = $reader->summarise($candidate, $date);

                if ($summary === null) {
                    continue;
                }

                // Matched on a Carbon date for the same reason as the analytics
                // pull: the cast column stores a datetime, so a bare string
                // would never find the row written yesterday.
                ServerLogDay::query()->updateOrCreate(
                    ['date' => Carbon::parse($summary['date'])],
                    collect($summary)->except('date')->all()
                );

                $this->info('لُخّص '.$summary['requests'].' طلبًا ليوم '.$summary['date'].'.');
                $written++;

                break;
            }
        }

        if ($written === 0) {
            $this->warn('لا توجد أسطر لتلك الأيام في سجل الخادم.');
        }

        return self::SUCCESS;
    }

    /**
     * The days to summarise: one, or a stretch back through the archive.
     *
     * @return list<Carbon>
     */
    private function days(): array
    {
        if ($backfill = (int) $this->option('backfill')) {
            $end = Carbon::yesterday();

            return collect(range(0, max(0, min(365, $backfill) - 1)))
                ->map(fn (int $back): Carbon => $end->copy()->subDays($back))
                ->all();
        }

        return [$this->option('date') ? Carbon::parse((string) $this->option('date')) : Carbon::yesterday()];
    }

    /**
     * The live log and everything logrotate has kept, newest first. A day is
     * looked for in each until it is found: after a rotation yesterday lives in
     * `.1`, and older days in the gzipped ones behind it.
     *
     * @return list<string>
     */
    private function candidates(string $path): array
    {
        $rotated = glob($path.'.*') ?: [];

        // .1, .2.gz, .3.gz … sorted by their number rather than as text, so
        // .10.gz does not land between .1 and .2.
        usort($rotated, function (string $a, string $b) use ($path): int {
            $number = fn (string $file): int => (int) filter_var($file, FILTER_SANITIZE_NUMBER_INT) ?: 0;

            return $number(str_replace($path, '', $a)) <=> $number(str_replace($path, '', $b));
        });

        return array_values(array_filter([$path, ...$rotated], 'is_readable'));
    }
}
