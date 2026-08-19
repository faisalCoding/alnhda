<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backlink extends Model
{
    /** @use HasFactory<\Database\Factories\BacklinkFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'target_url',
        'visits',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visits' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
