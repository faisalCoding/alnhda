<?php

namespace App\Services\Market;

use App\Models\MarketDeed;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Support\Collection;

/**
 * Sets what the company asks for its own units beside what was paid for
 * property of the same size around them.
 *
 * A project is placed in a district by hand, because its location is written
 * for a buyer and matches nothing in the ministry's files. Once placed, its
 * units' price per metre is read against the deeds in that district — and in
 * the whole city — that sold between the smallest and largest of them, give or
 * take a fifth, over the latest four quarters.
 */
class ProjectMarketComparison
{
    /**
     * How far beyond the project's own smallest and largest unit a deed may
     * be and still count as the same kind of sale.
     */
    private const AREA_MARGIN = 0.2;

    public function __construct(private readonly DeedExplorer $deeds) {}

    /**
     * @return array{city: array{key: string, label: string}, districts: list<array{key: string, label: string}>, projects: list<array<string, mixed>>}
     */
    public function forCity(string $cityKey): array
    {
        $districts = MarketDeed::query()
            ->where('city_key', $cityKey)
            ->where('district_key', '!=', '')
            ->toBase()
            ->selectRaw('district_key as "key", max(district) as label')
            ->groupBy('district_key')
            ->orderBy('label')
            ->get()
            ->map(fn (object $row): array => ['key' => $row->key, 'label' => $row->label]);

        return [
            'city' => ['key' => $cityKey, 'label' => PlaceName::preferred($cityKey)],
            'districts' => $districts->all(),
            'projects' => Project::query()
                ->ordered()
                ->with('properties')
                ->get()
                ->map(fn (Project $project): array => $this->project($project, $cityKey, $districts))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array{key: string, label: string}>  $districts
     * @return array<string, mixed>
     */
    private function project(Project $project, string $cityKey, Collection $districts): array
    {
        $units = $project->properties
            ->map(fn (Properties $unit): ?array => $this->unit($unit))
            ->filter()
            ->values();

        $placed = $project->market_city_key === $cityKey && filled($project->market_district_key);
        $rates = Quantiles::spread($units->pluck('rate')->all());

        $row = [
            'id' => $project->id,
            'name' => $project->name,
            'location' => $project->location,
            'status' => $project->status,
            // Placed in another city: shown, but not compared with this one.
            'elsewhere' => filled($project->market_city_key) && $project->market_city_key !== $cityKey,
            'district' => $placed ? $project->market_district_key : null,
            'district_label' => $placed
                ? ($districts->firstWhere('key', $project->market_district_key)['label'] ?? $project->market_district_key)
                : null,
            'units' => $units->count(),
            'unpriced_units' => $project->properties->count() - $units->count(),
            'area_min' => $units->min('area'),
            'area_max' => $units->max('area'),
            'price_min' => $units->min('price'),
            'price_max' => $units->max('price'),
            'rate' => $rates['median'],
            'market' => null,
        ];

        if ($units->isNotEmpty()) {
            $from = $row['area_min'] * (1 - self::AREA_MARGIN);
            $to = $row['area_max'] * (1 + self::AREA_MARGIN);

            $row['market'] = [
                'area_from' => round($from),
                'area_to' => round($to),
                'district' => $placed ? $this->deeds->comparables($cityKey, $project->market_district_key, $from, $to) : null,
                'city' => $this->deeds->comparables($cityKey, null, $from, $to),
            ];
        }

        return $row;
    }

    /**
     * A unit that can be priced by the metre: one with an area and a price.
     * The offer is what is actually being asked when there is one.
     *
     * @return array{area: float, price: int, rate: float}|null
     */
    private function unit(Properties $unit): ?array
    {
        $area = (float) $unit->area;
        $price = (int) ($unit->offer ?: $unit->price);

        if ($area <= 0 || $price <= 0) {
            return null;
        }

        return ['area' => $area, 'price' => $price, 'rate' => round($price / $area, 2)];
    }
}
