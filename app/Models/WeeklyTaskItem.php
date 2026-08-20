<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyTaskItem extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklyTaskItemFactory> */
    use HasFactory;

    protected $fillable = [
        'weekly_task_list_id',
        'weekly_task_category_id',
        'title',
        'is_done',
        'completed_at',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WeeklyTaskList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(WeeklyTaskList::class, 'weekly_task_list_id');
    }

    /**
     * @return BelongsTo<WeeklyTaskCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WeeklyTaskCategory::class, 'weekly_task_category_id');
    }
}
