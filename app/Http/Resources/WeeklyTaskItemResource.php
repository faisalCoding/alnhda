<?php

namespace App\Http\Resources;

use App\Models\WeeklyTaskItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WeeklyTaskItem */
class WeeklyTaskItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'week_start' => $this->list->week_start->toDateString(),
            'title' => $this->title,
            'is_done' => $this->is_done,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'carried_from' => $this->carried_from?->toDateString(),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
