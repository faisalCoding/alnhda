<?php

use App\Models\Admin;
use App\Models\MarketIndicatorFile;
use App\Models\MarketQuarter;
use App\Services\Market\CatalogueSync;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * The catalogue as a computer inside the Kingdom sends it.
 */
function cataloguePush(array $overrides = []): array
{
    return array_replace_recursive([
        'quarters' => [
            [
                'year' => 2026,
                'quarter' => 2,
                'dataset_id' => '34e1da80-d4cd-4d65-bdc8-a8d48a7e7ee2',
                'download_url' => 'https://open.data.gov.sa/odp-public/x/Transactions%20sale%20for%20real%20estate%20CSV.csv',
                'file_updated_at' => '2026-08-06T11:12:03.000000Z',
            ],
        ],
        'indicator_files' => [
            [
                'year' => 2026,
                'quarter' => 1,
                'region' => 'مكة المكرمة',
                'dataset_id' => '4c12f9d5-e1e4-445e-b824-96d24f4e755d',
                'download_url' => 'https://open.data.gov.sa/odp-public/y/indicators.csv',
                'file_updated_at' => '2026-05-21T09:37:19.000000Z',
            ],
        ],
    ], $overrides);
}

function actingWithCatalogueToken(array $abilities = [Admin::MARKET_CATALOGUE_TOKEN]): void
{
    Sanctum::actingAs(Admin::factory()->create(), $abilities);
}

it('takes nothing from a caller without a token', function () {
    $this->postJson('/api/market/catalogue', cataloguePush())->assertUnauthorized();

    expect(MarketQuarter::query()->count())->toBe(0);
});

it('takes nothing from a token issued for something else', function () {
    actingWithCatalogueToken(['tasks:self']);

    $this->postJson('/api/market/catalogue', cataloguePush())->assertForbidden();

    expect(MarketQuarter::query()->count())->toBe(0);
});

it('does not open the panel to a catalogue token', function () {
    actingWithCatalogueToken();

    $this->getJson(panelUrl('/api/market-prices'))->assertUnauthorized();
    $this->postJson(panelUrl('/api/market-prices/import'))->assertUnauthorized();
});

it('records the quarters and files it is told about', function () {
    actingWithCatalogueToken();

    $this->postJson('/api/market/catalogue', cataloguePush())
        ->assertSuccessful()
        ->assertJsonPath('data.published', 1)
        ->assertJsonPath('data.waiting', 1)
        ->assertJsonPath('data.indicators_waiting', 1);

    $quarter = MarketQuarter::query()->sole();
    $file = MarketIndicatorFile::query()->sole();

    expect($quarter->key())->toBe('2026-2')
        ->and($quarter->isImported())->toBeFalse()
        ->and($quarter->download_url)->toEndWith('Transactions%20sale%20for%20real%20estate%20CSV.csv')
        ->and($quarter->file_updated_at->toDateTimeString())->toBe('2026-08-06 11:12:03')
        ->and($file->region)->toBe('مكة المكرمة')
        ->and($file->region_key)->toBe('مكه المكرمه')
        ->and($file->key())->toBe('2026-1')
        ->and(app(CatalogueSync::class)->lastCheckedAt())->not->toBeNull();
});

it('leaves what was uploaded as it is and notices a replaced file', function () {
    MarketQuarter::factory()->period(2026, 2)->create(['dataset_id' => null, 'deals_count' => 500, 'imported_at' => '2026-08-01 09:00:00']);
    actingWithCatalogueToken();

    $this->postJson('/api/market/catalogue', cataloguePush())->assertSuccessful()->assertJsonPath('data.waiting', 0);

    $quarter = MarketQuarter::query()->sole();

    expect($quarter->isImported())->toBeTrue()
        ->and($quarter->deals_count)->toBe(500)
        ->and($quarter->dataset_id)->toBe('34e1da80-d4cd-4d65-bdc8-a8d48a7e7ee2')
        ->and($quarter->fileChangedSinceImport())->toBeTrue();
});

