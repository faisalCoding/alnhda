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
            'label' => $this->label(),
            'name' => $this->name,
            'project_name' => $this->project_name,
            'source_site' => $this->source_site,
            'site_label' => $this->siteLabel(),
            'listing_id' => $this->listing_id,
            'city' => $this->city,
            'city_key' => $this->city_key,
            'district' => $this->district,
            'district_key' => $this->district_key,
            'kind' => $this->kind,
            'area' => $this->area,
            'price' => $this->price,
            'rate' => $this->price_per_meter,
            'rooms' => $this->rooms,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'street_width' => $this->street_width,
            'facade' => $this->facade,
            'property_age' => $this->property_age,
            'property_age_years' => $this->property_age_years,
            'posted_on' => $this->posted_on?->toDateString(),
            'listing_updated_on' => $this->listing_updated_on?->toDateString(),
            'observed_on' => $this->seenOn()?->toDateString(),
            // Too old to say what the market asks now: listed, not compared with.
            'fresh' => $this->isFresh(),
            'source_url' => $this->source_url,
            'duplicates' => $this->duplicates,
            'duplicate_group' => $this->duplicate_group,
            // How many listings name this property, itself included.
            'group_size' => $this->group_size ?? 1,
        ];
    }
}
