<?php

namespace App\Console\Commands;

use App\Services\WhatsappServiceProcess;
use Illuminate\Console\Command;

class WhatsappStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the WhatsApp gateway as a detached background process';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappServiceProcess $process): int
    {
        if (! $process->isInstalled()) {
            $this->error('حزم خدمة الواتساب غير مثبتة. شغّل: npm install --prefix whatsapp-service');

            return self::FAILURE;
        }

        return match ($process->start()) {
            'already_running' => $this->report('الخدمة تعمل مسبقًا على المنفذ '.$process->port().'.', $process),
            'started' => $this->report('تم تشغيل الخدمة في الخلفية على المنفذ '.$process->port().'.', $process),
            default => $this->cannotStart(),
        };
    }

    private function report(string $message, WhatsappServiceProcess $process): int
    {
        $this->info($message);
        $this->line('السجل: '.$process->logPath());
        $this->line('للإيقاف: php artisan whatsapp:stop');

        return self::SUCCESS;
    }

    private function cannotStart(): int
    {
        $this->error('تعذر تشغيل الخدمة — تأكد من توفر node ومن أن shell_exec غير معطّل.');

        return self::FAILURE;
    }
}
