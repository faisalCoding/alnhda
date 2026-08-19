<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['إنستغرام', 'تويتر', 'يوتيوب', 'سناب شات', 'تيك توك']),
            'identifier' => fake()->userName(),
            'password' => fake()->password(),
            'sort_order' => 0,
        ];
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes): array => ['password' => null]);
    }
}
