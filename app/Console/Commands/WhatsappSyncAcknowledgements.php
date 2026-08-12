<?php

namespace App\Console\Commands;

use App\Services\WhatsappAcknowledgementSync;
use Illuminate\Console\Command;

/**
 * Pulls delivery acknowledgements from the gateway. The service reports them as
 * they arrive but only keeps them in memory, so this promotes "sent" rows to
 * delivered/read (or failed) in the database, which is the durable record.
 */
class WhatsappSyncAcknowledgements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:sync-acks {--hours=48 : How far back to look for unconfirmed messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update delivery confirmation for messages WhatsApp has not confirmed yet';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappAcknowledgementSync $sync): int
    {
        $result = $sync->sync((int) $this->option('hours'));

        // Only an unreachable gateway is a failure. Having nothing new to report
        // is the normal case, and this runs on a schedule — treating it as an
        // error would cry wolf every few minutes.
        if (! $result['reachable']) {
            $this->error('تعذر الوصول إلى البوابة — تأكد أن الخدمة تعمل.');

            return self::FAILURE;
        }

        if ($result['untrackable'] > 0) {
            $this->warn($result['untrackable'].' رسالة أُرسلت دون معرّف ولا يمكن تأكيد استلامها.');
        }

        if ($result['checked'] === 0) {
            $this->info('لا توجد رسائل بانتظار تأكيد الاستلام.');

            return self::SUCCESS;
        }

        $this->info("حُدّثت حالة {$result['updated']} رسالة من أصل {$result['checked']} (البوابة تعرف {$result['known']}).");

        return self::SUCCESS;
    }
}
