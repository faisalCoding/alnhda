<?php

namespace App\Services\Market;

use App\Exceptions\UnreadableSalesFile;
use App\Models\ManualMarketUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads a file of property listings collected from listing sites into the
 * hand-entered market units.
 *
 * The file has one listing a row, under the headings the form uses:
 * source_site, listing_id, url, neighborhood, city, title, price_sar, area_m2,
 * rooms, bedrooms, bathrooms, street_width_m, facade, posted_at, updated_at,
 * scraped_at, possible_cross_site_duplicate, property_age, property_age_years.
 * A listing already entered — the same number on the same site — is updated,
 * so a file collected again next month refreshes the prices instead of
 * doubling them.
 */
class ListingFileImporter
{
    use ReadsCsvFiles;

    /**
     * The headings a listing cannot be read without.
     *
     * @var list<string>
     */
    private const REQUIRED = ['neighborhood', 'city', 'price_sar', 'area_m2'];

    /**
     * Words in a listing's title that say what kind of property it is, in the
     * order they are looked for: «فيلا مع شقة» is a villa.
     *
     * @var array<string, string>
     */
    private const KINDS = [
        '/فيلا|فيلة|فله|فلة|فلل/u' => 'فيلا',
        '/دوبلكس|دبلكس/u' => 'دوبلكس',
        '/عمارة|عماره|عمائر/u' => 'عمارة',
        '/شقة|شقه|شقق/u' => 'شقة',
        '/(?<!\p{L})دور(?!\p{L})|أدوار|ادوار/u' => 'دور',
        '/أرض|ارض|قطعة/u' => 'أرض',
    ];

    /**
     * @var array<string, string>
     */
    private const DIRECTIONS = ['north' => 'شمالية', 'south' => 'جنوبية', 'east' => 'شرقية', 'west' => 'غربية'];

    public function __construct(
        private readonly MarketPlaces $places,
        private readonly ListingDuplicates $duplicates,
    ) {}

    /**
     * @param  string|null  $kind  The kind for a listing whose title does not say.
     * @return array{added: int, updated: int, skipped: list<array{row: int, reason: string}>, properties: int, repeated: int, unknown_districts: list<string>}
     *
     * @throws UnreadableSalesFile
     */
    public function import(string $path, ?string $kind = null): array
    {
        $handle = $this->open($path);
        $report = ['added' => 0, 'updated' => 0, 'skipped' => [], 'unknown_districts' => []];
        $cities = [];
        $kinds = array_values(array_unique([...$this->places->kinds(), ...MarketPlaces::USUAL_KINDS]));

        try {
            $columns = $this->headings($this->row($handle) ?? []);

            foreach (self::REQUIRED as $heading) {
                if (! isset($columns[$heading])) {
                    throw new UnreadableSalesFile('هذا ليس ملف إعلانات بالصيغة المعروفة: لا يوجد فيه عمود «'.$heading.'».');
                }
            }

            DB::transaction(function () use ($handle, $columns, $kind, $kinds, &$report, &$cities): void {
                $line = 1;

                while (($row = $this->row($handle)) !== null) {
                    $line++;
                    $value = fn (string $heading): string => isset($columns[$heading]) ? trim((string) ($row[$columns[$heading]] ?? '')) : '';

                    if (implode('', array_map('trim', array_map('strval', $row))) === '') {
                        continue;
                    }

                    $attributes = $this->listing($value, $kind, $kinds, $problem);

                    if ($attributes === null) {
                        $report['skipped'][] = ['row' => $line, 'reason' => $problem];

                        continue;
                    }

                    $unit = filled($attributes['source_site']) && filled($attributes['listing_id'])
                        ? ManualMarketUnit::query()->firstOrNew(['source_site' => $attributes['source_site'], 'listing_id' => $attributes['listing_id']])
                        : new ManualMarketUnit;

                    $report[$unit->exists ? 'updated' : 'added']++;
                    $unit->fill($attributes)->save();
                    $cities[$attributes['city_key']] = true;

                    $known = array_column($this->places->districts($attributes['city_key']), 'key');

                    if ($known !== [] && ! in_array($attributes['district_key'], $known, true)) {
                        $report['unknown_districts'][$attributes['district']] = true;
                    }
                }
            });
        } finally {
            fclose($handle);
        }

        if ($report['added'] + $report['updated'] === 0) {
            throw new UnreadableSalesFile('لم يُقرأ من الملف أي إعلان: '.($report['skipped'][0]['reason'] ?? 'الملف فارغ.'));
        }

        $repeated = $this->duplicates->regroup(array_keys($cities));

        return [
            'added' => $report['added'],
            'updated' => $report['updated'],
            'skipped' => $report['skipped'],
            'properties' => ManualMarketUnit::oneEach(ManualMarketUnit::query()->whereIn('city_key', array_keys($cities))->get())->count(),
            'repeated' => $repeated,
            'unknown_districts' => array_keys($report['unknown_districts']),
        ];
    }

