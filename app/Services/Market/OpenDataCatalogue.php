<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Asks the national open data portal which quarters of sale deeds the Ministry
 * of Justice has published.
 *
 * Only the catalogue is read. The portal refuses to hand the files themselves
 * to anything but a person's browser, so each quarter is downloaded by hand
 * and uploaded in the panel; this is what tells the panel one is waiting.
 */
class OpenDataCatalogue
{
    public const PUBLISHER_URL = 'https://open.data.gov.sa/ar/publishers/35c63412-c4ae-4303-8fef-56cfd71303cf';

    private const ENDPOINT = 'https://open.data.gov.sa/data/api/organizations';

    private const PUBLISHER = 'وزارة العدل';

    /**
     * @var array<string, int>
     */
    private const QUARTERS = ['الاول' => 1, 'الثاني' => 2, 'الثالث' => 3, 'الرابع' => 4];

    /**
     * @return list<array{year: int, quarter: int, dataset_id: string}> Oldest first.
     *
     * @throws RuntimeException
     */
    public function salesQuarters(): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->get(self::ENDPOINT, ['version' => -1, 'organization' => self::PUBLISHER]);

        $datasets = $response->successful() ? $response->json('datasets') : null;

        if (! is_array($datasets)) {
            throw new RuntimeException('منصة البيانات المفتوحة لم تُعد قائمة مجموعات البيانات (الحالة '.$response->status().').');
        }

        $quarters = [];

        foreach ($datasets as $dataset) {
            $period = $this->period((string) ($dataset['titleAr'] ?? ''));
            $id = (string) ($dataset['id'] ?? '');

            if ($period === null || $id === '') {
                continue;
            }

            $quarters[$period[0].'-'.$period[1]] ??= ['year' => $period[0], 'quarter' => $period[1], 'dataset_id' => $id];
        }

        ksort($quarters);

        return array_values($quarters);
    }

    /**
     * The quarter a dataset covers, read from a title such as
     * «الصفقات العقارية 2026 الربع الثاني». Titles are not written to one
     * pattern, so the year and the quarter are looked for wherever they sit.
     *
     * @return array{0: int, 1: int}|null
     */
    private function period(string $title): ?array
    {
        $title = PlaceName::key($title);

        if (! str_starts_with($title, 'الصفقات العقاريه') || ! preg_match('/20\d{2}/', $title, $year)) {
            return null;
        }

        foreach (self::QUARTERS as $word => $quarter) {
            if (str_contains($title, 'الربع '.$word)) {
                return [(int) $year[0], $quarter];
            }
        }

        return null;
    }
}
