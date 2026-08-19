<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AppSettings;
use App\Services\WeeklyTaskPlanner;
use App\Services\WhatsappGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WeeklyTasksReport extends Command
{
    protected $signature = 'weekly-tasks:report
        {kind : opening for the Saturday brief, closing for the Thursday summary}
        {--date= : Any day inside the week to report on, defaults to today}
        {--dry-run : Print the message instead of sending it}';

    protected $description = 'Send the weekly task brief or summary to the configured WhatsApp group';

    public function __construct(
        private WeeklyTaskPlanner $planner,
        private WhatsappGateway $gateway,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $kind = (string) $this->argument('kind');

        if (! in_array($kind, ['opening', 'closing'], true)) {
            $this->error('النوع يجب أن يكون opening أو closing.');

            return self::FAILURE;
        }

        $date = $this->option('date') ? now()->parse($this->option('date')) : now();

        $message = $kind === 'opening'
            ? $this->planner->openingMessage($date)
            : $this->planner->closingMessage($date);

        if ($message === null) {
            $this->warn('لا توجد مهام لهذا الأسبوع، فلم تُرسل رسالة.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line($message);

            return self::SUCCESS;
        }

        $settings = AppSettings::current();

        if (! $settings->weeklyReportsAreReady()) {
            $this->warn('التقارير الأسبوعية غير مفعّلة أو لم تُحدَّد مجموعة، فلم تُرسل رسالة.');

            return self::SUCCESS;
        }

        // The gateway keeps one session per admin; the reports go out from the
        // first linked one rather than from whoever happens to be signed in.
        $admin = Admin::query()->orderBy('id')->first();

        if ($admin === null) {
            $this->error('لا يوجد حساب إداري لإرسال الرسالة من جلسته.');

            return self::FAILURE;
        }

        $result = $this->gateway->sendToGroup(
            $this->gateway->clientIdFor($admin),
            (string) $settings->whatsapp_group_id,
            $message,
        );

        if (! ($result['sent'] ?? false)) {
            $error = $result['error'] ?? 'سبب غير معروف';
            Log::warning('weekly task report failed', ['kind' => $kind, 'error' => $error]);
            $this->error('تعذر الإرسال: '.$error);

            return self::FAILURE;
        }

        $this->info('أُرسلت الرسالة إلى '.($settings->whatsapp_group_name ?? 'المجموعة المحددة').'.');

        return self::SUCCESS;
    }
}
