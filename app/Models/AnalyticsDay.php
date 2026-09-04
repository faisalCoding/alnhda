<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One day of traffic as Google Analytics recorded it.
 */
class AnalyticsDay extends Model
{
    /** @use HasFactory<\Database\Factories\AnalyticsDayFactory> */
    use HasFactory;

    protected $fillable = ['date', 'users', 'sessions', 'views'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'users' => 'integer',
            'sessions' => 'integer',
            'views' => 'integer',
        ];
    }
}
