<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Backlink>
 */
class BacklinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'url' => fake()->url(),
            'target_url' => 'https://kayanalnhda.sa/'.fake()->slug(),
            'visits' => fake()->numberBetween(0, 500),
            'sort_order' => 0,
        ];
    }
}
