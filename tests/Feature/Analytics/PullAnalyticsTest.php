<?php

use App\Models\AnalyticsDay;
use App\Models\AnalyticsSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A real key pair, so the command signs a real assertion rather than skipping
 * the one part of the exchange that can silently be wrong.
 */
function fakeServiceAccount(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    $path = tempnam(sys_get_temp_dir(), 'ga4').'.json';
    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'client_email' => 'panel@example.iam.gserviceaccount.com',
        'private_key' => $pem,
    ]));

    config(['services.ga4.credentials' => $path, 'services.ga4.property_id' => '123456789']);

    return $path;
}

function ga4Row(string $date, int $users, int $sessions, int $views): array
{
    return [
        'dimensionValues' => [['value' => $date]],
        'metricValues' => [['value' => (string) $users], ['value' => (string) $sessions], ['value' => (string) $views]],
    ];
}

it('does nothing but explain itself when google is not connected', function () {
    config(['services.ga4.property_id' => null, 'services.ga4.credentials' => null]);

    $this->artisan('analytics:pull')
        ->expectsOutputToContain('GA4_PROPERTY_ID')
        ->assertSuccessful();

    expect(AnalyticsDay::query()->count())->toBe(0);
});

it('says so when the key file is missing rather than crashing the schedule', function () {
    config(['services.ga4.property_id' => '123', 'services.ga4.credentials' => '/no/such/key.json']);

    $this->artisan('analytics:pull')->assertSuccessful();
});

it('stores a row for each day google reports', function () {
    $path = fakeServiceAccount();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'analyticsdata.googleapis.com/*' => Http::sequence()
            ->push(['rows' => [ga4Row('20260901', 12, 15, 40), ga4Row('20260902', 20, 24, 71)]])
            ->push(['rows' => [['dimensionValues' => [['value' => '/projects']], 'metricValues' => [['value' => '31']]]]])
            ->push(['rows' => [['dimensionValues' => [['value' => 'Organic Search']], 'metricValues' => [['value' => '18']]]]])
            ->push(['rows' => [['dimensionValues' => [['value' => 'mobile']], 'metricValues' => [['value' => '30']]]]])
            ->push(['rows' => [['dimensionValues' => [['value' => 'Jeddah']], 'metricValues' => [['value' => '22']]]]]),
    ]);

    $this->artisan('analytics:pull', ['--days' => 7])->assertSuccessful();

    expect(AnalyticsDay::query()->count())->toBe(2)
        ->and(AnalyticsDay::query()->whereDate('date', '2026-09-02')->first())
        ->users->toBe(20)->sessions->toBe(24)->views->toBe(71);

    $summary = AnalyticsSummary::query()->where('period', AnalyticsSummary::CURRENT_PERIOD)->first();

    expect($summary->top_pages)->toBe([['label' => '/projects', 'value' => 31]])
        ->and($summary->channels)->toBe([['label' => 'Organic Search', 'value' => 18]])
        ->and($summary->cities)->toBe([['label' => 'Jeddah', 'value' => 22]])
        ->and($summary->pulled_at)->not->toBeNull();

    unlink($path);
});

it('updates the day it already had instead of adding it twice', function () {
    $path = fakeServiceAccount();
    AnalyticsDay::query()->create(['date' => '2026-09-01', 'users' => 1, 'sessions' => 1, 'views' => 1]);

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => [ga4Row('20260901', 99, 99, 99)]]),
    ]);

    $this->artisan('analytics:pull')->assertSuccessful();

    expect(AnalyticsDay::query()->count())->toBe(1)
        ->and(AnalyticsDay::query()->first()->users)->toBe(99);

    unlink($path);
});

it('reports a refusal from google instead of writing half a day', function () {
    $path = fakeServiceAccount();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'analyticsdata.googleapis.com/*' => Http::response(['error' => 'forbidden'], 403),
    ]);

    $this->artisan('analytics:pull')->assertFailed();

    expect(AnalyticsDay::query()->count())->toBe(0);

    unlink($path);
});

it('signs its own request rather than sending the key', function () {
    $path = fakeServiceAccount();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    $this->artisan('analytics:pull')->assertSuccessful();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
            return false;
        }

        $assertion = $request['assertion'] ?? '';

        return substr_count($assertion, '.') === 2
            && ! str_contains($assertion, 'BEGIN PRIVATE KEY');
    });

    unlink($path);
});
