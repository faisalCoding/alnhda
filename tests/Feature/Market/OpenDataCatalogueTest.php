<?php

use App\Models\MarketIndicatorFile;
use App\Models\MarketQuarter;
use Illuminate\Support\Facades\Http;

function cataloguePayload(): array
{
    return [
        'id' => '35c63412-c4ae-4303-8fef-56cfd71303cf',
        'nameAr' => 'وزارة العدل',
        'datasets' => [
            ['id' => 'q2-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الثاني', 'titleEn' => 'Total number of transactions sales'],
            ['id' => 'q1-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الاول', 'titleEn' => 'Total number of transactions sales'],
            ['id' => 'q1-2023', 'titleAr' => 'الصفقات العقارية 2023  - الربع الأول', 'titleEn' => 'Realestate Sale 2023 Q1'],
            ['id' => 'q4-2023', 'titleAr' => 'الصفقات العقارية 2023  الربع الرابع', 'titleEn' => 'Total number of transactions sales'],
            // Other things the ministry publishes, some with «بيع» in the title.
            ['id' => 'courts', 'titleAr' => 'القضايا المقيدة في محاكم الدرجة الأولى', 'titleEn' => 'Incoming cases'],
            ['id' => 'auction', 'titleAr' => 'القرارات التنفيذية في قرار بيع عقار أو منقول للمنفذ ضده 2025 الربع الثالث', 'titleEn' => 'Decisions'],
        ],
    ];
}

/**
 * What the Real Estate General Authority lists: sale indicators per region
 * under two wordings, and rent indicators that are not asked for.
 */
function authorityPayload(): array
{
    return [
        'nameAr' => 'الهيئة العامة للعقار',
        'datasets' => [
            ['id' => 'makkah-q1-2026', 'titleAr' => 'مؤشرات صفقات البيع لمنطقة مكة المكرمة الربع الاول من عام 2026'],
            // The 2024 wording, one of them with no space before the quarter.
            ['id' => 'makkah-q2-2024', 'titleAr' => 'مؤشرات البيع لمنطقة مكة المكرمةالربع الثاني لعام 2024 '],
            ['id' => 'riyadh-q1-2026', 'titleAr' => 'مؤشرات صفقات البيع لمنطقة الرياض الربع الاول من عام 2026'],
            ['id' => 'rent-q1-2026', 'titleAr' => 'مؤشر الايجار لمنطقة مكة المكرمة الربع الاول من عام 2026'],
            ['id' => 'no-period', 'titleAr' => 'مؤشرات صفقات البيع'],
        ],
    ];
}

/**
 * What the portal says about one dataset's files: the spreadsheet copy first,
 * the way it lists them, then the CSV.
 */
function resourcesPayload(string $datasetId, string $updatedAt = '2026-08-06 14:12:03'): array
{
    $folder = 'https://open.data.gov.sa/odp-public/35c63412/'.$datasetId.'/v2/';

    return [
        'datasetId' => $datasetId,
        'resources' => [
            ['id' => 'x', 'format' => 'XLSX', 'downloadUrl' => $folder.'Transactions sale for real estate Excel.xlsx', 'updatedAt' => $updatedAt],
            ['id' => 'c', 'format' => 'CSV', 'downloadUrl' => $folder.'Transactions sale for real estate CSV.csv', 'updatedAt' => $updatedAt],
        ],
    ];
}

/**
 * The portal as the check sees it: the catalogue, then one answer per dataset.
 *
 * @param  array<string, mixed>  $resources  Answers by dataset id, for the ones that should differ.
 */
function fakePortal(array $resources = [], mixed $authority = null): void
{
    Http::fake([
        'open.data.gov.sa/data/api/organizations*' => fn ($request) => $request['organization'] === 'وزارة العدل'
            ? Http::response(cataloguePayload())
            : ($authority ?? Http::response(authorityPayload())),
        'open.data.gov.sa/data/api/datasets/resources*' => function ($request) use ($resources) {
            $id = $request['dataset'];

            return $resources[$id] ?? Http::response(resourcesPayload($id));
        },
    ]);
}

it('notes the quarters the portal has published', function () {
    fakePortal();

    $this->artisan('market:check')->assertSuccessful();

    expect(MarketQuarter::query()->chronological()->get()->map->key()->all())
        ->toBe(['2023-1', '2023-4', '2026-1', '2026-2'])
        ->and(MarketQuarter::query()->imported()->count())->toBe(0)
        ->and(MarketQuarter::query()->where('year', 2026)->where('quarter', 2)->first()->portalUrl())
        ->toBe('https://open.data.gov.sa/ar/datasets/view/q2-2026/resources');
});

it('asks for the ministry of justice, then once for each quarter of sales', function () {
    fakePortal();

    $this->artisan('market:check')->assertSuccessful();

    // Two catalogues, the four sales datasets and the two indicator files for
    // the region the panel follows: not the courts, the auctions, the rent
    // indicators or another region.
    Http::assertSentCount(8);
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://open.data.gov.sa/data/api/organizations')
        && $request['organization'] === 'وزارة العدل');
    Http::assertSent(fn ($request): bool => ($request['organization'] ?? null) === 'الهيئة العامة للعقار');
    Http::assertNotSent(fn ($request): bool => in_array(
        $request['dataset'] ?? null,
        ['courts', 'auction', 'riyadh-q1-2026', 'rent-q1-2026', 'no-period'],
        true,
    ));
});

