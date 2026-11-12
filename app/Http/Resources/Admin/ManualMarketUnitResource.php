<?php

namespace App\Http\Resources\Admin;

use App\Models\ManualMarketUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ManualMarketUnit
 */
class ManualMarketUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'project_name' => $this->project_name,
            'city' => $this->city,
            'district' => $this->district,
            'kind' => $this->kind,
            'area' => $this->area,
            'price' => $this->price,
            'rate' => $this->price_per_meter,
        ];
    }
}
