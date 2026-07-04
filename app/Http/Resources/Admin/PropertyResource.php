<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ResolvesPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    use ResolvesPublicUrl;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'project_id' => $this->project_id,
            'price' => $this->price,
            'offer' => $this->offer,
            'status' => $this->status,
            'rooms' => $this->rooms,
            'bathrooms' => $this->bathrooms,
            'living_rooms' => $this->living_rooms,
            'mainds_room' => $this->mainds_room,
            'area' => $this->area,
            'doors' => $this->doors,
            'type' => $this->type,
            'parkings' => $this->parkings,
            'driver_room' => $this->driver_room,
            'facade' => $this->facade,
            'furniture' => (bool) $this->furniture,
            'unit_youtube' => $this->unit_youtube,
            'stages_building_youtube' => $this->stages_building_youtube,
            'pdf_path' => $this->pdf_path,
            'pdf_url' => $this->publicUrl($this->pdf_path),
            'images' => ImagePropertyResource::collection($this->whenLoaded('propertiesImages')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
