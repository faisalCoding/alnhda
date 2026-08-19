<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AdvertisingLicence
 */
class AdvertisingLicenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'properties_id' => $this->properties_id,
            'unit_name' => $this->unit_name,
            'unit_label' => $this->unitLabel(),
            'project_name' => $this->whenLoaded('unit', fn () => $this->unit?->project?->name),
            'licence_number' => $this->licence_number,
            'expires_on' => $this->expires_on?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry(),
            'note' => $this->note,
            'sort_order' => $this->sort_order,
        ];
    }
}
