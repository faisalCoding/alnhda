<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyTaskList extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklyTaskListFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'week_start',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
        ];
    }

    /**
     * The Saturday that opens the week containing the given day. The Saudi week
     * runs Saturday to Thursday, which is why this is not Carbon's own
     * startOfWeek.
     */
    public static function weekStartFor(CarbonInterface $date): CarbonInterface
    {
        $date = $date->copy()->startOfDay();

        return $date->dayOfWeek === CarbonInterface::SATURDAY
            ? $date
            : $date->previous(CarbonInterface::SATURDAY)->startOfDay();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<WeeklyTaskItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WeeklyTaskItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
