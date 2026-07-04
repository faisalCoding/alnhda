<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ResolvesPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'description' => $this->description,
            'location' => $this->location,
            'status' => $this->status,
            'project_type' => $this->project_type,
            'map_url' => $this->map_url,
            'guarantees' => $this->guarantees ?? [],
            'image_url' => $this->image_url,
            'image_full_url' => $this->publicUrl($this->image_url),
            'pdf_path' => $this->pdf_path,
            'pdf_url' => $this->publicUrl($this->pdf_path),
            'properties_count' => $this->whenCounted('properties'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
