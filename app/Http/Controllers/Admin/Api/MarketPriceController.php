<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\MarketLevel;
use App\Exceptions\UnreadableSalesFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportMarketSalesRequest;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Services\Market\OpenDataCatalogue;
use App\Services\Market\PlaceName;
use App\Services\Market\SalesImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The market prices screen reads figures that were worked out when each
 * quarter's file was uploaded. Nothing here opens a file or reaches the open
 * data portal, so the screen costs a handful of indexed queries.
 */
class MarketPriceController extends Controller
{
    /**
     * The city the screen opens on.
     */
    private const DEFAULT_CITY = 'جدة';

    public function index(Request $request): JsonResponse
    {
        $quarters = MarketQuarter::query()->chronological()->get();
        $imported = $quarters->filter(fn (MarketQuarter $quarter): bool => $quarter->isImported())->values();

        $payload = [
            'has_data' => $imported->isNotEmpty(),
            'publisher_url' => OpenDataCatalogue::PUBLISHER_URL,
            'quarters' => $imported->map(fn (MarketQuarter $quarter): array => $this->quarter($quarter))->all(),
            'waiting' => $quarters
                ->reject(fn (MarketQuarter $quarter): bool => $quarter->isImported())
                ->map(fn (MarketQuarter $quarter): array => $this->quarter($quarter))
                ->values()
                ->all(),
        ];

        if ($imported->isEmpty()) {
            return response()->json(['data' => $payload]);
        }

        $selected = $imported->first(fn (MarketQuarter $quarter): bool => $quarter->key() === $request->query('quarter'))
            ?? $imported->last();
        // The same quarter a year earlier: property has seasons, so a quarter
        // is compared with its own counterpart rather than with the one before.
        $yearBefore = $imported->first(fn (MarketQuarter $quarter): bool => $quarter->year === $selected->year - 1
            && $quarter->quarter === $selected->quarter);

        $classifications = $this->classifications();
        $classification = in_array($request->query('classification'), $classifications, true)
            ? (string) $request->query('classification')
            : MarketPriceStat::ALL_CLASSIFICATIONS;

        $cityKey = PlaceName::key((string) $request->query('city', self::DEFAULT_CITY));
        $districtKey = PlaceName::key((string) $request->query('district', ''));

        $comparison = array_filter([$selected->id, $yearBefore?->id]);

        $countryRows = $this->stats(MarketLevel::Country, $classification)->get();
        $cityRows = $this->stats(MarketLevel::City, $classification)->where('city_key', $cityKey)->get();

        $districtRows = $this->stats(MarketLevel::District, $classification)
            ->where('city_key', $cityKey)
            ->whereIn('market_quarter_id', $comparison)
            ->get();

        return response()->json(['data' => $payload + [
            'filters' => [
                'quarter' => $selected->key(),
                'compared_with' => $yearBefore?->key(),
                'classification' => $classification,
                'city' => $cityKey,
                'district' => $districtKey === '' ? null : $districtKey,
            ],
            'classifications' => $classifications,
            'country' => [
                'current' => $this->point($countryRows->firstWhere('market_quarter_id', $selected->id)),
                'previous' => $this->point($countryRows->firstWhere('market_quarter_id', $yearBefore?->id)),
                'series' => $this->series($imported, $countryRows),
            ],
            'regions' => $this->ranking(
                $this->stats(MarketLevel::Region, $classification)->whereIn('market_quarter_id', $comparison)->get(),
                $selected,
                'region',
            ),
            'cities' => $this->stats(MarketLevel::City, MarketPriceStat::ALL_CLASSIFICATIONS)
                ->where('market_quarter_id', $selected->id)
                ->orderByDesc('deals_count')
                ->get()
                ->map(fn (MarketPriceStat $stat): array => [
                    'key' => $stat->city_key,
                    'label' => $stat->city,
                    'region' => $stat->region,
                    'deals' => $stat->deals_count,
                ])
                ->unique('key')
                ->values()
                ->all(),
            'city' => [
                'key' => $cityKey,
                'label' => ($cityRows->firstWhere('market_quarter_id', $selected->id) ?? $cityRows->first())?->city
                    ?? PlaceName::preferred($cityKey),
                'current' => $this->point($cityRows->firstWhere('market_quarter_id', $selected->id)),
                'previous' => $this->point($cityRows->firstWhere('market_quarter_id', $yearBefore?->id)),
                'series' => $this->series($imported, $cityRows),
            ],
            'districts' => $this->ranking($districtRows, $selected, 'district'),
            'district' => $districtKey === '' ? null : $this->district($imported, $classification, $cityKey, $districtKey),
        ]]);
    }

