<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\WeeklyTaskList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WeeklyTaskList>
 */
class WeeklyTaskListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'week_start' => WeeklyTaskList::weekStartFor(now())->toDateString(),
        ];
    }
}
