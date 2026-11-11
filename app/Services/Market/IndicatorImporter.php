<?php

namespace App\Services\Market;

use App\Enums\MarketLevel;
use App\Exceptions\UnreadableSalesFile;
use App\Models\MarketIndicatorFile;
use App\Models\MarketTypeStat;
use Illuminate\Support\Facades\DB;

/**
 * Stores what the authority's sale indicators say each kind of property sold
 * for, by city always and by district where the file goes that far.
 *
 * Uploading a file again replaces what the earlier copy wrote.
 */
class IndicatorImporter
{
    private const ROWS_PER_INSERT = 500;

    public function __construct(private readonly IndicatorFileReader $reader) {}

    /**
     * @return list<MarketIndicatorFile> One per region and quarter the file covered, oldest first.
     *
     * @throws UnreadableSalesFile
     */
    public function import(string $path, ?string $sourceName = null): array
    {
        $files = [];

        foreach ($this->reader->rows($path) as $row) {
            if ($row === null) {
                continue;
            }

            $region = PlaceName::key($row['region']);
            $file = implode('|', [$row['year'], $row['quarter'], $region]);

            $files[$file] ??= [
                'year' => $row['year'],
                'quarter' => $row['quarter'],
                'region_key' => $region,
                'region' => PlaceName::preferred($row['region']),
                'cities' => [],
                'districts' => [],
            ];

            $this->add($files[$file]['cities'], $row, null);

            if ($row['district'] !== null && $row['district'] !== '') {
                $this->add($files[$file]['districts'], $row, $row['district']);
            }
        }

        if ($files === []) {
            throw new UnreadableSalesFile('لم يُقرأ من الملف أي صف: تأكد أنه ملف CSV لمؤشرات صفقات البيع وأنه ليس فارغًا.');
        }

        usort($files, fn (array $a, array $b): int => [$a['year'], $a['quarter']] <=> [$b['year'], $b['quarter']]);

        return array_map(fn (array $file): MarketIndicatorFile => $this->store($file, $sourceName), $files);
    }

    /**
     * A published row whose price per metre is this many times the middle one
     * for its city and kind of property is an entry error, not a sale: the
     * files hold villas at a quarter of a million riyals a metre.
     */
    private const IMPLAUSIBLE_MULTIPLE = 10;

    /**
     * Adds one published row to the place it belongs to.
     *
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array{city: string, type: string, classification: string, deals: int, value: float, area: float, average: float|null, highest: float|null, lowest: float|null}  $row
     */
    private function add(array &$groups, array $row, ?string $district): void
    {
        $key = implode('|', [PlaceName::key($row['city']), PlaceName::key((string) $district), $row['type'], $row['classification']]);

        $groups[$key] ??= [
            'city' => $row['city'],
            'district' => (string) $district,
            'type' => $row['type'],
            'classification' => $row['classification'],
            'deals' => 0,
            'value' => 0.0,
            'area' => 0.0,
            'rows' => [],
        ];

        $groups[$key]['deals'] += $row['deals'];
        $groups[$key]['value'] += $row['value'];
        $groups[$key]['area'] += $row['area'];
        $groups[$key]['rows'][] = [
            'rate' => $row['average'] ?? $row['value'] / $row['area'],
            'deals' => $row['deals'],
            'lowest' => $row['lowest'],
            'highest' => $row['highest'],
        ];
    }

    /**
     * The price per metre of a place made of several published rows, with the
     * lowest and highest any of them reported.
     *
     * A file by district holds many rows for one city and kind of property.
     * Their prices are averaged by the number of deeds behind each, which is
     * as close to the authority's own city average as its district figures
     * allow: dividing all that was paid by all that was sold would let one
     * vast plot set the price of land. Rows far above the middle one are left
     * out of the average, though their deeds are still counted.
     *
     * @param  list<array{rate: float, deals: int, lowest: float|null, highest: float|null}>  $rows
     * @return array{average: float, lowest: float|null, highest: float|null}
     */
    private function prices(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => $a['rate'] <=> $b['rate']);

        $half = array_sum(array_column($rows, 'deals')) / 2;
        $middle = $rows[0]['rate'];
        $seen = 0;

        foreach ($rows as $row) {
            $seen += $row['deals'];
            $middle = $row['rate'];

            if ($seen >= $half) {
                break;
            }
        }

        $kept = array_filter($rows, fn (array $row): bool => $row['rate'] <= $middle * self::IMPLAUSIBLE_MULTIPLE);
        $lowest = array_filter(array_column($kept, 'lowest'), fn (?float $price): bool => $price !== null);
        $highest = array_filter(array_column($kept, 'highest'), fn (?float $price): bool => $price !== null);

        return [
            'average' => array_sum(array_map(fn (array $row): float => $row['rate'] * $row['deals'], $kept))
                / array_sum(array_column($kept, 'deals')),
            'lowest' => $lowest === [] ? null : min($lowest),
            'highest' => $highest === [] ? null : max($highest),
        ];
    }

    /**
     * @param  array{year: int, quarter: int, region_key: string, region: string, cities: array<string, array<string, mixed>>, districts: array<string, array<string, mixed>>}  $file
     */
    private function store(array $file, ?string $sourceName): MarketIndicatorFile
    {
        return DB::transaction(function () use ($file, $sourceName): MarketIndicatorFile {
            $record = MarketIndicatorFile::query()->firstOrNew([
                'year' => $file['year'],
                'quarter' => $file['quarter'],
                'region_key' => $file['region_key'],
            ]);

            $record->fill([
                'region' => $file['region'],
                'source_name' => $sourceName,
                'deals_count' => array_sum(array_column($file['cities'], 'deals')),
                'imported_at' => now(),
            ])->save();

            $record->stats()->delete();

            $now = now();
            $rows = [];

            foreach ([MarketLevel::City->value => $file['cities'], MarketLevel::District->value => $file['districts']] as $level => $groups) {
                foreach ($groups as $group) {
                    $prices = $this->prices($group['rows']);

                    $rows[] = [
                        'market_indicator_file_id' => $record->id,
                        'level' => $level,
                        'city_key' => PlaceName::key($group['city']),
                        'city' => PlaceName::preferred($group['city']),
                        'district_key' => PlaceName::key($group['district']),
                        'district' => $group['district'],
                        'property_type' => $group['type'],
                        'classification' => $group['classification'],
                        'deals_count' => $group['deals'],
                        'total_price' => (int) round($group['value']),
                        'total_area' => round($group['area'], 2),
                        'average_price_per_meter' => round($prices['average'], 2),
                        'lowest_price_per_meter' => $prices['lowest'] === null ? null : round($prices['lowest'], 2),
                        'highest_price_per_meter' => $prices['highest'] === null ? null : round($prices['highest'], 2),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
                MarketTypeStat::query()->insert($chunk);
            }

            return $record;
        });
    }
}
