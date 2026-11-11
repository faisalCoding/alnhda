<?php

namespace App\Services\Market;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Asks the national open data portal which quarters of sale deeds the Ministry
 * of Justice has published, through the API the portal documents on each
 * dataset's own page.
 *
 * That API describes datasets and their files; it does not serve the rows, and
 * the portal refuses to hand the files to anything but a person's browser. So
 * each quarter is still downloaded by hand and uploaded in the panel. What
 * this gives the panel is the list of quarters, the link each file is
 * downloaded from, and the day the ministry last replaced it.
 */
class OpenDataCatalogue
{
    public const PUBLISHER_URL = 'https://open.data.gov.sa/ar/publishers/35c63412-c4ae-4303-8fef-56cfd71303cf';

    private const ENDPOINT = 'https://open.data.gov.sa/data/api/organizations';

    private const RESOURCES_ENDPOINT = 'https://open.data.gov.sa/data/api/datasets/resources';

    /**
     * How many datasets are asked about at once. The portal allows far more;
     * this keeps a check to a few seconds without leaning on it.
     */
    private const CONCURRENCY = 6;

    private const PUBLISHER = 'وزارة العدل';

    public const AUTHORITY_URL = 'https://open.data.gov.sa/ar/publishers/fb522d21-fd36-4842-ac14-d838502a292b';

    private const AUTHORITY = 'الهيئة العامة للعقار';

    /**
     * The regions whose sale indicators the panel keeps track of. The
     * authority publishes one file per region per quarter, and asking about
     * all thirteen would make every check four times as long for figures the
     * company does not trade in. A file for any other region can still be
     * uploaded; it just is not listed as waiting.
     *
     * @var list<string>
     */
    private const FOLLOWED_REGIONS = ['مكة المكرمة'];

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
        $quarters = [];

        foreach ($this->datasets(self::PUBLISHER) as $dataset) {
            $period = $this->period($dataset['title'], ['الصفقات العقاريه']);

            if ($period !== null) {
                $quarters[$period[0].'-'.$period[1]] ??= ['year' => $period[0], 'quarter' => $period[1], 'dataset_id' => $dataset['id']];
            }
        }

        ksort($quarters);

        return array_values($quarters);
    }

    /**
     * The authority's quarterly sale indicators by property type, for the
     * regions the panel follows.
     *
     * @return list<array{year: int, quarter: int, region: string, dataset_id: string}> Oldest first.
     *
     * @throws RuntimeException
     */
    public function indicatorFiles(): array
    {
        $files = [];

        foreach ($this->datasets(self::AUTHORITY) as $dataset) {
            $period = $this->period($dataset['title'], ['مؤشرات صفقات البيع', 'مؤشرات البيع']);

            if ($period === null) {
                continue;
            }

            foreach (self::FOLLOWED_REGIONS as $region) {
                if (str_contains(PlaceName::key($dataset['title']), PlaceName::key($region))) {
                    $files[$period[0].'-'.$period[1].'-'.$region] ??= [
                        'year' => $period[0],
                        'quarter' => $period[1],
                        'region' => $region,
                        'dataset_id' => $dataset['id'],
                    ];
                }
            }
        }

        ksort($files);

        return array_values($files);
    }

    /**
     * Everything one publisher has on the portal, by id and Arabic title.
     *
     * @return list<array{id: string, title: string}>
     *
     * @throws RuntimeException
     */
    private function datasets(string $publisher): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->get(self::ENDPOINT, ['version' => -1, 'organization' => $publisher]);

        $datasets = $response->successful() ? $response->json('datasets') : null;

        if (! is_array($datasets)) {
            throw new RuntimeException('منصة البيانات المفتوحة لم تُعد قائمة مجموعات البيانات (الحالة '.$response->status().').');
        }

        $listed = [];

        foreach ($datasets as $dataset) {
            $id = (string) ($dataset['id'] ?? '');

            if ($id !== '') {
                $listed[] = ['id' => $id, 'title' => (string) ($dataset['titleAr'] ?? '')];
            }
        }

        return $listed;
    }

    /**
     * Where each dataset's CSV is downloaded from and when it was last
     * replaced. A dataset the portal did not answer for is simply left out, so
     * one bad answer does not cost the rest.
     *
     * @param  list<string>  $datasetIds
     * @return array<string, array{download_url: string, updated_at: string|null}>
     */
    public function files(array $datasetIds): array
    {
        $files = [];

        foreach (array_chunk($datasetIds, self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $id) => $pool->as($id)
                    ->timeout(20)
                    ->acceptJson()
                    ->get(self::RESOURCES_ENDPOINT, ['version' => -1, 'dataset' => $id]),
                $chunk,
            ));

            foreach ($responses as $id => $response) {
                $resources = $response instanceof Response && $response->successful() ? $response->json('resources') : null;

                foreach (is_array($resources) ? $resources : [] as $resource) {
                    $url = (string) ($resource['downloadUrl'] ?? '');

                    if (strtoupper((string) ($resource['format'] ?? '')) !== 'CSV' || ! str_starts_with($url, 'https://open.data.gov.sa/')) {
                        continue;
                    }

                    $files[$id] = [
                        // The portal writes file names with spaces in them.
                        'download_url' => str_replace(' ', '%20', $url),
                        'updated_at' => $resource['updatedAt'] ?? $resource['createdAt'] ?? null,
                    ];

                    break;
                }
            }
        }

        return $files;
    }

    /**
     * The quarter a dataset covers, read from a title such as
     * «الصفقات العقارية 2026 الربع الثاني». Titles are not written to one
     * pattern, so the year and the quarter are looked for wherever they sit.
     *
     * @param  list<string>  $openings  What the title must begin with, as matched.
     * @return array{0: int, 1: int}|null
     */
    private function period(string $title, array $openings): ?array
    {
        $title = PlaceName::key($title);

        $opens = array_filter($openings, fn (string $opening): bool => str_starts_with($title, $opening));

        if ($opens === [] || ! preg_match('/20\d{2}/', $title, $year)) {
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
