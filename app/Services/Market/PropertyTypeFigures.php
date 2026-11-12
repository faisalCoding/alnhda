<?php

namespace App\Services\Market;

use App\Enums\MarketLevel;
use App\Models\MarketIndicatorFile;
use App\Models\MarketPriceStat;
use App\Models\MarketTypeStat;
use Illuminate\Support\Collection;

/**
 * What each kind of property sold for in one city, read from the authority's
 * indicators that were uploaded.
 *
 * These sit beside the ministry's deeds rather than inside them: another
 * publisher, averages instead of medians, and quarters that do not line up
 * with the deeds'. So the quarter shown is the nearest one the authority has
 * published, and the screen is told which that is.
 */
class PropertyTypeFigures
{
    /**
     * @param  array{0: int, 1: int}|null  $period  The year and quarter the screen is on, when it is on one.
     * @return array<string, mixed>
     */
    public function forCity(string $cityKey, ?array $period, string $classification, ?string $type): array
    {
        $files = MarketIndicatorFile::query()->chronological()->get();

        $rows = MarketTypeStat::query()
            ->where('level', MarketLevel::City)
            ->where('city_key', $cityKey)
            ->when($classification !== MarketPriceStat::ALL_CLASSIFICATIONS, fn ($query) => $query->where('classification', $classification))
            ->get();

        $covered = $files->whereIn('id', $rows->pluck('market_indicator_file_id')->unique())->values();
        $shown = $this->nearest($covered, $period);
        $yearBefore = $shown === null ? null : $covered->first(
            fn (MarketIndicatorFile $file): bool => $file->year === $shown->year - 1 && $file->quarter === $shown->quarter
        );

        $before = $this->byType($rows->where('market_indicator_file_id', $yearBefore?->id));
        $types = $this->byType($rows->where('market_indicator_file_id', $shown?->id))
            ->map(fn (array $figures, string $name): array => ['type' => $name] + $figures + [
                'previous_average' => $before->get($name)['average'] ?? null,
            ])
            ->sortByDesc('deals')
            ->values();

        $type = $types->firstWhere('type', $type)['type'] ?? null;

        return [
            'publisher_url' => OpenDataCatalogue::AUTHORITY_URL,
            'has_data' => $files->contains(fn (MarketIndicatorFile $file): bool => $file->isImported()),
            'waiting' => $files->reject(fn (MarketIndicatorFile $file): bool => $file->isImported())
                ->map(fn (MarketIndicatorFile $file): array => $this->file($file))
                ->values()
                ->all(),
            'changed' => $files->filter(fn (MarketIndicatorFile $file): bool => $file->fileChangedSinceImport())
                ->map(fn (MarketIndicatorFile $file): array => $this->file($file))
                ->values()
                ->all(),
            'quarter' => $shown?->key(),
            'compared_with' => $yearBefore?->key(),
            'rows' => $types->all(),
            'selected' => $type,
            'series' => $type === null ? [] : $this->series($covered, $rows->where('property_type', $type)),
            'districts' => $type === null || $shown === null ? [] : $this->districts($shown, $cityKey, $classification, $type),
            'by_district' => $shown !== null && MarketTypeStat::query()
                ->where('level', MarketLevel::District)
                ->where('city_key', $cityKey)
                ->where('market_indicator_file_id', $shown->id)
                ->exists(),
        ];
    }

    /**
     * The kinds of property the authority prices in a city, busiest first.
     * «أخرى» is left out: it is not a kind a unit can be.
     *
     * @return list<string>
     */
    public function kinds(string $cityKey): array
    {
        return MarketTypeStat::query()
            ->where('level', MarketLevel::City)
            ->where('city_key', $cityKey)
            ->where('property_type', '!=', 'أخرى')
            ->get(['property_type', 'deals_count'])
            ->groupBy('property_type')
            ->map(fn (Collection $rows): int => (int) $rows->sum('deals_count'))
            ->sortDesc()
            ->keys()
            ->all();
    }

