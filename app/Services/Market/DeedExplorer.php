<?php

namespace App\Services\Market;

use App\Models\MarketDeed;
use App\Models\MarketQuarter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reads a city's deeds one by one: the sales that match a district, a range of
 * area and a range of price, with what they add up to, how they moved month
 * by month and how they spread across price.
 *
 * Deeds are kept only for the cities named in SalesImporter::DETAILED_CITIES,
 * so for any other city this has nothing to say and says so.
 */
class DeedExplorer
{
    public const PER_PAGE = 50;

    /**
     * How many of the latest quarters a reading may cover; zero is all of them.
     *
     * @var list<int>
     */
    public const SPANS = [4, 8, 12, 0];

    /**
     * Where one price bracket ends and the next begins, in riyals.
     *
     * @var list<int>
     */
    private const PRICE_STEPS = [250_000, 500_000, 750_000, 1_000_000, 1_500_000, 2_500_000, 5_000_000];

    /**
     * @var array<string, string>
     */
    private const SORTS = ['date' => 'deed_date', 'price' => 'price', 'area' => 'area', 'rate' => 'price_per_meter'];

    /**
     * @param  array{city: string, district?: string|null, classification?: string|null, area_min?: float|null, area_max?: float|null, price_min?: float|null, price_max?: float|null, quarters?: int|null, sort?: string|null, direction?: string|null, page?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function explore(array $filters): array
    {
        $cityKey = PlaceName::key($filters['city']);
        $covered = $this->quarters($cityKey);
        $span = in_array($filters['quarters'] ?? null, self::SPANS, true) ? (int) $filters['quarters'] : self::SPANS[0];
        $period = $span === 0 ? $covered : $covered->slice(-$span)->values();

        $inPeriod = fn (): Builder => MarketDeed::query()
            ->where('city_key', $cityKey)
            ->whereIn('market_quarter_id', $period->pluck('id'))
            ->when(filled($filters['classification'] ?? null), fn (Builder $query) => $query->where('classification', $filters['classification']));

        $matching = fn (): Builder => $inPeriod()
            ->when(filled($filters['district'] ?? null), fn (Builder $query) => $query->where('district_key', PlaceName::key($filters['district'])))
            ->when(isset($filters['area_min']), fn (Builder $query) => $query->where('area', '>=', $filters['area_min']))
            ->when(isset($filters['area_max']), fn (Builder $query) => $query->where('area', '<=', $filters['area_max']))
            ->when(isset($filters['price_min']), fn (Builder $query) => $query->where('price', '>=', $filters['price_min']))
            ->when(isset($filters['price_max']), fn (Builder $query) => $query->where('price', '<=', $filters['price_max']));

        $sort = self::SORTS[$filters['sort'] ?? 'date'] ?? 'deed_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $figures = $this->figures($matching());

        return [
            'available' => $covered->isNotEmpty(),
            'period' => [
                'quarters' => $span,
                'from' => $period->first()?->key(),
                'to' => $period->last()?->key(),
                'covered' => $covered->count(),
            ],
            'districts' => $inPeriod()
                ->where('district_key', '!=', '')
                ->toBase()
                ->selectRaw('district_key as "key", max(district) as label, count(*) as deals')
                ->groupBy('district_key')
                ->orderByDesc('deals')
                ->get()
                ->map(fn (object $row): array => ['key' => $row->key, 'label' => $row->label, 'deals' => (int) $row->deals])
                ->all(),
            'summary' => $figures['summary'],
            'monthly' => $figures['monthly'],
            'distribution' => $figures['distribution'],
            'page' => $page,
            'pages' => max(1, (int) ceil($figures['summary']['deals'] / self::PER_PAGE)),
            'deeds' => $matching()
                ->orderBy($sort, $direction)
                ->orderBy('id')
                ->forPage($page, self::PER_PAGE)
                ->get()
                ->map(fn (MarketDeed $deed): array => [
                    'id' => $deed->id,
                    'date' => $deed->deed_date->toDateString(),
                    'district' => $deed->district,
                    'classification' => $deed->classification,
                    'area' => $deed->area,
                    'price' => $deed->price,
                    'rate' => $deed->price_per_meter,
                    'properties' => $deed->properties_count,
                ])
                ->all(),
        ];
    }

    /**
     * The middle price per metre, and the range around it, of the deeds in a
     * district — or the whole city — that sold between two sizes over the
     * latest quarters. What a project's own prices are set beside.
     *
     * @return array{deals: int, lower: float|null, median: float|null, upper: float|null, from: string|null, to: string|null}
     */
    public function comparables(string $cityKey, ?string $districtKey, float $areaFrom, float $areaTo, int $quarters = 4): array
    {
        $period = $this->quarters($cityKey)->slice(-$quarters)->values();

        $rates = MarketDeed::query()
            ->where('city_key', $cityKey)
            ->whereIn('market_quarter_id', $period->pluck('id'))
            ->when($districtKey !== null, fn (Builder $query) => $query->where('district_key', $districtKey))
            ->whereBetween('area', [$areaFrom, $areaTo])
            ->pluck('price_per_meter')
            ->map(fn (mixed $rate): float => (float) $rate)
            ->all();

        return ['deals' => count($rates)] + Quantiles::spread($rates) + [
            'from' => $period->first()?->key(),
            'to' => $period->last()?->key(),
        ];
    }

