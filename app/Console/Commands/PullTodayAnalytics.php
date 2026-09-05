<?php

namespace App\Console\Commands;

use App\Models\AnalyticsDay;
use App\Models\ServerLogDay;
use App\Services\Analytics\AccessLogReader;
use App\Services\Analytics\Ga4Reporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Refreshes the day that is still running.
 *
 * The nightly jobs deal in finished days; this one keeps today's partial row
 * current through the day and is overwritten by them once the day closes, so a
 * half-counted number never survives into the history.
 */
class PullTodayAnalytics extends Command
{
    protected $signature = 'analytics:today';

    protected $description = 'Refresh the traffic for the day still in progress';

    public function handle(Ga4Reporter $reporter, AccessLogReader $reader): int
    {
        $today = Carbon::today();

        $this->refreshLog($reader, $today);
        $this->refreshAnalytics($reporter, $today);

        return self::SUCCESS;
    }

    private function refreshLog(AccessLogReader $reader, Carbon $today): void
    {
        $path = (string) config('services.access_log.path');

        if (! is_readable($path)) {
            return;
        }

        // Only the live file: a day still running has not been rotated away.
        $summary = $reader->summarise($path, $today);

        if ($summary === null) {
            return;
        }

        ServerLogDay::query()->updateOrCreate(
            ['date' => $today],
            collect($summary)->except('date')->all()
        );

        $this->info('سجل اليوم: '.$summary['requests'].' طلبًا حتى الآن.');
    }

    private function refreshAnalytics(Ga4Reporter $reporter, Carbon $today): void
    {
        if (! $reporter->isConfigured()) {
            return;
        }

        try {
            foreach ($reporter->dailyTotals($today, $today) as $day) {
                AnalyticsDay::query()->updateOrCreate(
                    ['date' => Carbon::parse($day['date'])],
                    ['users' => $day['users'], 'sessions' => $day['sessions'], 'views' => $day['views']]
                );

                $this->info('تحليلات اليوم: '.$day['users'].' زائرًا حتى الآن.');
            }
        } catch (Throwable $e) {
            // A running day is refreshed again in a few minutes; a failure here
            // must not turn the scheduler red every quarter of an hour.
            $this->warn($e->getMessage());
        }
    }
}
