<?php

namespace App\Http\Resources\Admin;

use App\Models\WhatsappMessageRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'sender' => $this->admin?->name,
            'recipients_count' => $this->recipients_count,
            'skipped_count' => $this->skipped_count,
            'created_at' => $this->created_at?->toISOString(),
            'counts' => [
                'queued' => $this->countOf(WhatsappMessageRecipient::STATUS_QUEUED),
                'sent' => $this->countOf(WhatsappMessageRecipient::STATUS_SENT),
                'delivered' => $this->countOf(WhatsappMessageRecipient::STATUS_DELIVERED),
                'read' => $this->countOf(WhatsappMessageRecipient::STATUS_READ),
                'failed' => $this->countOf(WhatsappMessageRecipient::STATUS_FAILED),
            ],
            'recipients' => $this->recipients->map(fn (WhatsappMessageRecipient $recipient) => [
                'id' => $recipient->id,
                'lead_id' => $recipient->lead_id,
                'name' => $recipient->name,
                'phone' => $recipient->phone,
                'status' => $recipient->status,
                'error' => $recipient->error,
                'sent_at' => $recipient->sent_at?->toISOString(),
                'delivered_at' => $recipient->delivered_at?->toISOString(),
                'read_at' => $recipient->read_at?->toISOString(),
            ])->values(),
        ];
    }

    private function countOf(string $status): int
    {
        return $this->recipients->where('status', $status)->count();
    }
}
