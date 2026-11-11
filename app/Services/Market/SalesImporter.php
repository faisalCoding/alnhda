<?php

namespace App\Services\Market;

use App\Enums\AreaBand;
use App\Enums\MarketLevel;
use App\Exceptions\UnreadableSalesFile;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use Illuminate\Support\Facades\DB;

/**
 * Turns a quarter of sale deeds into the figures the panel reads.
 *
 * Each deed is counted once for its district, its city, its region and the
 * country. At each of those it is counted under its own classification and
 * under all of them, and under its own area band and under all of them, so
 * the screen can narrow by either without adding anything up itself. Reading
 * the same quarter again replaces what it wrote, so a corrected file from the
 * ministry is simply uploaded over the old one.
 */
class SalesImporter
{
    /**
     * A quarter holding less than this share of a file's deeds is a stray date,
     * not a quarter: left in, one deed dated 2062 would become the newest
     * quarter on the screen.
     */
    private const MINIMUM_SHARE = 0.01;

    private const ROWS_PER_INSERT = 500;

    public function __construct(private readonly SalesFileReader $reader) {}

    /**
     * @return list<MarketQuarter> The quarters the file covered, oldest first.
     *
     * @throws UnreadableSalesFile
     */
    public function import(string $path, ?string $sourceName = null): array
    {
        $periods = [];
        $labels = [];
        $skipped = 0;
        $read = 0;

        foreach ($this->reader->deals($path) as $deal) {
            if ($deal === null) {
                $skipped++;

                continue;
            }

            $read++;
            $period = $deal['year'].'-'.$deal['quarter'];
            $periods[$period] ??= ['year' => $deal['year'], 'quarter' => $deal['quarter'], 'deals' => 0, 'groups' => []];
            $periods[$period]['deals']++;

            $this->count($periods[$period]['groups'], $labels, $deal);
        }

        if ($read === 0) {
            throw new UnreadableSalesFile('لم تُقرأ من الملف أي صفقة: تأكد أنه ملف CSV للصفقات العقارية وأنه ليس فارغًا.');
        }

        $quarters = [];

        foreach ($periods as $period) {
            if ($period['deals'] < $read * self::MINIMUM_SHARE) {
                $skipped += $period['deals'];

                continue;
            }

            $quarters[] = $period;
        }

        usort($quarters, fn (array $a, array $b): int => [$a['year'], $a['quarter']] <=> [$b['year'], $b['quarter']]);

        return array_map(
            fn (array $period): MarketQuarter => $this->store($period, $labels, $skipped, $sourceName),
            $quarters,
        );
    }

    /**
     * Adds one deed to every group it belongs to.
     *
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, array<string, array<string, int>>>  $labels
     * @param  array{region: string, city: string, district: string, classification: string, properties: int, price: float, area: float}  $deal
     */
    private function count(array &$groups, array &$labels, array $deal): void
    {
        $region = PlaceName::key($deal['region']);
        $city = PlaceName::key($deal['city']);
        $district = PlaceName::key($deal['district']);

        $labels['region'][$region][$deal['region']] = ($labels['region'][$region][$deal['region']] ?? 0) + 1;
        $labels['city'][$city][$deal['city']] = ($labels['city'][$city][$deal['city']] ?? 0) + 1;
        $labels['district'][$city.'|'.$district][$deal['district']] = ($labels['district'][$city.'|'.$district][$deal['district']] ?? 0) + 1;

        $places = [
            [MarketLevel::Country, '', '', ''],
            [MarketLevel::Region, $region, '', ''],
            [MarketLevel::City, $region, $city, ''],
        ];

        if ($district !== '') {
            $places[] = [MarketLevel::District, $region, $city, $district];
        }

        $classifications = array_unique([$deal['classification'], MarketPriceStat::ALL_CLASSIFICATIONS]);
        $bands = [AreaBand::forArea($deal['area'])->value, MarketPriceStat::ALL_AREAS];
        $rate = $deal['price'] / $deal['area'];

        foreach ($places as [$level, $regionKey, $cityKey, $districtKey]) {
            foreach ($classifications as $classification) {
                foreach ($bands as $band) {
                    $group = implode('|', [$level->value, $regionKey, $cityKey, $districtKey, $classification, $band]);

                    $groups[$group] ??= [
                        'level' => $level->value,
                        'region_key' => $regionKey,
                        'city_key' => $cityKey,
                        'district_key' => $districtKey,
                        'classification' => $classification,
                        'area_band' => $band,
                        'deals' => 0,
                        'properties' => 0,
                        'price' => 0.0,
                        'area' => 0.0,
                        'rates' => [],
                        'prices' => [],
                    ];

                    $groups[$group]['deals']++;
                    $groups[$group]['properties'] += $deal['properties'];
                    $groups[$group]['price'] += $deal['price'];
                    $groups[$group]['area'] += $deal['area'];
                    $groups[$group]['rates'][] = $rate;
                    $groups[$group]['prices'][] = $deal['price'];
                }
            }
        }
    }

