<?php

namespace App\Console\Commands;

use App\Services\WhatsappServiceProcess;
use Illuminate\Console\Command;

class WhatsappRestart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart the WhatsApp gateway so a deployed change takes effect';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappServiceProcess $process): int
    {
        if (! $process->isInstalled()) {
            $this->error('حزم خدمة الواتساب غير مثبتة. شغّل: npm install --prefix whatsapp-service');

            return self::FAILURE;
        }

        return match ($process->restart()) {
            'started' => $this->done('أُعيد تشغيل الخدمة على المنفذ '.$process->port().'.'),
            'stop_failed' => $this->failed('تعذر إيقاف الخدمة القديمة — أوقفها يدويًا ثم أعد المحاولة.'),
            default => $this->failed('تعذر تشغيل الخدمة — راجع '.$process->logPath()),
        };
    }

    private function done(string $message): int
    {
        $this->info($message);

        return self::SUCCESS;
    }

    private function failed(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
