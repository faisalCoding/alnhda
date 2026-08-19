<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyReportSend extends Model
{
    protected $fillable = [
        'week_start',
        'kind',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public static function alreadySent(string $weekStart, string $kind): bool
    {
        return static::query()->whereDate('week_start', $weekStart)->where('kind', $kind)->exists();
    }
}
