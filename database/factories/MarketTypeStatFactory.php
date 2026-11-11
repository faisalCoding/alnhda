<?php

namespace Database\Factories;

use App\Enums\MarketLevel;
use App\Models\MarketIndicatorFile;
use App\Models\MarketTypeStat;
use App\Services\Market\PlaceName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketTypeStat>
 */
class MarketTypeStatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $area = fake()->numberBetween(1000, 90000);
        $average = fake()->numberBetween(1500, 6000);

        return [
            'market_indicator_file_id' => MarketIndicatorFile::factory(),
            'level' => MarketLevel::City,
            'city' => 'جدة',
            'city_key' => PlaceName::key('جدة'),
            'property_type' => 'شقة',
            'classification' => 'سكني',
            'deals_count' => fake()->numberBetween(5, 4000),
            'total_price' => $area * $average,
            'total_area' => $area,
            'average_price_per_meter' => $average,
            'lowest_price_per_meter' => (int) round($average * 0.6),
            'highest_price_per_meter' => (int) round($average * 1.8),
        ];
    }

    public function type(string $type, string $classification = 'سكني'): static
    {
        return $this->state(fn (): array => ['property_type' => $type, 'classification' => $classification]);
    }

    public function city(string $city): static
    {
        return $this->state(fn (): array => ['city' => $city, 'city_key' => PlaceName::key($city)]);
    }

    public function district(string $district): static
    {
        return $this->state(fn (): array => [
            'level' => MarketLevel::District,
            'district' => $district,
            'district_key' => PlaceName::key($district),
        ]);
    }
}
