<?php

namespace App\Jobs;

use App\Services\WhatsappGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendLeadWhatsappJob implements ShouldQueue
{
    use Queueable;

    /**
     * Sending waits a random human-like pause first, so the job needs more than
     * the default 60s before the worker considers it stuck.
     */
    public int $timeout = 180;

    public function __construct(
        public string $clientId,
        public string $phone,
        public string $message,
    ) {}

    public function handle(WhatsappGateway $whatsapp): void
    {
        $this->humanPause();

        if (! $whatsapp->send($this->clientId, $this->phone, $this->message)) {
            Log::error('WhatsApp send failed', ['phone' => $this->phone, 'client_id' => $this->clientId]);
        }
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