    /**
     * One row as the unit it describes, or null with the reason it cannot be.
     *
     * @param  callable(string): string  $value
     * @param  list<string>  $kinds
     * @return array<string, mixed>|null
     */
    private function listing(callable $value, ?string $fallbackKind, array $kinds, ?string &$problem): ?array
    {
        $price = $this->number($value('price_sar'));
        $area = $this->number($value('area_m2'));
        $city = PlaceName::label($value('city'));
        $district = PlaceName::district($value('neighborhood'), $city);
        $kind = $this->kind($value('title')) ?? $fallbackKind;

        $problem = match (true) {
            $city === '' => 'لا مدينة.',
            $district === '' => 'لا حي.',
            $price === null || $price <= 0 => 'لا سعر.',
            $area === null || $area <= 0 => 'لا مساحة.',
            $kind === null => 'العنوان لا يذكر نوع العقار.',
            ! in_array($kind, $kinds, true) => 'نوع العقار «'.$kind.'» ليس من الأنواع التي تُسعَّر بها السوق.',
            default => null,
        };

        if ($problem !== null) {
            return null;
        }

        $cityKey = PlaceName::key($city);
        $districtKey = PlaceName::key($district);
        $site = strtolower($value('source_site'));
        $url = $value('url');

        return [
            'name' => $value('title') ?: null,
            'source_site' => $site !== '' ? $site : null,
            'listing_id' => $value('listing_id') ?: null,
            'city' => $this->places->cityLabel($cityKey) ?? PlaceName::preferred($city),
            'city_key' => $cityKey,
            'district' => $this->places->districtLabel($cityKey, $districtKey) ?? $district,
            'district_key' => $districtKey,
            'kind' => $kind,
            'area' => $area,
            'price' => (int) round($price),
            'price_per_meter' => round($price / $area, 2),
            'rooms' => $this->count($value('rooms')),
            'bedrooms' => $this->count($value('bedrooms')),
            'bathrooms' => $this->count($value('bathrooms')),
            'street_width' => $value('street_width_m') ?: null,
            'facade' => $this->facade($value('facade')),
            'property_age' => $value('property_age') ?: null,
            'property_age_years' => $this->count($value('property_age_years')),
            'posted_on' => $this->day($value('posted_at')),
            'listing_updated_on' => $this->day($value('updated_at')),
            'observed_on' => $this->day($value('scraped_at')) ?? now()->toDateString(),
            'source_url' => preg_match('#^https?://#i', $url) ? $url : null,
            'duplicates' => $value('possible_cross_site_duplicate') ?: null,
        ];
    }

    /**
     * The kind of property a listing's title names.
     */
    private function kind(string $title): ?string
    {
        foreach (self::KINDS as $pattern => $kind) {
            if (preg_match($pattern, $title)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * A count as listing sites write it: «7», «10+», or nothing.
     */
    private function count(string $value): ?int
    {
        return preg_match('/^\d+/', $value, $digits) ? (int) $digits[0] : null;
    }

    /**
     * A facade in Arabic, whichever language the site wrote it in.
     */
    private function facade(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $words = preg_split('/[\s,\/-]+/', strtolower($value)) ?: [];

        if ($words !== [] && array_diff($words, array_keys(self::DIRECTIONS)) === []) {
            return implode(' ', array_map(fn (string $word): string => self::DIRECTIONS[$word], $words));
        }

        return $value;
    }

    /**
     * The day a listing timestamp falls on in Riyadh.
     */
    private function day(string $value): ?string
    {
        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Asia/Riyadh')->setTimezone('Asia/Riyadh')->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
