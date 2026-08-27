<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WeeklyTaskItem
 */
class WeeklyTaskItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekly_task_list_id' => $this->weekly_task_list_id,
            'weekly_task_category_id' => $this->weekly_task_category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'color' => $this->category->color,
                'sort_order' => $this->category->sort_order,
            ]),
            'title' => $this->title,
            'is_done' => $this->is_done,
            'completed_at' => $this->completed_at?->toISOString(),
            'sort_order' => $this->sort_order,
            'carried_from' => $this->carried_from?->toDateString(),
        ];
    }
}