    /**
     * What one kind of property last sold for in a city, or in one district
     * of it, with the quarter that figure is from. The authority stopped
     * publishing by district in late 2025, so a district's figure can be
     * older than its city's; the quarter is returned so the screen can say.
     *
     * @return array{deals: int, value: int, area: float, average: float|null, lowest: float|null, highest: float|null, quarter: string}|null
     */
    public function reference(string $cityKey, string $type, ?string $districtKey = null): ?array
    {
        $rows = MarketTypeStat::query()
            ->with('file')
            ->where('level', $districtKey === null ? MarketLevel::City : MarketLevel::District)
            ->where('city_key', $cityKey)
            ->where('property_type', $type)
            ->get()
            // The district is named by the ministry's deeds and looked for in
            // the authority's files, which do not always spell it the same.
            ->when($districtKey !== null, fn (Collection $rows): Collection => $rows->filter(
                fn (MarketTypeStat $row): bool => PlaceName::loose($row->district_key) === PlaceName::loose($districtKey)
            ));

        $latest = $rows
            ->sortBy(fn (MarketTypeStat $row): array => [$row->file->year, $row->file->quarter])
            ->last()
            ?->file;

        if ($latest === null) {
            return null;
        }

        return $this->merge($rows->where('market_indicator_file_id', $latest->id)) + ['quarter' => $latest->key()];
    }

    /**
     * The latest quarter the authority covers that is no later than the one
     * the screen is on; failing that, the earliest it covers at all.
     *
     * @param  Collection<int, MarketIndicatorFile>  $covered
     * @param  array{0: int, 1: int}|null  $period
     */
    private function nearest(Collection $covered, ?array $period): ?MarketIndicatorFile
    {
        if ($period === null) {
            return $covered->last();
        }

        return $covered->last(fn (MarketIndicatorFile $file): bool => [$file->year, $file->quarter] <= $period)
            ?? $covered->first();
    }

    /**
     * Rows for one quarter merged by kind of property, across classifications.
     *
     * @param  Collection<int, MarketTypeStat>  $rows
     * @return Collection<string, array<string, int|float|null>>
     */
    private function byType(Collection $rows): Collection
    {
        return $rows->groupBy('property_type')->map(fn (Collection $group): array => $this->merge($group));
    }

    /**
     * Several rows as one: the counts added up, the price per metre averaged
     * by the deeds behind each, the extremes taken across all of them.
     *
     * @param  Collection<int, MarketTypeStat>  $rows
     * @return array<string, int|float|null>
     */
    private function merge(Collection $rows): array
    {
        $deals = (int) $rows->sum('deals_count');

        return [
            'deals' => $deals,
            'value' => (int) $rows->sum('total_price'),
            'area' => (float) $rows->sum('total_area'),
            'average' => $deals === 0 ? null : round(
                $rows->sum(fn (MarketTypeStat $row): float => $row->average_price_per_meter * $row->deals_count) / $deals,
                2,
            ),
            'lowest' => $rows->pluck('lowest_price_per_meter')->filter()->min(),
            'highest' => $rows->pluck('highest_price_per_meter')->filter()->max(),
        ];
    }

    /**
     * One kind of property quarter by quarter, across every quarter the
     * authority covers for the city, with a gap where it sold none.
     *
     * @param  Collection<int, MarketIndicatorFile>  $covered
     * @param  Collection<int, MarketTypeStat>  $rows
     * @return list<array<string, mixed>>
     */
    private function series(Collection $covered, Collection $rows): array
    {
        $byFile = $rows->groupBy('market_indicator_file_id');

        return $covered
            ->map(fn (MarketIndicatorFile $file): array => [
                'key' => $file->key(),
                'year' => $file->year,
                'quarter' => $file->quarter,
            ] + $this->merge($byFile->get($file->id, collect())))
            ->all();
    }

    /**
     * Where in the city one kind of property sold, for the quarters the
     * authority published by district.
     *
     * @return list<array<string, mixed>>
     */
    private function districts(MarketIndicatorFile $file, string $cityKey, string $classification, string $type): array
    {
        return MarketTypeStat::query()
            ->where('level', MarketLevel::District)
            ->where('city_key', $cityKey)
            ->where('market_indicator_file_id', $file->id)
            ->where('property_type', $type)
            ->when($classification !== MarketPriceStat::ALL_CLASSIFICATIONS, fn ($query) => $query->where('classification', $classification))
            ->get()
            ->groupBy('district_key')
            ->map(fn (Collection $rows, string $key): array => ['key' => $key, 'label' => $rows->first()->district] + $this->merge($rows))
            ->sortByDesc('deals')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function file(MarketIndicatorFile $file): array
    {
        return [
            'key' => $file->key(),
            'year' => $file->year,
            'quarter' => $file->quarter,
            'region' => $file->region,
            'file_updated_at' => $file->file_updated_at?->toISOString(),
            'download_url' => $file->download_url,
            'portal_url' => $file->portalUrl(),
        ];
    }
}
