<?php

namespace Database\Factories;

use App\Models\MarketDeed;
use App\Models\MarketQuarter;
use App\Services\Market\PlaceName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketDeed>
 */
class MarketDeedFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $area = fake()->numberBetween(100, 900);
        $rate = fake()->numberBetween(1500, 6000);

        return [
            'market_quarter_id' => MarketQuarter::factory(),
            'city' => 'جدة',
            'city_key' => PlaceName::key('جدة'),
            'district' => 'الصفا',
            'district_key' => PlaceName::key('الصفا'),
            'classification' => 'سكني',
            'deed_date' => '2026-01-15',
            'price' => $area * $rate,
            'area' => $area,
            'price_per_meter' => $rate,
            'properties_count' => 1,
        ];
    }

    public function in(string $district, string $city = 'جدة'): static
    {
        return $this->state(fn (): array => [
            'city' => $city,
            'city_key' => PlaceName::key($city),
            'district' => $district,
            'district_key' => PlaceName::key($district),
        ]);
    }

    /**
     * A sale of a given size at a given price per metre.
     */
    public function sold(float $area, float $rate, string $date = '2026-01-15'): static
    {
        return $this->state(fn (): array => [
            'area' => $area,
            'price_per_meter' => $rate,
            'price' => (int) round($area * $rate),
            'deed_date' => $date,
        ]);
    }
}
