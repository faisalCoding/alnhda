<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyTaskTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklyTaskTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'weekly_task_category_id',
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

    /**
     * Null means the task is handed to every employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Null means the task is filed under no heading of its own.
     *
     * @return BelongsTo<WeeklyTaskCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WeeklyTaskCategory::class, 'weekly_task_category_id');
    }
}