    /**
     * @param  array{year: int, quarter: int, deals: int, groups: array<string, array<string, mixed>>}  $period
     * @param  array<string, array<string, array<string, int>>>  $labels
     */
    private function store(array $period, array $labels, int $skipped, ?string $sourceName): MarketQuarter
    {
        return DB::transaction(function () use ($period, $labels, $skipped, $sourceName): MarketQuarter {
            $quarter = MarketQuarter::query()->firstOrNew(['year' => $period['year'], 'quarter' => $period['quarter']]);

            $quarter->fill([
                'source_name' => $sourceName,
                'deals_count' => $period['deals'],
                'skipped_rows' => $skipped,
                'figures_version' => MarketQuarter::FIGURES_VERSION,
                'imported_at' => now(),
            ])->save();

            $quarter->stats()->delete();

            $now = now();
            $rows = [];

            foreach ($period['groups'] as $group) {
                sort($group['rates']);
                sort($group['prices']);

                $rows[] = [
                    'market_quarter_id' => $quarter->id,
                    'level' => $group['level'],
                    'region_key' => $group['region_key'],
                    'region' => $this->label($labels, 'region', $group['region_key']),
                    'city_key' => $group['city_key'],
                    'city' => $this->label($labels, 'city', $group['city_key']),
                    'district_key' => $group['district_key'],
                    'district' => $this->label($labels, 'district', $group['city_key'].'|'.$group['district_key']),
                    'classification' => $group['classification'],
                    'area_band' => $group['area_band'],
                    'deals_count' => $group['deals'],
                    'properties_count' => $group['properties'],
                    'total_price' => (int) round($group['price']),
                    'total_area' => round($group['area'], 2),
                    'median_price_per_meter' => round($this->quantile($group['rates'], 0.5), 2),
                    'lower_price_per_meter' => round($this->quantile($group['rates'], 0.25), 2),
                    'upper_price_per_meter' => round($this->quantile($group['rates'], 0.75), 2),
                    'median_price' => (int) round($this->quantile($group['prices'], 0.5)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
                MarketPriceStat::query()->insert($chunk);
            }

            return $quarter;
        });
    }

    /**
     * The spelling a place was written with most often in this file.
     *
     * @param  array<string, array<string, array<string, int>>>  $labels
     */
    private function label(array $labels, string $kind, string $key): string
    {
        $spellings = $labels[$kind][$key] ?? [];

        if ($key === '' || str_ends_with($key, '|') || $spellings === []) {
            return '';
        }

        arsort($spellings);

        return PlaceName::preferred((string) array_key_first($spellings));
    }

    /**
     * The value a given share of a sorted list falls below: 0.5 is the median,
     * 0.25 and 0.75 the quarters either side of it.
     *
     * The median is kept rather than the mean because deeds include gifts
     * recorded at a token price and farmland sold by the square kilometre, and
     * a mean follows those. The quarters say how wide the ordinary range is.
     *
     * @param  list<float>  $sorted
     */
    private function quantile(array $sorted, float $share): float
    {
        $position = (count($sorted) - 1) * $share;
        $below = (int) floor($position);
        $above = (int) ceil($position);

        return $sorted[$below] + ($sorted[$above] - $sorted[$below]) * ($position - $below);
    }
}
