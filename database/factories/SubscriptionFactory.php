<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Canva Pro', 'Adobe Creative Cloud', 'Semrush', 'ChatGPT Plus']),
            'identifier' => fake()->safeEmail(),
            'url' => fake()->url(),
            'expires_on' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'payment_account' => 'Visa ****'.fake()->numberBetween(1000, 9999),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'paid_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'note' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }

    public function payment(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_on' => null]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_on' => now()->addDays($days)->toDateString(),
        ]);
    }
}
