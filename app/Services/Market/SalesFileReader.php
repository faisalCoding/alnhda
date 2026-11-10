<?php

namespace App\Services\Market;

use App\Exceptions\UnreadableSalesFile;
use Generator;

/**
 * Reads a quarter of sale deeds as the Ministry of Justice publishes it on the
 * open data portal.
 *
 * The layout has changed more than once since 2020: columns were added and
 * renamed, and one quarter wrote its dates month first. Columns are therefore
 * found by name, under every name they have carried, never by position.
 */
class SalesFileReader
{
    /**
     * Every heading each column has been published under, spacing removed.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'region' => ['المنطقة'],
        'city' => ['المدينة'],
        'district' => ['المدينة/الحي', 'الحي'],
        'date' => ['تاريخالصفقةميلادي', 'التاريخالميلادي', 'التاريخميلادي', 'التاريخ'],
        'classification' => ['تصنيفالعقار'],
        'properties' => ['عددالعقارات'],
        'price' => ['السعر', 'السعربالريالالسعودي'],
        'area' => ['المساحة'],
    ];

    /**
     * Columns a deed can be read without.
     *
     * @var list<string>
     */
    private const OPTIONAL = ['classification', 'properties'];

    /**
     * Yields one deed per row, or null for a row that could not be read: a
     * blank line, a total, a deed with no price or no area.
     *
     * @return Generator<int, array{year: int, quarter: int, region: string, city: string, district: string, classification: string, properties: int, price: float, area: float}|null>
     *
     * @throws UnreadableSalesFile
     */
    public function deals(string $path): Generator
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new UnreadableSalesFile('تعذّر فتح الملف.');
        }

        try {
            $columns = $this->columns($this->row($handle) ?? []);

            while (($row = $this->row($handle)) !== null) {
                yield $this->deal($row, $columns);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return list<string|null>|null
     */
    private function row($handle): ?array
    {
        $row = fgetcsv($handle, null, ',', '"', '');

        return $row === false ? null : $row;
    }

    /**
     * Where each column sits in this particular file.
     *
     * @param  list<string|null>  $header
     * @return array<string, int>
     *
     * @throws UnreadableSalesFile
     */
    private function columns(array $header): array
    {
        $headings = [];

        foreach ($header as $position => $heading) {
            $heading = (string) $heading;

            if (! mb_check_encoding($heading, 'UTF-8')) {
                throw new UnreadableSalesFile('ترميز الملف ليس UTF-8. نزّل ملف CSV من منصة البيانات المفتوحة كما هو دون فتحه في Excel.');
            }

            $headings[(string) preg_replace('/[\s\x{FEFF}]+/u', '', $heading)] = $position;
        }

        $columns = [];

        foreach (self::COLUMNS as $column => $names) {
            foreach ($names as $name) {
                if (isset($headings[$name])) {
                    $columns[$column] = $headings[$name];

                    break;
                }
            }

            if (! isset($columns[$column]) && ! in_array($column, self::OPTIONAL, true)) {
                throw new UnreadableSalesFile('هذا ليس ملف الصفقات العقارية الذي تنشره وزارة العدل: لا يوجد فيه عمود «'.$names[0].'».');
            }
        }

        return $columns;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $columns
     * @return array{year: int, quarter: int, region: string, city: string, district: string, classification: string, properties: int, price: float, area: float}|null
     */
    private function deal(array $row, array $columns): ?array
    {
        $value = fn (string $column): string => isset($columns[$column]) ? trim((string) ($row[$columns[$column]] ?? '')) : '';

        $period = $this->period($value('date'));
        $price = $this->number($value('price'));
        $area = $this->number($value('area'));
        $city = PlaceName::label($value('city'));

        if ($period === null || $price === null || $area === null || $price <= 0 || $area <= 0 || $city === '') {
            return null;
        }

        return [
            'year' => $period[0],
            'quarter' => $period[1],
            'region' => PlaceName::region($value('region')),
            'city' => $city,
            'district' => PlaceName::district($value('district'), $city),
            'classification' => PlaceName::label($value('classification')),
            'properties' => max(1, (int) ($this->number($value('properties')) ?? 1)),
            'price' => $price,
            'area' => $area,
        ];
    }

    /**
     * The year and quarter a deed was registered in.
     *
     * Dates are written year first («2026/01/31») in every quarter but the
     * first of 2023, which wrote them month first («1/31/2023»). A Hijri date
     * read by mistake falls before 2000 and is refused.
     *
     * @return array{0: int, 1: int}|null
     */
    private function period(string $date): ?array
    {
        if (preg_match('#^(\d{4})[/-](\d{1,2})[/-]\d{1,2}#', $date, $parts)) {
            [$year, $month] = [(int) $parts[1], (int) $parts[2]];
        } elseif (preg_match('#^(\d{1,2})/\d{1,2}/(\d{4})#', $date, $parts)) {
            [$year, $month] = [(int) $parts[2], (int) $parts[1]];
        } else {
            return null;
        }

        if ($year < 2000 || $month < 1 || $month > 12) {
            return null;
        }

        return [$year, intdiv($month - 1, 3) + 1];
    }

    /**
     * A figure written with thousands separators, as «1,800,000».
     */
    private function number(string $value): ?float
    {
        $value = str_replace([',', ' ', "\u{00A0}"], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}