it('notes the authority\'s indicator files for the region it follows', function () {
    fakePortal();

    $this->artisan('market:check')->assertSuccessful();

    $files = MarketIndicatorFile::query()->chronological()->get();

    expect($files->map->key()->all())->toBe(['2024-2', '2026-1'])
        ->and($files->pluck('region')->unique()->all())->toBe(['مكة المكرمة'])
        ->and($files->every(fn (MarketIndicatorFile $file): bool => ! $file->isImported()))->toBeTrue()
        ->and($files->last()->portalUrl())->toBe('https://open.data.gov.sa/ar/datasets/view/makkah-q1-2026/resources')
        ->and($files->last()->download_url)->toEndWith('/makkah-q1-2026/v2/Transactions%20sale%20for%20real%20estate%20CSV.csv');
});

it('leaves an uploaded indicator file uploaded and notices when it is replaced', function () {
    MarketIndicatorFile::factory()->period(2026, 1)->create(['dataset_id' => null, 'imported_at' => '2026-06-01 09:00:00', 'deals_count' => 700]);

    fakePortal();

    $this->artisan('market:check')->assertSuccessful();

    $file = MarketIndicatorFile::query()->where('year', 2026)->sole();

    expect($file->isImported())->toBeTrue()
        ->and($file->deals_count)->toBe(700)
        ->and($file->dataset_id)->toBe('makkah-q1-2026')
        // The fake portal dates every file 6 August, after this upload.
        ->and($file->fileChangedSinceImport())->toBeTrue();
});

it('still notes the ministry\'s quarters when the authority\'s list cannot be read', function () {
    fakePortal(authority: Http::response('<html><title>Request Rejected</title></html>'));

    $this->artisan('market:check')->assertSuccessful();

    expect(MarketQuarter::query()->count())->toBe(4)
        ->and(MarketIndicatorFile::query()->count())->toBe(0);
});

it('records where each file is downloaded from and when it was last replaced', function () {
    fakePortal();

    $this->artisan('market:check')->assertSuccessful();

    $quarter = MarketQuarter::query()->where('year', 2026)->where('quarter', 2)->sole();

    expect($quarter->download_url)
        ->toBe('https://open.data.gov.sa/odp-public/35c63412/q2-2026/v2/Transactions%20sale%20for%20real%20estate%20CSV.csv')
        // Written by the portal in Riyadh time, kept here in UTC.
        ->and($quarter->file_updated_at->toDateTimeString())->toBe('2026-08-06 11:12:03');
});

it('keeps what it knew about a file when the portal does not answer for it', function (mixed $answer) {
    MarketQuarter::factory()->period(2026, 2)->create([
        'dataset_id' => 'q2-2026',
        'download_url' => 'https://open.data.gov.sa/odp-public/old.csv',
        'file_updated_at' => '2026-07-01 00:00:00',
    ]);

    fakePortal(['q2-2026' => $answer()]);

    $this->artisan('market:check')->assertSuccessful();

    $quarter = MarketQuarter::query()->where('year', 2026)->where('quarter', 2)->sole();

    expect($quarter->download_url)->toBe('https://open.data.gov.sa/odp-public/old.csv')
        ->and($quarter->file_updated_at->toDateString())->toBe('2026-07-01')
        // The others were still read.
        ->and(MarketQuarter::query()->where('year', 2026)->where('quarter', 1)->sole()->download_url)->not->toBeNull();
})->with([
    'a server error' => [fn () => fn () => Http::response(['message' => 'down'], 503)],
    'a rejection page' => [fn () => fn () => Http::response('<html><title>Request Rejected</title></html>')],
    'no csv among the files' => [fn () => fn () => Http::response(['resources' => [['format' => 'XLSX', 'downloadUrl' => 'https://open.data.gov.sa/x.xlsx']]])],
    'a link that leaves the portal' => [fn () => fn () => Http::response(['resources' => [['format' => 'CSV', 'downloadUrl' => 'https://elsewhere.example/x.csv']]])],
]);

it('notices a file the ministry replaced after it was uploaded', function () {
    MarketQuarter::factory()->period(2026, 2)->create(['imported_at' => '2026-08-01 09:00:00']);
    MarketQuarter::factory()->period(2026, 1)->create(['imported_at' => '2026-09-01 09:00:00']);

    fakePortal();

    $this->artisan('market:check')->assertSuccessful();

    expect(MarketQuarter::query()->where('quarter', 2)->where('year', 2026)->sole()->fileChangedSinceImport())->toBeTrue()
        ->and(MarketQuarter::query()->where('quarter', 1)->where('year', 2026)->sole()->fileChangedSinceImport())->toBeFalse()
        // Never uploaded, so there is nothing for the file to be newer than.
        ->and(MarketQuarter::query()->where('year', 2023)->where('quarter', 4)->sole()->fileChangedSinceImport())->toBeFalse();
});

it('leaves an imported quarter imported', function () {
    fakePortal();
    MarketQuarter::factory()->period(2026, 1)->create(['dataset_id' => null, 'deals_count' => 500]);

    $this->artisan('market:check')->assertSuccessful();

    $quarter = MarketQuarter::query()->where('year', 2026)->where('quarter', 1)->sole();

    expect($quarter->isImported())->toBeTrue()
        ->and($quarter->deals_count)->toBe(500)
        ->and($quarter->dataset_id)->toBe('q1-2026');
});

it('fails without writing anything when the portal turns the request away', function (mixed $response) {
    Http::fake(['open.data.gov.sa/*' => $response]);

    $this->artisan('market:check')->assertFailed();

    expect(MarketQuarter::query()->count())->toBe(0);
})->with([
    'a rejection page' => [fn () => Http::response('<html><head><title>Request Rejected</title></head></html>', 200, ['Content-Type' => 'text/html'])],
    'a server error' => [fn () => Http::response(['message' => 'down'], 503)],
    'a different shape' => [fn () => Http::response(['datasets' => 'none'])],
]);
