<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SocialPlatform
 */
class SocialPlatformResource extends JsonResource
{
    /**
     * Note the absence of the password: it never travels with a platform, only
     * through the reveal endpoint, so it cannot end up in the panel's cache.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tasks = $this->whenLoaded('tasks');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'has_password' => filled($this->getRawOriginal('password')),
            'sort_order' => $this->sort_order,
            'tasks' => SocialPlatformTaskResource::collection($tasks),
            'tasks_total' => $this->whenCounted('tasks'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
