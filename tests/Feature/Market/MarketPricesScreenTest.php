<?php

use App\Models\Admin;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

/**
 * A quarter with a figure for the country and for Jeddah, the two the screen
 * opens on.
 */
function marketQuarter(int $year, int $quarter, int $median = 3000, int $deals = 100): MarketQuarter
{
    $period = MarketQuarter::factory()->period($year, $quarter)->create(['deals_count' => $deals * 10]);

    MarketPriceStat::factory()->for($period, 'quarter')->create(['deals_count' => $deals * 10, 'median_price_per_meter' => $median / 2]);
    MarketPriceStat::factory()->for($period, 'quarter')->city('جدة')->create(['deals_count' => $deals, 'median_price_per_meter' => $median]);

    return $period;
}

function marketCsv(string $contents, string $name = 'sales.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "\u{FEFF}".$contents);
}

it('keeps the market prices api away from guests', function () {
    $this->getJson(panelUrl('/api/market-prices'))->assertUnauthorized();
    $this->postJson(panelUrl('/api/market-prices/import'))->assertUnauthorized();
});

it('serves the market prices screen to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get(panelUrl('/market-prices'))
        ->assertOk()
        ->assertSee('أسعار السوق')
        ->assertSee('marketPricesPage()', false);
});

it('explains itself before any quarter has been uploaded', function () {
    $waiting = MarketQuarter::factory()->pending()->period(2026, 2)->create(['dataset_id' => 'q2-2026']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonPath('data.has_data', false)
        ->assertJsonPath('data.quarters', [])
        ->assertJsonPath('data.waiting.0.key', '2026-2')
        ->assertJsonPath('data.waiting.0.portal_url', $waiting->portalUrl())
        ->assertJsonMissingPath('data.country');
});

it('opens on the newest quarter and on jeddah', function () {
    marketQuarter(2025, 4, median: 2800);
    marketQuarter(2026, 1, median: 3200);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonPath('data.has_data', true)
        ->assertJsonPath('data.filters.quarter', '2026-1')
        ->assertJsonPath('data.filters.city', 'جده')
        ->assertJsonPath('data.city.label', 'جدة')
        ->assertJsonPath('data.city.current.median', 3200)
        ->assertJsonPath('data.country.current.median', 1600)
        ->assertJsonPath('data.waiting', []);
});

it('compares a quarter with the same one a year earlier, not the one before it', function () {
    marketQuarter(2025, 1, median: 2000);
    marketQuarter(2025, 4, median: 9000);
    marketQuarter(2026, 1, median: 3000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonPath('data.filters.compared_with', '2025-1')
        ->assertJsonPath('data.city.previous.median', 2000);
});

it('compares with nothing when the year before was never uploaded', function () {
    marketQuarter(2025, 4);
    marketQuarter(2026, 1);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonPath('data.filters.compared_with', null)
        ->assertJsonPath('data.city.previous', null)
        ->assertJsonPath('data.country.previous', null);
});

it('shows the quarter that was asked for and falls back to the newest', function () {
    marketQuarter(2025, 4, median: 2800);
    marketQuarter(2026, 1, median: 3200);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?quarter=2025-4'))
        ->assertJsonPath('data.filters.quarter', '2025-4')
        ->assertJsonPath('data.city.current.median', 2800);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?quarter=1999-9'))
        ->assertJsonPath('data.filters.quarter', '2026-1');
});

it('draws one point per uploaded quarter, oldest first, with a gap where a place did not trade', function () {
    marketQuarter(2026, 1, median: 3200);
    MarketQuarter::factory()->period(2025, 3)->create();
    marketQuarter(2025, 2, median: 2600);
    // Published but not uploaded: no point for it.
    MarketQuarter::factory()->pending()->period(2026, 2)->create();

    $series = $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->json('data.city.series');

    expect(array_column($series, 'key'))->toBe(['2025-2', '2025-3', '2026-1'])
        ->and(array_column($series, 'median'))->toBe([2600, null, 3200])
        ->and($series[1]['deals'])->toBe(0);
});

it('narrows every figure to one classification', function () {
    $period = marketQuarter(2026, 1, median: 3200);
    MarketPriceStat::factory()->for($period, 'quarter')->classified('تجاري')->create(['deals_count' => 5]);
    MarketPriceStat::factory()->for($period, 'quarter')->classified('سكني')->create(['deals_count' => 50]);
    MarketPriceStat::factory()->for($period, 'quarter')->city('جدة')->classified('تجاري')->create(['median_price_per_meter' => 7000]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?classification='.urlencode('تجاري')))
        ->assertSuccessful()
        // Busiest first.
        ->assertJsonPath('data.classifications', ['سكني', 'تجاري'])
        ->assertJsonPath('data.filters.classification', 'تجاري')
        ->assertJsonPath('data.city.current.median', 7000);
});

it('shows every classification when asked for one no file carries', function () {
    marketQuarter(2026, 1, median: 3200);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?classification=فضائي'))
        ->assertSuccessful()
        ->assertJsonPath('data.filters.classification', '')
        ->assertJsonPath('data.city.current.median', 3200);
});

it('ranks the regions of the chosen quarter beside their median a year earlier', function () {
    $before = marketQuarter(2025, 1);
    $now = marketQuarter(2026, 1);

    MarketPriceStat::factory()->for($before, 'quarter')->region('الرياض')->create(['median_price_per_meter' => 1000]);
    MarketPriceStat::factory()->for($now, 'quarter')->region('الرياض')->create(['deals_count' => 900, 'median_price_per_meter' => 1500]);
    MarketPriceStat::factory()->for($now, 'quarter')->region('مكة المكرمة')->create(['deals_count' => 1200, 'median_price_per_meter' => 1100]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.regions')
        ->assertJsonPath('data.regions.0.label', 'مكة المكرمة')
        ->assertJsonPath('data.regions.0.previous_median', null)
        ->assertJsonPath('data.regions.1.label', 'الرياض')
        ->assertJsonPath('data.regions.1.median', 1500)
        ->assertJsonPath('data.regions.1.previous_median', 1000);
});

it('lists the districts of the chosen city only', function () {
    $before = marketQuarter(2025, 1);
    $now = marketQuarter(2026, 1);

    MarketPriceStat::factory()->for($before, 'quarter')->district('الصفا')->create(['median_price_per_meter' => 3000]);
    MarketPriceStat::factory()->for($now, 'quarter')->district('الصفا')->create(['deals_count' => 40, 'median_price_per_meter' => 3600]);
    MarketPriceStat::factory()->for($now, 'quarter')->district('الواحة')->create(['deals_count' => 90]);
    MarketPriceStat::factory()->for($now, 'quarter')->district('العليا', 'الرياض', 'الرياض')->create();
    MarketPriceStat::factory()->for($now, 'quarter')->city('الرياض', 'الرياض')->create(['deals_count' => 5000]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.districts')
        ->assertJsonPath('data.districts.0.label', 'الواحة')
        ->assertJsonPath('data.districts.1.label', 'الصفا')
        ->assertJsonPath('data.districts.1.previous_median', 3000)
        // Every city of the quarter is offered, busiest first.
        ->assertJsonPath('data.cities.0.label', 'الرياض')
        ->assertJsonPath('data.cities.1.label', 'جدة')
        ->assertJsonPath('data.district', null);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?city='.urlencode('الرياض')))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.districts')
        ->assertJsonPath('data.districts.0.label', 'العليا')
        ->assertJsonPath('data.city.label', 'الرياض');
});

it('finds a city however its name is spelt', function () {
    marketQuarter(2026, 1, median: 3200);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?city='.urlencode('جدة')))
        ->assertJsonPath('data.city.current.median', 3200);
});

it('gives the history of a district when one is opened', function () {
    $before = marketQuarter(2025, 4);
    $now = marketQuarter(2026, 1);

    MarketPriceStat::factory()->for($before, 'quarter')->district('الصفا')->create(['median_price_per_meter' => 3000]);
    MarketPriceStat::factory()->for($now, 'quarter')->district('الصفا')->create(['median_price_per_meter' => 3600]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?district='.urlencode('الصفا')))
        ->assertSuccessful()
        ->assertJsonPath('data.filters.district', 'الصفا')
        ->assertJsonPath('data.district.label', 'الصفا')
        ->assertJsonPath('data.district.series.0.median', 3000)
        ->assertJsonPath('data.district.series.1.median', 3600);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?district='.urlencode('لا وجود له')))
        ->assertSuccessful()
        ->assertJsonPath('data.district', null);
});

it('counts an uploaded quarter and shows it straight away', function () {
    $csv = 'المنطقة,المدينة,المدينة / الحي,الرقم المرجعي للصفقة,تاريخ الصفقة ميلادي,تاريخ الصفقة هجري,تصنيف العقار,عدد العقارات,السعر,المساحة'."\n".'منطقة مكة المكرمه,جده,جده/ الصفا ,1,2026/01/15,1447/07/26,سكني,1,"1,500,000",500.00'."\n";

    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/market-prices/import'), ['file' => marketCsv($csv, 'Transactions sale for real estate CSV.csv')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.quarters.0.key', '2026-1')
        ->assertJsonPath('data.quarters.0.deals', 1);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonPath('data.city.current.median', 3000)
        ->assertJsonPath('data.districts.0.label', 'الصفا');

    expect(MarketQuarter::query()->sole()->source_name)->toBe('Transactions sale for real estate CSV.csv');
});

it('turns away an upload that is not a sales file', function (Closure $file) {
    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/market-prices/import'), ['file' => $file()], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(MarketQuarter::query()->count())->toBe(0);
})->with([
    'the excel copy' => [fn () => UploadedFile::fake()->create('Transactions sale for real estate Excel.xlsx', 200)],
    'another csv' => [fn () => marketCsv("الاسم,الهاتف\nأحمد,0500000000\n", 'leads.csv')],
    'nothing' => [fn () => null],
]);
