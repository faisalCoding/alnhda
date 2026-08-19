<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'url' => $this->url,
            'expires_on' => $this->expires_on?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry(),
            // The billing detail itself only ever travels via the reveal endpoint.
            'has_payment_account' => filled($this->getRawOriginal('payment_account')),
            'note' => $this->note,
            'sort_order' => $this->sort_order,
        ];
    }
}
