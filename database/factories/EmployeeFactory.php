<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '05'.fake()->numerify('########'),
            'role' => fake()->randomElement(['مسوّق', 'مبيعات', 'تصميم']),
            'is_active' => true,
            'enrolled_on' => now()->subMonth()->toDateString(),
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function enrolledOn(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['enrolled_on' => $date]);
    }
}
