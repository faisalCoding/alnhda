<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Account
 */
class AccountResource extends JsonResource
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
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->all(), []),
            'categories' => AccountCategoryResource::collection($this->whenLoaded('categories')),
            'name' => $this->name,
            'identifier' => $this->identifier,
            'url' => $this->url,
            'has_password' => filled($this->getRawOriginal('password')),
            'sort_order' => $this->sort_order,
            'tasks' => AccountTaskResource::collection($tasks),
            'tasks_total' => $this->whenCounted('tasks'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
