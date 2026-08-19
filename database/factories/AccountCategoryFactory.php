<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountCategory>
 */
class AccountCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['تواصل اجتماعي', 'أدوات تصميم', 'تحليلات', 'بريد']),
            'color' => fake()->randomElement(\App\Models\AccountCategory::COLORS),
            'sort_order' => 0,
        ];
    }
}
