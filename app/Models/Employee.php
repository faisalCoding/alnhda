<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * An employee is a record the admin maintains, not a login. It becomes
 * authenticatable only so a personal access token can be issued against one
 * employee, which is how the owner's own site reads and edits their tasks.
 */
class Employee extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'role',
        'is_active',
        'enrolled_on',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enrolled_on' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<WeeklyTaskList, $this>
     */
    public function weeklyTaskLists(): HasMany
    {
        return $this->hasMany(WeeklyTaskList::class)->orderByDesc('week_start');
    }

    /**
     * @return HasMany<WeeklyTaskTemplate, $this>
     */
    public function weeklyTaskTemplates(): HasMany
    {
        return $this->hasMany(WeeklyTaskTemplate::class);
    }

    /**
     * The templates that apply to this employee: the shared ones plus its own.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, WeeklyTaskTemplate>
     */
    public function applicableTemplates(): \Illuminate\Database\Eloquent\Collection
    {
        return WeeklyTaskTemplate::query()
            ->where(fn ($query) => $query->whereNull('employee_id')->orWhere('employee_id', $this->id))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
