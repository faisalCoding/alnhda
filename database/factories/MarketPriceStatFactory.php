<?php

namespace Database\Factories;

use App\Enums\MarketLevel;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Services\Market\PlaceName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketPriceStat>
 */
class MarketPriceStatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $area = fake()->numberBetween(5000, 500000);
        $median = fake()->numberBetween(500, 6000);

        return [
            'market_quarter_id' => MarketQuarter::factory(),
            'level' => MarketLevel::Country,
            'classification' => MarketPriceStat::ALL_CLASSIFICATIONS,
            'deals_count' => fake()->numberBetween(10, 5000),
            'properties_count' => fake()->numberBetween(10, 5000),
            'total_price' => $area * $median,
            'total_area' => $area,
            'median_price_per_meter' => $median,
        ];
    }

    public function region(string $region): static
    {
        return $this->state(fn (): array => [
            'level' => MarketLevel::Region,
            'region' => $region,
            'region_key' => PlaceName::key($region),
        ]);
    }

    public function city(string $city, string $region = 'مكة المكرمة'): static
    {
        return $this->state(fn (): array => [
            'level' => MarketLevel::City,
            'region' => $region,
            'region_key' => PlaceName::key($region),
            'city' => $city,
            'city_key' => PlaceName::key($city),
        ]);
    }

    public function district(string $district, string $city = 'جدة', string $region = 'مكة المكرمة'): static
    {
        return $this->city($city, $region)->state(fn (): array => [
            'level' => MarketLevel::District,
            'district' => $district,
            'district_key' => PlaceName::key($district),
        ]);
    }

    public function classified(string $classification): static
    {
        return $this->state(fn (): array => ['classification' => $classification]);
    }
}
