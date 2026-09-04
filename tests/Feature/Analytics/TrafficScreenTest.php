<?php

use App\Models\Admin;
use App\Models\AnalyticsDay;
use App\Models\AnalyticsSummary;
use App\Models\ServerLogDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

it('keeps the traffic api away from guests', function () {
    $this->getJson(panelUrl('/api/traffic'))->assertUnauthorized();
});

it('serves the traffic screen to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get(panelUrl('/traffic'))
        ->assertOk()
        ->assertSee('حركة السير')
        ->assertSee('trafficPage()', false);
});

it('adds up the days in the range and compares them with the ones before', function () {
    AnalyticsDay::query()->create(['date' => Carbon::yesterday(), 'users' => 30, 'sessions' => 40, 'views' => 90]);
    AnalyticsDay::query()->create(['date' => Carbon::yesterday()->subDays(2), 'users' => 20, 'sessions' => 25, 'views' => 60]);
    // Before the window: belongs to the comparison, not the total.
    AnalyticsDay::query()->create(['date' => Carbon::yesterday()->subDays(10), 'users' => 100, 'sessions' => 100, 'views' => 100]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/traffic?days=7'))
        ->assertSuccessful()
        ->assertJsonPath('data.google.totals.users', 50)
        ->assertJsonPath('data.google.previous_totals.users', 100)
        ->assertJsonPath('data.range.days', 7);
});

it('draws one row per day in the range, including the quiet ones', function () {
    AnalyticsDay::query()->create(['date' => Carbon::yesterday(), 'users' => 5, 'sessions' => 5, 'views' => 5]);

    $response = $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/traffic?days=7'))
        ->assertSuccessful();

    expect($response->json('data.days'))->toHaveCount(7)
        ->and($response->json('data.days.6.users'))->toBe(5)
        ->and($response->json('data.days.0.users'))->toBe(0);
});

it('falls back to a month when asked for a range it does not offer', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/traffic?days=999'))
        ->assertSuccessful()
        ->assertJsonPath('data.range.days', 30);
});

it('reports the server log beside the analytics, and the crawlers apart', function () {
    ServerLogDay::query()->create([
        'date' => Carbon::yesterday(),
        'requests' => 500,
        'unique_addresses' => 90,
        'bytes' => 1048576,
        'bot_requests' => 200,
        'status_2xx' => 450,
        'status_4xx' => 40,
        'status_5xx' => 10,
        'top_bots' => [['label' => 'Googlebot', 'value' => 120]],
        'not_found' => [['label' => '/old', 'value' => 12]],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/traffic?days=7'))
        ->assertSuccessful()
        ->assertJsonPath('data.server.totals.requests', 500)
        ->assertJsonPath('data.server.totals.bot_requests', 200)
        ->assertJsonPath('data.server.totals.human_requests', 300)
        ->assertJsonPath('data.server.totals.errors', 50)
        ->assertJsonPath('data.server.top_bots.0.label', 'Googlebot');
});

it('adds the same crawler up across the days of the range', function () {
    foreach ([1, 2] as $back) {
        ServerLogDay::query()->create([
            'date' => Carbon::yesterday()->subDays($back),
            'requests' => 10,
            'top_bots' => [['label' => 'Googlebot', 'value' => 5], ['label' => 'Bingbot', 'value' => 1]],
        ]);
    }

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/traffic?days=7'))
        ->assertSuccessful()
        ->assertJsonPath('data.server.top_bots.0', ['label' => 'Googlebot', 'value' => 10])
        ->assertJsonPath('data.server.top_bots.1', ['label' => 'Bingbot', 'value' => 2]);
});

it('says plainly that google is not connected instead of showing zeroes', function () {
    config(['services.ga4.property_id' => null, 'services.ga4.credentials' => null]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/traffic'))
        ->assertSuccessful()
        ->assertJsonPath('data.google.configured', false)
        ->assertJsonPath('data.google.has_data', false);
});

it('carries the breakdowns the nightly pull stored', function () {
    AnalyticsSummary::query()->create([
        'period' => AnalyticsSummary::CURRENT_PERIOD,
        'pulled_at' => now(),
        'top_pages' => [['label' => '/projects', 'value' => 44]],
        'channels' => [['label' => 'Organic Search', 'value' => 30]],
        'devices' => [['label' => 'mobile', 'value' => 51]],
        'cities' => [['label' => 'Jeddah', 'value' => 25]],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/traffic'))
        ->assertSuccessful()
        ->assertJsonPath('data.google.top_pages.0.label', '/projects')
        ->assertJsonPath('data.google.channels.0.value', 30)
        ->assertJsonPath('data.google.cities.0.label', 'Jeddah');
});
