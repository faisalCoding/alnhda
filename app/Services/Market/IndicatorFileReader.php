<?php

namespace App\Services\Market;

use App\Exceptions\UnreadableSalesFile;
use Generator;

/**
 * Reads a region's quarterly file of sale indicators as the Real Estate
 * General Authority publishes it on the open data portal.
 *
 * Unlike the ministry's deeds these are already added up: one row is a kind of
 * property in a place, with how many sold and for how much. The layout has
 * changed every year so far — by district in 2024 and most of 2025, by city
 * since — and so have the headings, so columns are found by name.
 */
class IndicatorFileReader
{
    use ReadsCsvFiles;

    /**
     * Every heading each column has been published under, as matched.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'year' => ['السنه'],
        'quarter' => ['الربع'],
        'region' => ['المنطقه'],
        'city' => ['المدينه'],
        'district' => ['الحي'],
        'type' => ['نوعالعقار', 'النوع'],
        'classification' => ['تصنيفالعقار', 'استخدامالعقار', 'الاستخدام'],
        'deals' => ['عددالصكوك', 'عددالصفقات'],
        'value' => ['مجموعسعرالعقار', 'قيمهالصفقات', 'مجموعقيمالصفقات'],
        'area' => ['المساحه', 'مجموعالمساحات'],
        'average' => ['متوسطسعرالمتر'],
        'highest' => ['الحدالاعليلسعرالمتر', 'اعليمتوسطسعرمتر'],
        'lowest' => ['الحدالادنيلسعرالمتر', 'اقلمتوسطسعرمتر'],
    ];

    /**
     * Columns a row can be read without.
     *
     * @var list<string>
     */
    private const OPTIONAL = ['district', 'classification', 'average', 'highest', 'lowest'];

    /**
     * @var array<string, int>
     */
    private const QUARTERS = ['الاول' => 1, 'الثاني' => 2, 'الثالث' => 3, 'الرابع' => 4];

    /**
     * Yields one row per kind of property in a place, or null for a row that
     * could not be read.
     *
     * @return Generator<int, array{year: int, quarter: int, region: string, city: string, district: string|null, type: string, classification: string, deals: int, value: float, area: float, average: float|null, highest: float|null, lowest: float|null}|null>
     *
     * @throws UnreadableSalesFile
     */
    public function rows(string $path): Generator
    {
        $handle = $this->open($path);

        try {
            $columns = $this->columns($this->row($handle) ?? []);

            while (($row = $this->row($handle)) !== null) {
                yield $this->indicator($row, $columns);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Whether the file carries the columns of an indicators file at all.
     */
    public function recognises(string $path): bool
    {
        try {
            $handle = $this->open($path);
        } catch (UnreadableSalesFile) {
            return false;
        }

        try {
            $this->columns($this->row($handle) ?? []);

            return true;
        } catch (UnreadableSalesFile) {
            return false;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Headings are matched without the units some quarters append to them
     * («المساحة M2», «متوسط سعر المتر ر.س») and without regard to hamzas.
     */
    private function heading(string $heading): string
    {
        return (string) preg_replace('/[\s\x{FEFF}]+|ر\.س|m2/iu', '', PlaceName::key($heading));
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     *
     * @throws UnreadableSalesFile
     */
    private function columns(array $header): array
    {
        $headings = $this->headings($header);
        $columns = [];

        foreach (self::COLUMNS as $column => $names) {
            foreach ($names as $name) {
                if (isset($headings[$name])) {
                    $columns[$column] = $headings[$name];

                    break;
                }
            }

            if (! isset($columns[$column]) && ! in_array($column, self::OPTIONAL, true)) {
                throw new UnreadableSalesFile('هذا ليس ملف مؤشرات صفقات البيع الذي تنشره الهيئة العامة للعقار: لا يوجد فيه عمود «'.$names[0].'».');
            }
        }

        return $columns;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $columns
     * @return array{year: int, quarter: int, region: string, city: string, district: string|null, type: string, classification: string, deals: int, value: float, area: float, average: float|null, highest: float|null, lowest: float|null}|null
     */
    private function indicator(array $row, array $columns): ?array
    {
        $value = fn (string $column): string => isset($columns[$column]) ? trim((string) ($row[$columns[$column]] ?? '')) : '';
        $positive = function (string $column) use ($value): ?float {
            $number = $this->number($value($column));

            return $number !== null && $number > 0 ? $number : null;
        };

        $year = (int) ($this->number($value('year')) ?? 0);
        $quarter = $this->quarter($value('quarter'));
        $city = PlaceName::label($value('city'));
        [$type, $classification] = $this->kind($value('type'), $value('classification'));
        $deals = (int) ($positive('deals') ?? 0);
        $price = $positive('value');
        $area = $positive('area');

        if ($year < 2000 || $quarter === null || $city === '' || $type === '' || $deals < 1 || $price === null || $area === null) {
            return null;
        }

        return [
            'year' => $year,
            'quarter' => $quarter,
            'region' => PlaceName::region($value('region')),
            'city' => $city,
            'district' => isset($columns['district']) ? PlaceName::label($value('district')) : null,
            'type' => $type,
            'classification' => $classification,
            'deals' => $deals,
            'value' => $price,
            'area' => $area,
            'average' => $positive('average'),
            'highest' => $positive('highest'),
            'lowest' => $positive('lowest'),
        ];
    }

    /**
     * The kind of property and its classification as two things.
     *
     * The 2024 files have no classification column and fold it into the kind
     * of land instead — «قطعة أرض-سكنى», «قطعة أرض- زراعي» — where later
     * files write «أرض» beside «سكني». Read the later way, land keeps one
     * history across both.
     *
     * @return array{0: string, 1: string}
     */
    private function kind(string $type, string $classification): array
    {
        $type = PlaceName::label($type);
        $classification = PlaceName::label($classification);

        if (preg_match('/^قطعة\s+[أا]رض\s*(?:-\s*(.+))?$/u', $type, $parts)) {
            $type = 'أرض';

            if ($classification === '' && isset($parts[1])) {
                $classification = str_replace('ى', 'ي', PlaceName::label($parts[1]));
            }
        }

        return [$type, $classification];
    }

    /**
     * A quarter written as a number or, where a file has only the name, as
     * «الربع الثاني».
     */
    private function quarter(string $value): ?int
    {
        if (in_array($value, ['1', '2', '3', '4'], true)) {
            return (int) $value;
        }

        foreach (self::QUARTERS as $name => $quarter) {
            if (str_contains(PlaceName::key($value), $name)) {
                return $quarter;
            }
        }

        return null;
    }
}
