<?php

namespace Database\Factories;

use App\Models\MarketQuarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketQuarter>
 */
class MarketQuarterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => fake()->unique()->numberBetween(2000, 2999),
            'quarter' => fake()->numberBetween(1, 4),
            'dataset_id' => fake()->uuid(),
            'source_name' => 'sales.csv',
            'deals_count' => fake()->numberBetween(100, 90000),
            'skipped_rows' => 0,
            'imported_at' => now(),
        ];
    }

    /**
     * Listed on the portal, its file not yet read.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'source_name' => null,
            'deals_count' => 0,
            'imported_at' => null,
        ]);
    }

    public function period(int $year, int $quarter): static
    {
        return $this->state(fn (): array => ['year' => $year, 'quarter' => $quarter]);
    }
}
