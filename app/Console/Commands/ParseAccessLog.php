<?php

namespace App\Console\Commands;

use App\Models\ServerLogDay;
use App\Services\Analytics\AccessLogReader;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ParseAccessLog extends Command
{
    protected $signature = 'analytics:parse-log {--date= : The day to summarise, defaults to yesterday}';

    protected $description = 'Summarise a day of the web server access log';

    public function handle(AccessLogReader $reader): int
    {
        $path = (string) config('services.access_log.path');
        $date = $this->option('date') ? Carbon::parse((string) $this->option('date')) : Carbon::yesterday();

        // Apache rotates the log overnight, so yesterday's lines are usually in
        // access.log.1 by the time this runs — both are read, and whichever
        // holds the day wins.
        $candidates = array_filter([$path, $path.'.1'], 'is_readable');

        if ($candidates === []) {
            $this->warn('سجل الخادم غير موجود أو غير مقروء: '.$path);

            return self::SUCCESS;
        }

        foreach ($candidates as $candidate) {
            $summary = $reader->summarise($candidate, $date);

            if ($summary !== null) {
                // Matched on a Carbon date for the same reason as the analytics
                // pull: the cast column stores a datetime, so a bare string
                // would never find the row written yesterday.
                ServerLogDay::query()->updateOrCreate(
                    ['date' => Carbon::parse($summary['date'])],
                    collect($summary)->except('date')->all()
                );

                $this->info('لُخّص '.$summary['requests'].' طلبًا ليوم '.$summary['date'].' من '.$candidate.'.');

                return self::SUCCESS;
            }
        }

        $this->warn('لا توجد أسطر ليوم '.$date->toDateString().' في سجل الخادم.');

        return self::SUCCESS;
    }
}
