<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyTaskCategory extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklyTaskCategoryFactory> */
    use HasFactory;

    /**
     * The same fixed palette the account categories draw on, for the same
     * reason: every entry stays legible on both the light and the dark panel.
     *
     * @var list<string>
     */
    public const COLORS = AccountCategory::COLORS;

    protected $fillable = [
        'name',
        'color',
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

    /**
     * @return HasMany<WeeklyTaskTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(WeeklyTaskTemplate::class);
    }

    /**
     * @return HasMany<WeeklyTaskItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WeeklyTaskItem::class);
    }
}
