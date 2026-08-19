<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdvertisingLicence>
 */
class AdvertisingLicenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'properties_id' => null,
            'unit_name' => 'وحدة '.fake()->numberBetween(1, 40),
            'licence_number' => (string) fake()->numerify('##########'),
            'expires_on' => now()->addMonths(6)->toDateString(),
            'note' => null,
            'sort_order' => 0,
        ];
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_on' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function withoutExpiry(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_on' => null]);
    }
}
