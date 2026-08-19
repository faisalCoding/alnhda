<?php

namespace Database\Factories;

use App\Models\WeeklyTaskList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WeeklyTaskItem>
 */
class WeeklyTaskItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekly_task_list_id' => WeeklyTaskList::factory(),
            'title' => fake()->sentence(4),
            'is_done' => false,
            'sort_order' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_done' => true,
            'completed_at' => now(),
        ]);
    }
}
