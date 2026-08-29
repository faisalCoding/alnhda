<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Every lookup of a week's report goes through here.
     *
     * The date cast writes the column with a time on it, so comparing it
     * against a bare date matches nothing — and an upsert that matches nothing
     * inserts, straight into the unique index. Keeping the one correct
     * comparison in one place is what stops that from being rediscovered.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForWeek(Builder $query, string $weekStart, string $kind): Builder
    {
        return $query->whereDate('week_start', $weekStart)->where('kind', $kind);
    }

    public static function alreadySent(string $weekStart, string $kind): bool
    {
        return static::query()->forWeek($weekStart, $kind)->exists();
    }

    /**
     * When this week's report of that kind went out, or null if it has not.
     */
    public static function sentAt(string $weekStart, string $kind): ?CarbonInterface
    {
        return static::query()->forWeek($weekStart, $kind)->first()?->sent_at;
    }

    /**
     * Records a report as sent, whether or not one was recorded before.
     */
    public static function record(string $weekStart, string $kind): self
    {
        $send = static::query()->forWeek($weekStart, $kind)->first()
            ?? new static(['week_start' => $weekStart, 'kind' => $kind]);

        $send->sent_at = now();
        $send->save();

        return $send;
    }
}
