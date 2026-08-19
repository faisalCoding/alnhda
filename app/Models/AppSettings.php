<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSettings extends Model
{
    protected $fillable = [
        'whatsapp_group_id',
        'whatsapp_group_name',
        'weekly_reports_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekly_reports_enabled' => 'boolean',
        ];
    }

    /**
     * The single settings row, created on first use so callers never deal with
     * a null.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function weeklyReportsAreReady(): bool
    {
        return $this->weekly_reports_enabled && filled($this->whatsapp_group_id);
    }
}
