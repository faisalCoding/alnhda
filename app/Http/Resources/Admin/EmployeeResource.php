<?php

namespace App\Http\Resources\Admin;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'enrolled_on' => $this->enrolled_on?->toDateString(),
            'sort_order' => $this->sort_order,
            'has_lawhati_code' => (bool) ($this->has_lawhati_code
                ?? $this->tokens()->where('name', Employee::LAWHATI_TOKEN)->exists()),
        ];
    }
}
