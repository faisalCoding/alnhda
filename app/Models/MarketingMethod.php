<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingMethod extends Model
{
    /** @use HasFactory<\Database\Factories\MarketingMethodFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