    public function store(ImportMarketSalesRequest $request, SalesImporter $importer): JsonResponse
    {
        $file = $request->file('file');

        try {
            $quarters = $importer->import($file->getRealPath(), $file->getClientOriginalName());
        } catch (UnreadableSalesFile $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return response()->json([
            'data' => [
                'quarters' => array_map(fn (MarketQuarter $quarter): array => $this->quarter($quarter), $quarters),
            ],
        ], 201);
    }

    /**
     * @return Builder<MarketPriceStat>
     */
    private function stats(MarketLevel $level, string $classification): Builder
    {
        return MarketPriceStat::query()
            ->where('level', $level)
            ->where('classification', $classification);
    }

    /**
     * The classifications the files have used, busiest first.
     *
     * @return list<string>
     */
    private function classifications(): array
    {
        return MarketPriceStat::query()
            ->where('level', MarketLevel::Country)
            ->where('classification', '!=', MarketPriceStat::ALL_CLASSIFICATIONS)
            ->get(['classification', 'deals_count'])
            ->groupBy('classification')
            ->map(fn (Collection $rows): int => (int) $rows->sum('deals_count'))
            ->sortDesc()
            ->keys()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function quarter(MarketQuarter $quarter): array
    {
        return [
            'key' => $quarter->key(),
            'year' => $quarter->year,
            'quarter' => $quarter->quarter,
            'deals' => $quarter->deals_count,
            'skipped' => $quarter->skipped_rows,
            'imported_at' => $quarter->imported_at?->toISOString(),
            'portal_url' => $quarter->portalUrl(),
        ];
    }

    /**
     * @return array<string, int|float>|null
     */
    private function point(?MarketPriceStat $stat): ?array
    {
        if ($stat === null) {
            return null;
        }

        return [
            'deals' => $stat->deals_count,
            'properties' => $stat->properties_count,
            'value' => $stat->total_price,
            'area' => $stat->total_area,
            'median' => $stat->median_price_per_meter,
            'average' => $stat->averagePricePerMeter(),
        ];
    }

    /**
     * One entry per imported quarter, including those a place did not trade
     * in, so a chart shows the gap instead of closing it.
     *
     * @param  Collection<int, MarketQuarter>  $quarters
     * @param  Collection<int, MarketPriceStat>  $rows
     * @return list<array<string, mixed>>
     */
    private function series(Collection $quarters, Collection $rows): array
    {
        $byQuarter = $rows->keyBy('market_quarter_id');

        return $quarters
            ->map(fn (MarketQuarter $quarter): array => [
                'key' => $quarter->key(),
                'year' => $quarter->year,
                'quarter' => $quarter->quarter,
            ] + ($this->point($byQuarter->get($quarter->id)) ?? [
                'deals' => 0, 'properties' => 0, 'value' => 0, 'area' => 0.0, 'median' => null, 'average' => null,
            ]))
            ->all();
    }

    /**
     * Places as they stood in the chosen quarter, busiest first, each beside
     * its median a year earlier.
     *
     * @param  Collection<int, MarketPriceStat>  $rows
     * @return list<array<string, mixed>>
     */
    private function ranking(Collection $rows, MarketQuarter $selected, string $place): array
    {
        $before = $rows->where('market_quarter_id', '!=', $selected->id)->keyBy($place.'_key');

        return $rows
            ->where('market_quarter_id', $selected->id)
            ->sortByDesc('deals_count')
            ->map(fn (MarketPriceStat $stat): array => [
                'key' => $stat->{$place.'_key'},
                'label' => $stat->{$place},
                'previous_median' => $before->get($stat->{$place.'_key'})?->median_price_per_meter,
            ] + $this->point($stat))
            ->values()
            ->all();
    }

    /**
     * One district's history, asked for when a row of the table is opened.
     *
     * @param  Collection<int, MarketQuarter>  $quarters
     * @return array<string, mixed>|null
     */
    private function district(Collection $quarters, string $classification, string $cityKey, string $districtKey): ?array
    {
        $rows = $this->stats(MarketLevel::District, $classification)
            ->where('city_key', $cityKey)
            ->where('district_key', $districtKey)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'key' => $districtKey,
            'label' => $rows->first()->district,
            'series' => $this->series($quarters, $rows),
        ];
    }
}