it('keeps a link it already had when told of a file without one', function () {
    MarketQuarter::factory()->pending()->period(2026, 2)->create(['download_url' => 'https://open.data.gov.sa/odp-public/old.csv']);
    actingWithCatalogueToken();

    $this->postJson('/api/market/catalogue', cataloguePush(['quarters' => [['download_url' => null, 'file_updated_at' => null]]]))
        ->assertSuccessful();

    expect(MarketQuarter::query()->sole()->download_url)->toBe('https://open.data.gov.sa/odp-public/old.csv');
});

it('refuses a catalogue that is not what the portal would say', function (array $overrides, string $field) {
    actingWithCatalogueToken();

    $this->postJson('/api/market/catalogue', cataloguePush($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(MarketQuarter::query()->count())->toBe(0)
        ->and(MarketIndicatorFile::query()->count())->toBe(0);
})->with([
    'a link that leaves the portal' => [['quarters' => [['download_url' => 'https://elsewhere.example/sales.csv']]], 'quarters.0.download_url'],
    'a link dressed as the portal' => [['quarters' => [['download_url' => 'https://open.data.gov.sa.elsewhere.example/x.csv']]], 'quarters.0.download_url'],
    'a script for a link' => [['indicator_files' => [['download_url' => 'javascript:alert(1)']]], 'indicator_files.0.download_url'],
    'a fifth quarter' => [['quarters' => [['quarter' => 5]]], 'quarters.0.quarter'],
    'a year out of range' => [['quarters' => [['year' => 1999]]], 'quarters.0.year'],
    'an id that is not one' => [['quarters' => [['dataset_id' => '../../etc']]], 'quarters.0.dataset_id'],
    'a date that is not one' => [['quarters' => [['file_updated_at' => 'yesterday-ish']]], 'quarters.0.file_updated_at'],
    'a file with no region' => [['indicator_files' => [['region' => '']]], 'indicator_files.0.region'],
]);

it('refuses an empty catalogue', function () {
    actingWithCatalogueToken();

    $this->postJson('/api/market/catalogue', ['quarters' => [], 'indicator_files' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quarters');
});

it('issues a token that can send the catalogue and nothing else', function () {
    $admin = Admin::factory()->create();

    $this->artisan('market:token', ['--plain' => true])->assertSuccessful();

    $token = $admin->tokens()->sole();

    expect($token->name)->toBe('catalogue')
        ->and($token->abilities)->toBe([Admin::MARKET_CATALOGUE_TOKEN]);
});

it('replaces the earlier token when one is issued again', function () {
    $admin = Admin::factory()->create();

    $this->artisan('market:token')->assertSuccessful();
    $first = $admin->tokens()->sole()->id;
    $this->artisan('market:token')->assertSuccessful();

    expect($admin->tokens()->count())->toBe(1)
        ->and($admin->tokens()->sole()->id)->not->toBe($first);
});

it('issues nothing when there is no admin to issue it to', function () {
    $this->artisan('market:token', ['admin' => 'nobody@example.com'])->assertFailed();
});

it('sends what it read from the portal on to the live panel', function () {
    config(['services.market.push_url' => 'https://kayanalnhda.example/api/market/catalogue', 'services.market.push_token' => 'secret-token']);

    Http::fake([
        'open.data.gov.sa/data/api/organizations*' => fn ($request) => Http::response(['datasets' => $request['organization'] === 'وزارة العدل'
            ? [['id' => 'q2-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الثاني']]
            : [['id' => 'makkah-q1-2026', 'titleAr' => 'مؤشرات صفقات البيع لمنطقة مكة المكرمة الربع الاول من عام 2026']]]),
        'open.data.gov.sa/data/api/datasets/resources*' => fn ($request) => Http::response(['resources' => [[
            'format' => 'CSV',
            'downloadUrl' => 'https://open.data.gov.sa/odp-public/'.$request['dataset'].'/file name.csv',
            'updatedAt' => '2026-08-06 14:12:03',
        ]]]),
        'kayanalnhda.example/*' => Http::response(['data' => ['published' => 1, 'waiting' => 1, 'indicators_waiting' => 1]]),
    ]);

    $this->artisan('market:check', ['--push' => true])
        ->expectsOutputToContain('أُرسل الفهرس إلى الخادم')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://kayanalnhda.example/api/market/catalogue'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['quarters'][0]['dataset_id'] === 'q2-2026'
        && $request['quarters'][0]['download_url'] === 'https://open.data.gov.sa/odp-public/q2-2026/file%20name.csv'
        && $request['quarters'][0]['file_updated_at'] === '2026-08-06T11:12:03.000000Z'
        && $request['indicator_files'][0]['region'] === 'مكة المكرمة');

    // What was read is recorded here as well as sent.
    expect(MarketQuarter::query()->count())->toBe(1);
});

it('sends a catalogue the live panel accepts', function () {
    config(['services.market.push_url' => 'https://kayanalnhda.example/api/market/catalogue', 'services.market.push_token' => 'secret-token']);

    $sent = null;

    Http::fake([
        'open.data.gov.sa/data/api/organizations*' => fn ($request) => Http::response(['datasets' => $request['organization'] === 'وزارة العدل'
            ? [['id' => 'q2-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الثاني']]
            : []]),
        'open.data.gov.sa/data/api/datasets/resources*' => Http::response(['resources' => []]),
        'kayanalnhda.example/*' => function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response(['data' => []]);
        },
    ]);

    $this->artisan('market:check', ['--push' => true])->assertSuccessful();

    MarketQuarter::query()->delete();
    actingWithCatalogueToken();

    // The very payload the command produced, given to the endpoint it is for.
    $this->postJson('/api/market/catalogue', $sent)->assertSuccessful()->assertJsonPath('data.published', 1);
});

it('says what is missing when asked to send with nowhere to send to', function () {
    config(['services.market.push_url' => null, 'services.market.push_token' => null]);
    Http::fake(['open.data.gov.sa/*' => Http::response(['datasets' => [['id' => 'q2-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الثاني']], 'resources' => []])]);

    $this->artisan('market:check', ['--push' => true])
        ->expectsOutputToContain('MARKET_PUSH_TOKEN')
        ->assertFailed();

    Http::assertNotSent(fn ($request): bool => ! str_contains($request->url(), 'open.data.gov.sa'));
});

it('fails when the live panel turns the catalogue away', function () {
    config(['services.market.push_url' => 'https://kayanalnhda.example/api/market/catalogue', 'services.market.push_token' => 'revoked']);
    Http::fake([
        'open.data.gov.sa/*' => Http::response(['datasets' => [['id' => 'q2-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الثاني']], 'resources' => []]),
        'kayanalnhda.example/*' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    $this->artisan('market:check', ['--push' => true])->expectsOutputToContain('401')->assertFailed();
});

it('stops the weekly check on a machine that cannot reach the portal, until it can', function () {
    $weekly = fn () => collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'market:check'));

    expect($weekly()->filtersPass(app()))->toBeTrue();

    $reachable = false;
    Http::fake(['open.data.gov.sa/*' => function () use (&$reachable) {
        return $reachable
            ? Http::response(['datasets' => [], 'resources' => []])
            : throw new ConnectionException('Failed to connect to open.data.gov.sa port 443');
    }]);

    $this->artisan('market:check')->assertFailed();

    expect(app(CatalogueSync::class)->portalUnreachable())->toBeTrue()
        ->and($weekly()->filtersPass(app()))->toBeFalse();

    $reachable = true;
    $this->artisan('market:check')->assertSuccessful();

    expect(app(CatalogueSync::class)->portalUnreachable())->toBeFalse()
        ->and($weekly()->filtersPass(app()))->toBeTrue();
});

it('tells the panel when its server cannot reach the portal', function () {
    $admin = Admin::factory()->create();
    Http::fake(['open.data.gov.sa/*' => Http::failedConnection()]);

    $this->actingAs($admin, 'admin')
        ->postJson(panelUrl('/api/market-prices/check'))
        ->assertStatus(502)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'market:check --push'));

    $this->actingAs($admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonPath('data.portal_unreachable', true)
        ->assertJsonPath('data.catalogue_checked_at', null);
});
