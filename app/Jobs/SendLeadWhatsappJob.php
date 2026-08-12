<?php

namespace App\Jobs;

use App\Models\WhatsappMessageRecipient;
use App\Services\WhatsappGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendLeadWhatsappJob implements ShouldQueue
{
    use Queueable;

    /**
     * Must stay below the queue's retry_after (90s by default), or the job is
     * released for retry while still running and the lead gets the message
     * twice. The real ceiling is well under this: at most a 14s pause plus the
     * gateway's 15s HTTP timeout.
     */
    public int $timeout = 60;

    public function __construct(
        public string $clientId,
        public int $recipientId,
    ) {}

    public function handle(WhatsappGateway $whatsapp): void
    {
        $recipient = WhatsappMessageRecipient::query()->with('message')->find($this->recipientId);

        if ($recipient === null || $recipient->status !== WhatsappMessageRecipient::STATUS_QUEUED) {
            return;
        }

        $this->humanPause();

        // The template is stored once on the message; the name is filled in per
        // recipient here so the history shows what was actually composed.
        $body = str_replace('{الاسم}', $recipient->name, $recipient->message->body);

        $result = $whatsapp->send($this->clientId, $recipient->phone, $body);

        if (! $result['sent']) {
            Log::error('WhatsApp send failed', [
                'recipient_id' => $recipient->id,
                'phone' => $recipient->phone,
                'error' => $result['error'] ?? null,
            ]);

            $recipient->update([
                'status' => WhatsappMessageRecipient::STATUS_FAILED,
                'error' => $result['error'] ?? 'تعذر الإرسال.',
            ]);

            return;
        }

        $recipient->update([
            'status' => WhatsappMessageRecipient::STATUS_SENT,
            'provider_message_id' => $result['message_id'] ?? null,
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    /**
     * Marks the recipient failed when the job itself blows up, so a row never
     * sits at "queued" forever with nothing explaining why.
     */
    public function failed(?\Throwable $exception): void
    {
        WhatsappMessageRecipient::query()
            ->where('id', $this->recipientId)
            ->where('status', WhatsappMessageRecipient::STATUS_QUEUED)
            ->update([
                'status' => WhatsappMessageRecipient::STATUS_FAILED,
                'error' => $exception?->getMessage() ?? 'فشلت مهمة الإرسال.',
            ]);
    }

    /**
     * Pause for a random interval before each send so a bulk campaign trickles
     * out at a human pace instead of a burst that WhatsApp flags as spam. A
     * single queue worker processes jobs serially, so this spaces them out.
     * Set both config values to 0 to disable.
     */
    private function humanPause(): void
    {
        $min = max(0, (int) config('services.whatsapp.send_delay_min'));
        $max = max($min, (int) config('services.whatsapp.send_delay_max'));

        if ($max > 0) {
            sleep(random_int($min, $max));
        }
    }
}
