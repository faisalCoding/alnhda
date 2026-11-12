<?php

namespace App\Services\Market;

use App\Enums\MarketLevel;
use App\Models\MarketPriceStat;
use App\Models\MarketTypeStat;
use Illuminate\Support\Collection;

/**
 * The cities, districts and kinds of property the market figures are kept by,
 * as choices for the company's own records.
 *
 * A project's location and a unit's type used to be typed freely, and so
 * matched nothing in the market's files. Offered from here instead, a project
 * sits in a district the deeds know and a unit is a kind the authority prices,
 * and both can be set beside the market without anyone matching names by hand.
 */
class MarketPlaces
{
    /**
     * The kinds offered before the authority's indicators have been uploaded.
     *
     * @var list<string>
     */
    public const USUAL_KINDS = ['شقة', 'فيلا', 'دور', 'دوبلكس', 'أرض', 'عمارة'];

    /**
     * Every city the deeds have sold in, busiest first.
     *
     * @return list<array{key: string, label: string, region: string}>
     */
    public function cities(): array
    {
        return $this->places(MarketLevel::City, 'city')
            ->sortByDesc('deals')
            ->map(fn (array $city): array => ['key' => $city['key'], 'label' => $city['label'], 'region' => $city['region']])
            ->values()
            ->all();
    }

    /**
     * The districts of one city, by name.
     *
     * @return list<array{key: string, label: string}>
     */
    public function districts(string $cityKey): array
    {
        return $this->places(MarketLevel::District, 'district', $cityKey)
            ->sortBy('label')
            ->map(fn (array $district): array => ['key' => $district['key'], 'label' => $district['label']])
            ->values()
            ->all();
    }

    /**
     * The kinds of property the authority prices, busiest first. «أخرى» is
     * left out: it is not a kind a unit can be.
     *
     * @return list<string>
     */
    public function kinds(): array
    {
        $kinds = MarketTypeStat::query()
            ->where('level', MarketLevel::City)
            ->where('property_type', '!=', 'أخرى')
            ->get(['property_type', 'deals_count'])
            ->groupBy('property_type')
            ->map(fn (Collection $rows): int => (int) $rows->sum('deals_count'))
            ->sortDesc()
            ->keys()
            ->all();

        return $kinds === [] ? self::USUAL_KINDS : $kinds;
    }

    public function cityLabel(string $cityKey): ?string
    {
        return collect($this->cities())->firstWhere('key', $cityKey)['label'] ?? null;
    }

    public function districtLabel(string $cityKey, string $districtKey): ?string
    {
        return collect($this->districts($cityKey))->firstWhere('key', $districtKey)['label'] ?? null;
    }

    /**
     * A location as a buyer reads it, written from the market's own names:
     * «جدة حي الصفا».
     */
    public function location(string $cityKey, ?string $districtKey): ?string
    {
        $city = $this->cityLabel($cityKey);
        $district = filled($districtKey) ? $this->districtLabel($cityKey, $districtKey) : null;

        if ($city === null) {
            return null;
        }

        if ($district === null) {
            return $city;
        }

        return $city.' '.(str_starts_with($district, 'حي ') ? $district : 'حي '.$district);
    }

    /**
     * The places at one level as the deeds name them, each under the spelling
     * of its most recent quarter, with the deeds it has seen in all.
     *
     * @return Collection<string, array{key: string, label: string, region: string, deals: int}>
     */
    private function places(MarketLevel $level, string $place, ?string $cityKey = null): Collection
    {
        return MarketPriceStat::query()
            ->where('level', $level)
            ->where('classification', MarketPriceStat::ALL_CLASSIFICATIONS)
            ->where('area_band', MarketPriceStat::ALL_AREAS)
            ->where($place.'_key', '!=', '')
            ->when($cityKey !== null, fn ($query) => $query->where('city_key', $cityKey))
            ->orderBy('market_quarter_id')
            ->get([$place.'_key', $place, 'region', 'deals_count'])
            ->groupBy($place.'_key')
            ->map(fn (Collection $rows, string $key): array => [
                'key' => $key,
                'label' => $rows->last()->{$place},
                'region' => $rows->last()->region,
                'deals' => (int) $rows->sum('deals_count'),
            ]);
    }
}
