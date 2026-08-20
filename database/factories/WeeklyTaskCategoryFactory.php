<?php

namespace Database\Factories;

use App\Models\WeeklyTaskCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyTaskCategory>
 */
class WeeklyTaskCategoryFactory extends Factory
{
    protected $model = WeeklyTaskCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => fake()->randomElement(WeeklyTaskCategory::COLORS),
            'sort_order' => 0,
        ];
    }
}
