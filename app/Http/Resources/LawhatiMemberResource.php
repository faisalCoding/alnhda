<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee as لوحتي shows them: a name and an avatar on the cards assigned to them.
 *
 * @mixin \App\Models\Employee
 */
class LawhatiMemberResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, role: string|null, is_active: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'is_active' => $this->is_active,
        ];
    }
}
