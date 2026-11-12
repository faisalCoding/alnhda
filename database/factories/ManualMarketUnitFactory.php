<?php

namespace Database\Factories;

use App\Models\ManualMarketUnit;
use App\Services\Market\PlaceName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualMarketUnit>
 */
class ManualMarketUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $area = fake()->numberBetween(80, 400);
        $rate = fake()->numberBetween(2000, 8000);

        return [
            'name' => 'وحدة '.fake()->numerify('###'),
            'project_name' => 'مشروع السوق',
            'city' => 'جدة',
            'city_key' => PlaceName::key('جدة'),
            'district' => 'الصفا',
            'district_key' => PlaceName::key('الصفا'),
            'kind' => 'شقة',
            'area' => $area,
            'price' => (int) round($area * $rate),
            'price_per_meter' => $rate,
            'observed_on' => now()->toDateString(),
            'source_url' => null,
        ];
    }

    /**
     * Copied from a listing on a site, and said to repeat the given others.
     *
     * @param  list<string>  $repeats  Other listings as «site:number».
     */
    public function listed(string $site, string $id, array $repeats = [], ?string $group = null): static
    {
        return $this->state(fn (): array => [
            'source_site' => $site,
            'listing_id' => $id,
            'duplicates' => $repeats === [] ? null : implode(';', $repeats),
            'duplicate_group' => $group,
        ]);
    }

    /**
     * A unit of a given size at a given price per metre, in a district.
     */
    public function offered(string $district, float $area, float $rate, string $kind = 'شقة', string $city = 'جدة'): static
    {
        return $this->state(fn (): array => [
            'city' => $city,
            'city_key' => PlaceName::key($city),
            'district' => $district,
            'district_key' => PlaceName::key($district),
            'kind' => $kind,
            'area' => $area,
            'price_per_meter' => $rate,
            'price' => (int) round($area * $rate),
        ]);
    }
}
