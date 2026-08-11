<?php

namespace App\Console\Commands;

use App\Services\WhatsappServiceProcess;
use Illuminate\Console\Command;

class WhatsappStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show whether the WhatsApp gateway is running';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappServiceProcess $process): int
    {
        $pid = $process->runningPid();

        if ($pid === null) {
            $this->warn('الخدمة متوقفة. للتشغيل: php artisan whatsapp:start');

            return self::FAILURE;
        }

        $this->info('الخدمة تعمل — المنفذ '.$process->port().'، المعرف '.$pid.'.');
        $this->line('السجل: '.$process->logPath());

        return self::SUCCESS;
    }
}
