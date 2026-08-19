<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WeeklyTaskList
 */
class WeeklyTaskListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'week_start' => $this->week_start?->toDateString(),
            'items' => WeeklyTaskItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
