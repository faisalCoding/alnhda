<?php

namespace App\Console\Commands;

use App\Models\WhatsappMessageRecipient;
use App\Services\WhatsappGateway;
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
    public function handle(WhatsappGateway $gateway): int
    {
        $pending = WhatsappMessageRecipient::query()
            ->whereNotNull('provider_message_id')
            ->whereIn('status', [WhatsappMessageRecipient::STATUS_SENT, WhatsappMessageRecipient::STATUS_DELIVERED])
            ->where('sent_at', '>=', now()->subHours((int) $this->option('hours')))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('لا توجد رسائل بانتظار تأكيد الاستلام.');

            return self::SUCCESS;
        }

        $acks = $gateway->acknowledgements($pending->pluck('provider_message_id')->all());

        if ($acks === []) {
            $this->warn('لم تُرجع البوابة أي تأكيدات — تأكد أنها تعمل ولم يُعد تشغيلها بعد الإرسال.');

            return self::FAILURE;
        }

        $updated = 0;

        foreach ($pending as $recipient) {
            $ack = $acks[$recipient->provider_message_id] ?? null;

            if ($ack !== null && $recipient->applyAcknowledgement((int) $ack)) {
                $updated++;
            }
        }

        $this->info("حُدّثت حالة {$updated} رسالة من أصل {$pending->count()}.");

        return self::SUCCESS;
    }
}
