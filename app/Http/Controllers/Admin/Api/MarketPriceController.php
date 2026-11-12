<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\AreaBand;
use App\Enums\MarketLevel;
use App\Exceptions\UnreadableSalesFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportMarketSalesRequest;
use App\Models\MarketDeed;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Services\Market\CatalogueSync;
use App\Services\Market\MarketFileImporter;
use App\Services\Market\OpenDataCatalogue;
use App\Services\Market\PlaceName;
use App\Services\Market\PropertyTypeFigures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    public function index(Request $request, PropertyTypeFigures $types, CatalogueSync $sync): JsonResponse
    {
        $cityKey = PlaceName::key((string) $request->query('city', self::DEFAULT_CITY));
        $type = $request->query('type');

        $quarters = MarketQuarter::query()->chronological()->get();
        $imported = $quarters->filter(fn (MarketQuarter $quarter): bool => $quarter->isImported())->values();

        $payload = [
            'has_data' => $imported->isNotEmpty(),
            'publisher_url' => OpenDataCatalogue::PUBLISHER_URL,
            'catalogue_checked_at' => $sync->lastCheckedAt(),
            'portal_unreachable' => $sync->portalUnreachable(),
            // Deeds are kept one by one only for some cities; the screen shows
            // its deed-by-deed part only where there are any.
            'deeds_available' => MarketDeed::query()->where('city_key', $cityKey)->exists(),
            'quarters' => $imported->map(fn (MarketQuarter $quarter): array => $this->quarter($quarter))->all(),
            'waiting' => $quarters
                ->reject(fn (MarketQuarter $quarter): bool => $quarter->isImported())
                ->map(fn (MarketQuarter $quarter): array => $this->quarter($quarter))
                ->values()
                ->all(),
        ];

        if ($imported->isEmpty()) {
            // The authority's indicators stand on their own: they can be
            // uploaded and read before a single quarter of deeds has been.
            return response()->json(['data' => $payload + [
                'city' => ['key' => $cityKey, 'label' => PlaceName::preferred($cityKey)],
                'types' => $types->forCity($cityKey, null, MarketPriceStat::ALL_CLASSIFICATIONS, is_string($type) ? $type : null),
            ]]);
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

        $band = AreaBand::tryFrom((string) $request->query('band', ''))?->value ?? MarketPriceStat::ALL_AREAS;

        $districtKey = PlaceName::key((string) $request->query('district', ''));

        $comparison = array_filter([$selected->id, $yearBefore?->id]);

        $countryRows = $this->stats(MarketLevel::Country, $classification, $band)->get();
        $cityRows = $this->stats(MarketLevel::City, $classification, $band)->where('city_key', $cityKey)->get();

        $districtRows = $this->stats(MarketLevel::District, $classification, $band)
            ->where('city_key', $cityKey)
            ->whereIn('market_quarter_id', $comparison)
            ->get();

        return response()->json(['data' => $payload + [
            'filters' => [
                'quarter' => $selected->key(),
                'compared_with' => $yearBefore?->key(),
                'classification' => $classification,
                'band' => $band,
                'city' => $cityKey,
                'district' => $districtKey === '' ? null : $districtKey,
            ],
            'classifications' => $classifications,
            'bands' => array_map(
                fn (AreaBand $band): array => ['key' => $band->value, 'label' => $band->label()],
                AreaBand::cases(),
            ),
            'country' => [
                'current' => $this->point($countryRows->firstWhere('market_quarter_id', $selected->id)),
                'previous' => $this->point($countryRows->firstWhere('market_quarter_id', $yearBefore?->id)),
                'series' => $this->series($imported, $countryRows),
            ],
            'regions' => $this->ranking(
                $this->stats(MarketLevel::Region, $classification, $band)->whereIn('market_quarter_id', $comparison)->get(),
                $selected,
                'region',
            ),
            'cities' => $this->stats(MarketLevel::City)
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
            'types' => $types->forCity($cityKey, [$selected->year, $selected->quarter], $classification, is_string($type) ? $type : null),
            'districts' => $this->ranking($districtRows, $selected, 'district'),
            'district' => $districtKey === '' ? null : $this->district($imported, $classification, $band, $cityKey, $districtKey),
        ]]);
    }

    /**
     * Takes a quarter of the ministry's deeds or a quarter of the authority's
     * indicators; which one it is, is read from the file.
     */
    public function store(ImportMarketSalesRequest $request, MarketFileImporter $importer): JsonResponse
    {
        $file = $request->file('file');

        try {
            $result = $importer->import($file->getRealPath(), $file->getClientOriginalName());
        } catch (UnreadableSalesFile $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return response()->json(['data' => ['kind' => $result['kind'], 'quarters' => $result['periods']]], 201);
    }

    /**
     * Asks the open data portal now, instead of waiting for the weekly check.
     */
    public function check(CatalogueSync $sync): JsonResponse
    {
        try {
            $result = $sync->run();
        } catch (Throwable $e) {
            // The portal answers only from inside the Kingdom. Where the
            // server is not, the catalogue comes from a computer that is.
            return response()->json([
                'message' => 'الخادم لا يصل إلى منصة البيانات المفتوحة. حدّث القائمة من جهاز داخل المملكة بالأمر: php artisan market:check --push',
                'reason' => $e->getMessage(),
            ], 502);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * @return Builder<MarketPriceStat>
     */
    private function stats(
        MarketLevel $level,
        string $classification = MarketPriceStat::ALL_CLASSIFICATIONS,
        string $band = MarketPriceStat::ALL_AREAS,
    ): Builder {
        return MarketPriceStat::query()
            ->where('level', $level)
            ->where('classification', $classification)
            ->where('area_band', $band);
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
            ->where('area_band', MarketPriceStat::ALL_AREAS)
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
            'outdated' => $quarter->isOutdated(),
            'file_updated_at' => $quarter->file_updated_at?->toISOString(),
            'file_changed' => $quarter->fileChangedSinceImport(),
            'download_url' => $quarter->download_url,
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
            'lower' => $stat->lower_price_per_meter,
            'upper' => $stat->upper_price_per_meter,
            'average' => $stat->averagePricePerMeter(),
            'median_price' => $stat->median_price,
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
                'deals' => 0, 'properties' => 0, 'value' => 0, 'area' => 0.0,
                'median' => null, 'lower' => null, 'upper' => null, 'average' => null, 'median_price' => null,
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
    private function district(Collection $quarters, string $classification, string $band, string $cityKey, string $districtKey): ?array
    {
        $rows = $this->stats(MarketLevel::District, $classification, $band)
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
