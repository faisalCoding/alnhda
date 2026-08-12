<?php

namespace App\Services;

use App\Models\WhatsappMessageRecipient;

/**
 * Pulls delivery acknowledgements from the gateway into the database. Shared by
 * the scheduled command and the panel button, so both report the same numbers.
 */
class WhatsappAcknowledgementSync
{
    public function __construct(private WhatsappGateway $gateway) {}

    /**
     * @return array{reachable: bool, checked: int, updated: int, known: int, untrackable: int}
     */
    public function sync(int $hours = 48): array
    {
        // Rows without a provider id predate message-id tracking and can never
        // be confirmed; counted so the caller can say so instead of staying mute.
        $untrackable = WhatsappMessageRecipient::query()
            ->whereNull('provider_message_id')
            ->where('status', WhatsappMessageRecipient::STATUS_SENT)
            ->count();

        $pending = WhatsappMessageRecipient::query()
            ->whereNotNull('provider_message_id')
            ->whereIn('status', [WhatsappMessageRecipient::STATUS_SENT, WhatsappMessageRecipient::STATUS_DELIVERED])
            ->where('sent_at', '>=', now()->subHours($hours))
            ->get();

        if ($pending->isEmpty()) {
            return ['reachable' => true, 'checked' => 0, 'updated' => 0, 'known' => 0, 'untrackable' => $untrackable];
        }

        $acks = $this->gateway->acknowledgements($pending->pluck('provider_message_id')->all());

        if ($acks === null) {
            return ['reachable' => false, 'checked' => $pending->count(), 'updated' => 0, 'known' => 0, 'untrackable' => $untrackable];
        }

        $updated = 0;

        foreach ($pending as $recipient) {
            $ack = $acks[$recipient->provider_message_id] ?? null;

            if ($ack !== null && $recipient->applyAcknowledgement((int) $ack)) {
                $updated++;
            }
        }

        return [
            'reachable' => true,
            'checked' => $pending->count(),
            'updated' => $updated,
            'known' => count($acks),
            'untrackable' => $untrackable,
        ];
    }
}
