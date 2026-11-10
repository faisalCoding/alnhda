<?php

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

it('notes the quarters the portal has published', function () {
    Http::fake(['open.data.gov.sa/*' => Http::response(cataloguePayload())]);

    $this->artisan('market:check')->assertSuccessful();

    expect(MarketQuarter::query()->chronological()->get()->map->key()->all())
        ->toBe(['2023-1', '2023-4', '2026-1', '2026-2'])
        ->and(MarketQuarter::query()->imported()->count())->toBe(0)
        ->and(MarketQuarter::query()->where('year', 2026)->where('quarter', 2)->first()->portalUrl())
        ->toBe('https://open.data.gov.sa/ar/datasets/view/q2-2026/resources');
});

it('asks for the ministry of justice and nothing else', function () {
    Http::fake(['open.data.gov.sa/*' => Http::response(cataloguePayload())]);

    $this->artisan('market:check')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://open.data.gov.sa/data/api/organizations')
        && $request['organization'] === 'وزارة العدل');
});

it('leaves an imported quarter imported', function () {
    Http::fake(['open.data.gov.sa/*' => Http::response(cataloguePayload())]);
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
