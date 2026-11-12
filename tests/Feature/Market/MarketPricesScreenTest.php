<?php

use App\Enums\AreaBand;
use App\Models\Admin;
use App\Models\MarketIndicatorFile;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Models\MarketTypeStat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

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
    $this->postJson(panelUrl('/api/market-prices/check'))->assertUnauthorized();
});

it('serves the market prices screen to an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get(panelUrl('/market-prices'))
        ->assertOk()
        ->assertSee('أسعار السوق')
        ->assertSee('marketPricesPage()', false)
        ->assertSee('وحدات السوق المضافة يدويًا')
        ->assertSee('عروض مماثلة')
        // The verdict can be measured against any of the three.
        ->assertSee('احسب الفرق عن')
        ->assertSee("setGapReference('kind')", false)
        ->assertSee("setGapReference('deeds')", false)
        ->assertSee("setGapReference('offers')", false);
});

it('lays the screen out as a board of groups, each opened in place', function () {
    $html = $this->actingAs($this->admin, 'admin')
        ->withoutVite()
        ->get(panelUrl('/market-prices'))
        ->assertOk()
        ->getContent();

    foreach (['kingdom' => 'المملكة', 'city' => 'المدينة وأحياؤها', 'deeds' => 'الصفقات الفردية', 'types' => 'حسب نوع العقار', 'units' => 'وحداتنا مقابل السوق', 'offers' => 'وحدات السوق المضافة يدويًا', 'files' => 'الملفات والتحديث'] as $group => $title) {
        expect($html)->toContain("go('{$group}')")
            ->and($html)->toContain($title)
            ->and($html)->toContain("tileFigure('{$group}')");
    }

    // Seven squares, each with its own icon, and one way back from any group.
    expect(substr_count($html, 'aspect-square'))->toBe(7)
        ->and(substr_count($html, "@click=\"go('')\""))->toBe(1)
        // Every group's content is on this one page, shown by the group that is open.
        ->and($html)->toContain("x-show=\"view === 'kingdom'\"")
        ->and($html)->toContain("x-show=\"view === 'city'\"")
        ->and($html)->toContain("x-show=\"view === 'deeds'\"")
        ->and($html)->toContain("x-show=\"view === 'types'\"")
        ->and($html)->toContain("x-show=\"view === 'units'\"")
        // The hand-entered units have a group of their own, not a corner of another.
        ->and($html)->toContain("x-show=\"view === 'offers'\"")
        ->and(substr_count($html, "view === 'offers'"))->toBe(1)
        ->and($html)->toContain("x-show=\"view === 'files'\"");
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

it('narrows every figure to one size of property', function () {
    $period = marketQuarter(2026, 1, median: 3200);
    MarketPriceStat::factory()->for($period, 'quarter')->city('جدة')->band(AreaBand::Under150)->create([
        'deals_count' => 60,
        'median_price_per_meter' => 3700,
        'lower_price_per_meter' => 3200,
        'upper_price_per_meter' => 4700,
        'median_price' => 445000,
    ]);
    MarketPriceStat::factory()->for($period, 'quarter')->district('الصفا')->create(['deals_count' => 90]);
    MarketPriceStat::factory()->for($period, 'quarter')->district('الصفا')->band(AreaBand::Under150)->create(['deals_count' => 30]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?band=under_150'))
        ->assertSuccessful()
        ->assertJsonPath('data.filters.band', 'under_150')
        ->assertJsonPath('data.bands.0', ['key' => 'under_150', 'label' => 'أقل من 150 م²'])
        ->assertJsonCount(count(AreaBand::cases()), 'data.bands')
        ->assertJsonPath('data.city.current.deals', 60)
        ->assertJsonPath('data.city.current.median', 3700)
        ->assertJsonPath('data.city.current.lower', 3200)
        ->assertJsonPath('data.city.current.upper', 4700)
        ->assertJsonPath('data.city.current.median_price', 445000)
        ->assertJsonPath('data.districts.0.deals', 30)
        // No row for the whole country in this band: nothing was counted.
        ->assertJsonPath('data.country.current', null);
});

it('shows every size when no band or an unknown one is asked for', function (string $query) {
    $period = marketQuarter(2026, 1, median: 3200);
    MarketPriceStat::factory()->for($period, 'quarter')->city('جدة')->band(AreaBand::Under150)->create(['median_price_per_meter' => 3700]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'.$query))
        ->assertSuccessful()
        ->assertJsonPath('data.filters.band', '')
        ->assertJsonPath('data.city.current.median', 3200)
        // The city is offered once, not once per band.
        ->assertJsonCount(1, 'data.cities');
})->with(['', '?band=huge']);

it('does not count band rows among the classifications', function () {
    $period = marketQuarter(2026, 1);
    MarketPriceStat::factory()->for($period, 'quarter')->classified('سكني')->create(['deals_count' => 10]);
    MarketPriceStat::factory()->for($period, 'quarter')->classified('سكني')->band(AreaBand::Under150)->create(['deals_count' => 900]);
    MarketPriceStat::factory()->for($period, 'quarter')->classified('تجاري')->create(['deals_count' => 50]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonPath('data.classifications', ['تجاري', 'سكني']);
});

it('says which quarters need their file again and where to get it', function () {
    MarketQuarter::factory()->outdated()->period(2025, 4)->create(['dataset_id' => 'q4-2025', 'download_url' => 'https://open.data.gov.sa/odp-public/q4.csv']);
    MarketQuarter::factory()->period(2026, 1)->create([
        'imported_at' => '2026-05-01 09:00:00',
        'file_updated_at' => '2026-05-06 10:41:12',
    ]);
    MarketQuarter::factory()->pending()->period(2026, 2)->create(['dataset_id' => 'q2-2026', 'download_url' => 'https://open.data.gov.sa/odp-public/q2.csv']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonPath('data.quarters.0.key', '2025-4')
        ->assertJsonPath('data.quarters.0.outdated', true)
        ->assertJsonPath('data.quarters.0.file_changed', false)
        ->assertJsonPath('data.quarters.0.download_url', 'https://open.data.gov.sa/odp-public/q4.csv')
        ->assertJsonPath('data.quarters.1.outdated', false)
        ->assertJsonPath('data.quarters.1.file_changed', true)
        ->assertJsonPath('data.waiting.0.download_url', 'https://open.data.gov.sa/odp-public/q2.csv')
        ->assertJsonPath('data.waiting.0.outdated', false);
});

it('asks the portal on demand and reports what is waiting', function () {
    Http::fake([
        'open.data.gov.sa/data/api/organizations*' => Http::response(['datasets' => [
            ['id' => 'q1-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الاول'],
            ['id' => 'q2-2026', 'titleAr' => 'الصفقات العقارية 2026 الربع الثاني'],
        ]]),
        'open.data.gov.sa/data/api/datasets/resources*' => Http::response(['resources' => []]),
    ]);
    MarketQuarter::factory()->period(2026, 1)->create();

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/market-prices/check'))
        ->assertSuccessful()
        ->assertJsonPath('data.published', 2)
        ->assertJsonPath('data.waiting', 1);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonPath('data.waiting.0.key', '2026-2');
});

it('says so when the portal cannot be reached', function () {
    Http::fake(['open.data.gov.sa/*' => Http::response('<html><title>Request Rejected</title></html>')]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(panelUrl('/api/market-prices/check'))
        ->assertStatus(502)
        ->assertJsonStructure(['message']);

    expect(MarketQuarter::query()->count())->toBe(0);
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

/**
 * One quarter of the authority's figures for a kind of property in Jeddah.
 */
function typeFigures(int $year, int $quarter, string $type, int $average, int $deals = 100, string $classification = 'سكني'): MarketTypeStat
{
    $file = MarketIndicatorFile::query()->where('year', $year)->where('quarter', $quarter)->first()
        ?? MarketIndicatorFile::factory()->period($year, $quarter)->create();

    return MarketTypeStat::factory()->for($file, 'file')->type($type, $classification)->create([
        'deals_count' => $deals,
        'average_price_per_meter' => $average,
        'lowest_price_per_meter' => $average - 1000,
        'highest_price_per_meter' => $average + 2000,
    ]);
}

it('lists what each kind of property sold for in the city', function () {
    marketQuarter(2026, 1);
    typeFigures(2025, 1, 'شقة', 3500);
    typeFigures(2026, 1, 'شقة', 3900, deals: 4000);
    typeFigures(2026, 1, 'فيلا', 3400, deals: 140);
    MarketTypeStat::factory()->for(MarketIndicatorFile::query()->where('year', 2026)->first(), 'file')->city('الطائف')->type('شقة')->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonPath('data.types.has_data', true)
        ->assertJsonPath('data.types.quarter', '2026-1')
        ->assertJsonPath('data.types.compared_with', '2025-1')
        ->assertJsonCount(2, 'data.types.rows')
        ->assertJsonPath('data.types.rows.0.type', 'شقة')
        ->assertJsonPath('data.types.rows.0.deals', 4000)
        ->assertJsonPath('data.types.rows.0.average', 3900)
        ->assertJsonPath('data.types.rows.0.lowest', 2900)
        ->assertJsonPath('data.types.rows.0.highest', 5900)
        ->assertJsonPath('data.types.rows.0.previous_average', 3500)
        ->assertJsonPath('data.types.rows.1.type', 'فيلا')
        ->assertJsonPath('data.types.rows.1.previous_average', null)
        ->assertJsonPath('data.types.selected', null)
        ->assertJsonPath('data.types.series', []);
});

it('shows the nearest quarter the authority has published, not a later one', function () {
    marketQuarter(2025, 4);
    marketQuarter(2026, 2);
    typeFigures(2025, 3, 'شقة', 3600);
    typeFigures(2026, 1, 'شقة', 3900);
    typeFigures(2026, 3, 'شقة', 4100);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonPath('data.filters.quarter', '2026-2')
        ->assertJsonPath('data.types.quarter', '2026-1');

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?quarter=2025-4'))
        ->assertJsonPath('data.types.quarter', '2025-3');
});

it('falls back to the earliest quarter the authority covers when the screen is on an older one', function () {
    marketQuarter(2020, 1);
    typeFigures(2024, 1, 'شقة', 3800);
    typeFigures(2026, 1, 'شقة', 3900);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonPath('data.types.quarter', '2024-1')
        ->assertJsonPath('data.types.rows.0.average', 3800);
});

it('merges the classifications of a kind of property, or narrows to one', function () {
    marketQuarter(2026, 1);
    typeFigures(2026, 1, 'أرض', 2000, deals: 300);
    typeFigures(2026, 1, 'أرض', 9000, deals: 100, classification: 'تجاري');
    MarketPriceStat::factory()->for(MarketQuarter::query()->first(), 'quarter')->classified('تجاري')->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonCount(1, 'data.types.rows')
        ->assertJsonPath('data.types.rows.0.deals', 400)
        // (300 × 2,000 + 100 × 9,000) ÷ 400.
        ->assertJsonPath('data.types.rows.0.average', 3750)
        ->assertJsonPath('data.types.rows.0.lowest', 1000)
        ->assertJsonPath('data.types.rows.0.highest', 11000);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?classification='.urlencode('تجاري')))
        ->assertJsonPath('data.types.rows.0.deals', 100)
        ->assertJsonPath('data.types.rows.0.average', 9000);
});

it('gives the history and the districts of a kind of property when one is opened', function () {
    marketQuarter(2026, 1);
    typeFigures(2025, 1, 'شقة', 3500);
    typeFigures(2025, 1, 'فيلا', 4100);
    $flats = typeFigures(2026, 1, 'شقة', 3900);
    MarketTypeStat::factory()->for($flats->file, 'file')->district('الصفا')->create(['deals_count' => 30, 'average_price_per_meter' => 4200]);
    MarketTypeStat::factory()->for($flats->file, 'file')->district('الواحة')->create(['deals_count' => 80, 'average_price_per_meter' => 3700]);
    MarketTypeStat::factory()->for($flats->file, 'file')->district('الصفا')->type('فيلا')->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?type='.urlencode('شقة')))
        ->assertSuccessful()
        ->assertJsonPath('data.types.selected', 'شقة')
        ->assertJsonPath('data.types.series.0.key', '2025-1')
        ->assertJsonPath('data.types.series.0.average', 3500)
        ->assertJsonPath('data.types.series.1.average', 3900)
        ->assertJsonPath('data.types.by_district', true)
        ->assertJsonCount(2, 'data.types.districts')
        ->assertJsonPath('data.types.districts.0.label', 'الواحة')
        ->assertJsonPath('data.types.districts.1.average', 4200);
});

it('draws a gap where a kind of property sold nothing in a quarter', function () {
    marketQuarter(2026, 1);
    typeFigures(2025, 4, 'شقة', 3700);
    typeFigures(2026, 1, 'شقة', 3900);
    typeFigures(2026, 1, 'دور', 5200, deals: 30);

    $series = $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?type='.urlencode('دور')))
        ->json('data.types.series');

    expect(array_column($series, 'key'))->toBe(['2025-4', '2026-1'])
        ->and(array_column($series, 'average'))->toBe([null, 5200])
        ->and($series[0]['deals'])->toBe(0);
});

it('says when the authority published a quarter by city only', function () {
    marketQuarter(2026, 1);
    typeFigures(2026, 1, 'شقة', 3900);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?type='.urlencode('شقة')))
        ->assertJsonPath('data.types.by_district', false)
        ->assertJsonPath('data.types.districts', []);
});

it('ignores a kind of property the city does not have', function () {
    marketQuarter(2026, 1);
    typeFigures(2026, 1, 'شقة', 3900);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?type='.urlencode('قصر')))
        ->assertSuccessful()
        ->assertJsonPath('data.types.selected', null)
        ->assertJsonPath('data.types.series', []);
});

it('lists the authority\'s files that are waiting or were replaced', function () {
    marketQuarter(2026, 1);
    MarketIndicatorFile::factory()->pending()->period(2026, 1)->create(['dataset_id' => 'makkah-q1-2026', 'download_url' => 'https://open.data.gov.sa/odp-public/makkah.csv']);
    MarketIndicatorFile::factory()->period(2025, 4)->create(['imported_at' => '2026-04-01 09:00:00', 'file_updated_at' => '2026-04-23 07:22:44']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonCount(1, 'data.types.waiting')
        ->assertJsonPath('data.types.waiting.0.key', '2026-1')
        ->assertJsonPath('data.types.waiting.0.region', 'مكة المكرمة')
        ->assertJsonPath('data.types.waiting.0.download_url', 'https://open.data.gov.sa/odp-public/makkah.csv')
        ->assertJsonCount(1, 'data.types.changed')
        ->assertJsonPath('data.types.changed.0.key', '2025-4')
        ->assertJsonPath('data.types.rows', []);
});

it('reads the authority\'s figures before any quarter of deeds has been uploaded', function () {
    typeFigures(2025, 4, 'شقة', 3700);
    typeFigures(2026, 1, 'شقة', 3900);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertSuccessful()
        ->assertJsonPath('data.has_data', false)
        ->assertJsonPath('data.city.label', 'جدة')
        ->assertJsonPath('data.types.has_data', true)
        ->assertJsonPath('data.types.quarter', '2026-1')
        ->assertJsonPath('data.types.rows.0.average', 3900);
});

it('takes an upload of the authority\'s indicators through the same door', function () {
    $csv = 'السنة,الربع,المنطقة,المدينة,النوع,الاستخدام,عدد الصفقات,مجموع قيم الصفقات ر.س,مجموع المساحاتM2,متوسط سعر المتر ر.س,أعلى متوسط سعر متر ر.س,أقل متوسط سعر متر ر.س'."\n"
        .'2026,1,مكة المكرمة,جدة,شقة,سكني,4307,2655087595,675646.1354,3876.00159,8089.89,1998.95'."\n";

    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/market-prices/import'), ['file' => marketCsv($csv, 'Sales transaction indicators in Makkah Q1 2026.csv')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'indicators')
        ->assertJsonPath('data.quarters.0.key', '2026-1')
        ->assertJsonPath('data.quarters.0.region', 'مكة المكرمة')
        ->assertJsonPath('data.quarters.0.deals', 4307);

    expect(MarketQuarter::query()->count())->toBe(0)
        ->and(MarketIndicatorFile::query()->imported()->count())->toBe(1);
});

it('counts an uploaded quarter and shows it straight away', function () {
    $csv = 'المنطقة,المدينة,المدينة / الحي,الرقم المرجعي للصفقة,تاريخ الصفقة ميلادي,تاريخ الصفقة هجري,تصنيف العقار,عدد العقارات,السعر,المساحة'."\n".'منطقة مكة المكرمه,جده,جده/ الصفا ,1,2026/01/15,1447/07/26,سكني,1,"1,500,000",500.00'."\n";

    $this->actingAs($this->admin, 'admin')
        ->post(panelUrl('/api/market-prices/import'), ['file' => marketCsv($csv, 'Transactions sale for real estate CSV.csv')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'sales')
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
