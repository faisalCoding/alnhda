<?php

namespace App\Console\Commands;

use App\Models\AnalyticsDay;
use App\Models\AnalyticsSummary;
use App\Services\Analytics\Ga4Reporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class PullAnalytics extends Command
{
    protected $signature = 'analytics:pull {--days=30 : How many days back to refresh}';

    protected $description = 'Copy the traffic Google Analytics recorded into the panel';

    public function handle(Ga4Reporter $reporter): int
    {
        if ($problem = $reporter->configurationProblem()) {
            $this->warn($problem);

            return self::SUCCESS;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        // Yesterday, not today: a day still running would be stored half-counted
        // and then never corrected.
        $to = Carbon::yesterday();
        $from = $to->copy()->subDays($days - 1);

        try {
            foreach ($reporter->dailyTotals($from, $to) as $day) {
                // Matched on a Carbon date, not the string: the column is cast to
                // a date, so a raw string would never match what was stored and
                // every run would try to insert the same day again.
                AnalyticsDay::query()->updateOrCreate(
                    ['date' => Carbon::parse($day['date'])],
                    ['users' => $day['users'], 'sessions' => $day['sessions'], 'views' => $day['views']]
                );
            }

            AnalyticsSummary::query()->updateOrCreate(
                ['period' => AnalyticsSummary::CURRENT_PERIOD],
                [
                    'pulled_at' => now(),
                    'top_pages' => $reporter->breakdown($from, $to, 'pagePath', 'screenPageViews'),
                    'channels' => $reporter->breakdown($from, $to, 'sessionDefaultChannelGroup', 'sessions'),
                    'devices' => $reporter->breakdown($from, $to, 'deviceCategory', 'sessions', 5),
                    'cities' => $reporter->breakdown($from, $to, 'city', 'sessions'),
                ]
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('حُدّثت حركة السير حتى '.$to->toDateString().'.');

        return self::SUCCESS;
    }
}