    /**
     * The quarters that hold deeds for a city, oldest first.
     *
     * @return Collection<int, MarketQuarter>
     */
    private function quarters(string $cityKey): Collection
    {
        return MarketQuarter::query()
            ->whereIn('id', MarketDeed::query()->where('city_key', $cityKey)->select('market_quarter_id')->distinct())
            ->chronological()
            ->get();
    }

    /**
     * Everything the matching deeds add up to, gathered in one pass so a
     * reading of a hundred thousand deeds holds four numbers for each and no
     * models at all.
     *
     * @param  Builder<MarketDeed>  $query
     * @return array{summary: array<string, int|float|null>, monthly: list<array<string, mixed>>, distribution: list<array<string, mixed>>}
     */
    private function figures(Builder $query): array
    {
        $rates = [];
        $prices = [];
        $areas = [];
        $months = [];
        $brackets = array_fill(0, count(self::PRICE_STEPS) + 1, 0);

        foreach ($query->toBase()->select(['deed_date', 'price', 'area', 'price_per_meter'])->cursor() as $deed) {
            $rate = (float) $deed->price_per_meter;
            $price = (int) $deed->price;
            $month = substr((string) $deed->deed_date, 0, 7);

            $rates[] = $rate;
            $prices[] = $price;
            $areas[] = (float) $deed->area;
            $months[$month]['rates'][] = $rate;
            $months[$month]['value'] = ($months[$month]['value'] ?? 0) + $price;

            $bracket = 0;

            while ($bracket < count(self::PRICE_STEPS) && $price >= self::PRICE_STEPS[$bracket]) {
                $bracket++;
            }

            $brackets[$bracket]++;
        }

        ksort($months);
        $spread = Quantiles::spread($rates);

        return [
            'summary' => [
                'deals' => count($rates),
                'value' => array_sum($prices),
                'median' => $spread['median'],
                'lower' => $spread['lower'],
                'upper' => $spread['upper'],
                'median_price' => Quantiles::spread($prices)['median'],
                'median_area' => Quantiles::spread($areas)['median'],
            ],
            'monthly' => array_map(
                fn (string $month, array $figures): array => [
                    'key' => $month,
                    'deals' => count($figures['rates']),
                    'value' => $figures['value'],
                    'median' => Quantiles::spread($figures['rates'])['median'],
                ],
                array_keys($months),
                $months,
            ),
            'distribution' => array_map(
                fn (int $bracket, int $deals): array => [
                    'from' => $bracket === 0 ? 0 : self::PRICE_STEPS[$bracket - 1],
                    'to' => self::PRICE_STEPS[$bracket] ?? null,
                    'deals' => $deals,
                ],
                array_keys($brackets),
                $brackets,
            ),
        ];
    }
}
