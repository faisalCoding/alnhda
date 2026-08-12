<?php

namespace App\Console\Commands;

use App\Services\WhatsappServiceProcess;
use Illuminate\Console\Command;

class WhatsappStop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:stop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop the background WhatsApp gateway';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappServiceProcess $process): int
    {
        return match ($process->stop()) {
            'stopped' => $this->done('تم إيقاف الخدمة.'),
            'not_running' => $this->done('الخدمة متوقفة أصلًا.'),
            'pid_unknown' => $this->cannot('الخدمة تعمل لكن تعذر تحديد رقم عمليتها (lsof/ss/fuser غير متوفرة). أوقفها يدويًا.'),
            default => $this->cannot('تعذر إيقاف الخدمة — shell_exec معطّل على هذا الخادم.'),
        };
    }

    private function done(string $message): int
    {
        $this->info($message);

        return self::SUCCESS;
    }

    private function cannot(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
