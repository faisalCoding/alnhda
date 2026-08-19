<?php

namespace App\Console\Commands;

use App\Services\WeeklyTaskPlanner;
use Illuminate\Console\Command;

class WeeklyTasksGenerate extends Command
{
    protected $signature = 'weekly-tasks:generate {--date= : Any day inside the week to open, defaults to today}';

    protected $description = 'Open this week\'s task list for every active employee';

    public function __construct(private WeeklyTaskPlanner $planner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date') ? now()->parse($this->option('date')) : now();

        $result = $this->planner->generateFor($date);

        $this->info("أسبوع {$result['week_start']}: أُنشئت {$result['created']} قائمة، وتُركت {$result['skipped']} قائمة موجودة.");

        return self::SUCCESS;
    }
}
