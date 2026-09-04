<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The period breakdowns behind the daily totals.
 */
class AnalyticsSummary extends Model
{
    public const CURRENT_PERIOD = 'last_28_days';

    protected $fillable = ['period', 'pulled_at', 'top_pages', 'channels', 'devices', 'cities'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pulled_at' => 'datetime',
            'top_pages' => 'array',
            'channels' => 'array',
            'devices' => 'array',
            'cities' => 'array',
        ];
    }
}
