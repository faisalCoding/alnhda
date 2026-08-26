<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSettings extends Model
{
    protected $fillable = [
        'whatsapp_group_id',
        'whatsapp_group_name',
        'weekly_reports_enabled',
        'seo_default_title',
        'seo_default_description',
        'seo_default_image_path',
        'favicon_path',
        'apple_touch_icon_path',
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

    /**
     * The tab icon, or the bundled one until somebody uploads their own.
     */
    public function faviconUrl(): string
    {
        return blank($this->favicon_path)
            ? asset('img/KNicon.png')
            : asset('storage/'.ltrim($this->favicon_path, '/'));
    }

    public function appleTouchIconUrl(): string
    {
        return blank($this->apple_touch_icon_path)
            ? asset('img/KNicon.png')
            : asset('storage/'.ltrim($this->apple_touch_icon_path, '/'));
    }

    public function weeklyReportsAreReady(): bool
    {
        return $this->weekly_reports_enabled && filled($this->whatsapp_group_id);
    }
}
