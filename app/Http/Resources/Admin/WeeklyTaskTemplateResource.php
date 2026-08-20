<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WeeklyTaskTemplate
 */
class WeeklyTaskTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->name),
            'weekly_task_category_id' => $this->weekly_task_category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'category_color' => $this->whenLoaded('category', fn () => $this->category?->color),
            'title' => $this->title,
            'sort_order' => $this->sort_order,
        ];
    }
}
