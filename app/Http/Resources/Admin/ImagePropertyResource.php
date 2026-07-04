<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ResolvesPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImagePropertyResource extends JsonResource
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
            'properties_id' => $this->properties_id,
            'url' => $this->url,
            'full_url' => $this->publicUrl($this->url),
            'order_by' => $this->order_by,
        ];
    }
}
