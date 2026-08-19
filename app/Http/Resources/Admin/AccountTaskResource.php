<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AccountTask
 */
class AccountTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'title' => $this->title,
            'is_done' => $this->is_done,
            'completed_at' => $this->completed_at?->toISOString(),
            'sort_order' => $this->sort_order,
        ];
    }
}
