<?php

use App\Models\Admin;
use App\Models\MarketDeed;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Services\Market\SalesImporter;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function deedsUrl(array $query = []): string
{
    return panelUrl('/api/market-prices/deeds'.($query === [] ? '' : '?'.http_build_query($query)));
}

/**
 * A quarter of Jeddah deeds: each entry is a district, an area, a price per
 * metre and a date.
 *
 * @param  list<array{0: string, 1: float, 2: float, 3: string}>  $deeds
 */
function deedQuarter(int $year, int $quarter, array $deeds, string $classification = 'سكني'): MarketQuarter
{
    $period = MarketQuarter::factory()->period($year, $quarter)->create();

    foreach ($deeds as [$district, $area, $rate, $date]) {
        MarketDeed::factory()->for($period, 'quarter')->in($district)->sold($area, $rate, $date)->create(['classification' => $classification]);
    }

    return $period;
}

it('keeps the deeds away from guests', function () {
    $this->getJson(deedsUrl())->assertUnauthorized();
});

it('keeps each deed of a city it follows, and none of any other', function () {
    $path = tempnam(sys_get_temp_dir(), 'sales').'.csv';
    file_put_contents($path, "\u{FEFF}".implode("\n", [
        'المنطقة,المدينة,المدينة / الحي,الرقم المرجعي للصفقة,تاريخ الصفقة ميلادي,تاريخ الصفقة هجري,تصنيف العقار,عدد العقارات,السعر,المساحة',
        'منطقة مكة المكرمه,جده,جده/ الصفا ,1,2026/02/17,1447/08/29,سكني,2,"600,000",150.00',
        'منطقة مكة المكرمه,جده,جده/ ام السلم ,2,2026/03/05,1447/09/16,تجاري,1,"900,000",300.00',
        'منطقة مكة المكرمه,جده,جده/ أم السلم ,3,2026/03/06,1447/09/17,تجاري,1,"900,000",300.00',
        'منطقة مكة المكرمه,جده,جده/ أم السلم ,4,2026/03/07,1447/09/18,تجاري,1,"900,000",300.00',
        'منطقة الرياض,الرياض,الرياض/العليا,5,2026/01/09,1447/07/20,سكني,1,"4,000,000",400.00',
    ])."\n");

    app(SalesImporter::class)->import($path);

    $deeds = MarketDeed::query()->orderBy('deed_date')->get();

    expect($deeds)->toHaveCount(4)
        ->and($deeds->pluck('city')->unique()->all())->toBe(['جدة'])
        ->and($deeds[0]->deed_date->toDateString())->toBe('2026-02-17')
        ->and($deeds[0]->district)->toBe('الصفا')
        ->and($deeds[0]->price)->toBe(600000)
        ->and($deeds[0]->area)->toBe(150.0)
        ->and($deeds[0]->price_per_meter)->toBe(4000.0)
        ->and($deeds[0]->properties_count)->toBe(2)
        // One district under one spelling, the one written most often.
        ->and($deeds->where('classification', 'تجاري')->pluck('district')->unique()->values()->all())->toBe(['أم السلم'])
        ->and($deeds->where('classification', 'تجاري')->pluck('district_key')->unique()->values()->all())->toBe(['ام السلم']);
});

it('replaces a quarter\'s deeds when its file is uploaded again', function () {
    $file = function (string $price): string {
        $path = tempnam(sys_get_temp_dir(), 'sales').'.csv';
        file_put_contents($path, "\u{FEFF}".'المنطقة,المدينة,المدينة / الحي,الرقم المرجعي للصفقة,تاريخ الصفقة ميلادي,تاريخ الصفقة هجري,تصنيف العقار,عدد العقارات,السعر,المساحة'
            ."\n".'منطقة مكة المكرمه,جده,جده/ الصفا ,1,2026/02/17,1447/08/29,سكني,1,"'.$price.'",150.00'."\n");

        return $path;
    };

    app(SalesImporter::class)->import($file('600,000'));
    app(SalesImporter::class)->import($file('750,000'));

    expect(MarketDeed::query()->count())->toBe(1)
        ->and(MarketDeed::query()->sole()->price)->toBe(750000)
        ->and(MarketQuarter::query()->sole()->figures_version)->toBe(MarketQuarter::FIGURES_VERSION);
});

it('lists the deeds of the city newest first, with what they add up to', function () {
    deedQuarter(2026, 1, [
        ['الصفا', 150, 3000, '2026-01-10'],
        ['الصفا', 150, 4000, '2026-02-10'],
        ['الواحة', 600, 2000, '2026-03-10'],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl())
        ->assertSuccessful()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.period.from', '2026-1')
        ->assertJsonPath('data.summary.deals', 3)
        ->assertJsonPath('data.summary.value', 450000 + 600000 + 1200000)
        ->assertJsonPath('data.summary.median', 3000)
        ->assertJsonPath('data.summary.lower', 2500)
        ->assertJsonPath('data.summary.upper', 3500)
        ->assertJsonPath('data.summary.median_price', 600000)
        ->assertJsonPath('data.summary.median_area', 150)
        ->assertJsonCount(3, 'data.deeds')
        ->assertJsonPath('data.deeds.0.date', '2026-03-10')
        ->assertJsonPath('data.deeds.0.district', 'الواحة')
        ->assertJsonPath('data.deeds.0.rate', 2000)
        ->assertJsonPath('data.deeds.2.date', '2026-01-10')
        ->assertJsonPath('data.districts.0', ['key' => 'الصفا', 'label' => 'الصفا', 'deals' => 2])
        ->assertJsonPath('data.pages', 1);
});

it('narrows the deeds to a district, a range of area and a range of price', function (array $query, int $deals) {
    deedQuarter(2026, 1, [
        ['الصفا', 120, 3000, '2026-01-10'],
        ['الصفا', 180, 4000, '2026-02-10'],
        ['الصفا', 700, 2000, '2026-02-11'],
        ['الواحة', 150, 3500, '2026-03-10'],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl($query))
        ->assertSuccessful()
        ->assertJsonPath('data.summary.deals', $deals)
        ->assertJsonCount($deals, 'data.deeds')
        // The districts on offer do not shrink to the one chosen.
        ->assertJsonCount(2, 'data.districts');
})->with([
    'a district' => [['district' => 'الصفا'], 3],
    'a district spelt another way' => [['district' => 'الواحه'], 1],
    'flats by size' => [['area_min' => 100, 'area_max' => 200], 3],
    'a size in one district' => [['district' => 'الصفا', 'area_min' => 100, 'area_max' => 200], 2],
    'a price' => [['price_min' => 500000, 'price_max' => 800000], 2],
    'nothing that large' => [['area_min' => 5000], 0],
]);

it('narrows the deeds to a classification', function () {
    deedQuarter(2026, 1, [['الصفا', 150, 3000, '2026-01-10']]);
    MarketDeed::factory()->for(MarketQuarter::query()->first(), 'quarter')->create(['classification' => 'تجاري']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl(['classification' => 'تجاري']))
        ->assertJsonPath('data.summary.deals', 1)
        ->assertJsonPath('data.deeds.0.classification', 'تجاري');
});

it('reads the latest four quarters unless asked for more', function () {
    foreach ([[2024, 4], [2025, 1], [2025, 2], [2025, 3], [2025, 4], [2026, 1]] as [$year, $quarter]) {
        deedQuarter($year, $quarter, [['الصفا', 150, 3000, sprintf('%d-%02d-10', $year, $quarter * 3 - 1)]]);
    }

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl())
        ->assertJsonPath('data.period.from', '2025-2')
        ->assertJsonPath('data.period.to', '2026-1')
        ->assertJsonPath('data.period.covered', 6)
        ->assertJsonPath('data.summary.deals', 4);

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl(['quarters' => 0]))
        ->assertJsonPath('data.period.from', '2024-4')
        ->assertJsonPath('data.summary.deals', 6);
});

it('follows the price month by month', function () {
    deedQuarter(2026, 1, [
        ['الصفا', 150, 3000, '2026-01-05'],
        ['الصفا', 150, 5000, '2026-01-25'],
        ['الصفا', 150, 4200, '2026-03-02'],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl())
        ->assertJsonCount(2, 'data.monthly')
        ->assertJsonPath('data.monthly.0', ['key' => '2026-01', 'deals' => 2, 'value' => 1200000, 'median' => 4000])
        ->assertJsonPath('data.monthly.1.key', '2026-03')
        ->assertJsonPath('data.monthly.1.median', 4200);
});

it('spreads the deeds across brackets of price', function () {
    deedQuarter(2026, 1, [
        ['الصفا', 100, 2000, '2026-01-05'],   // 200,000
        ['الصفا', 100, 2500, '2026-01-06'],   // 250,000: the bracket begins here
        ['الصفا', 150, 4000, '2026-01-07'],   // 600,000
        ['الصفا', 2000, 5000, '2026-01-08'],  // 10,000,000
    ]);

    $distribution = $this->actingAs($this->admin, 'admin')->getJson(deedsUrl())->json('data.distribution');

    expect($distribution)->toHaveCount(8)
        ->and($distribution[0])->toBe(['from' => 0, 'to' => 250000, 'deals' => 1])
        ->and($distribution[1])->toBe(['from' => 250000, 'to' => 500000, 'deals' => 1])
        ->and($distribution[2]['deals'])->toBe(1)
        ->and($distribution[7])->toBe(['from' => 5000000, 'to' => null, 'deals' => 1])
        ->and(array_sum(array_column($distribution, 'deals')))->toBe(4);
});

it('sorts and pages the deeds', function () {
    $period = MarketQuarter::factory()->period(2026, 1)->create();

    foreach (range(1, 60) as $step) {
        MarketDeed::factory()->for($period, 'quarter')->sold(100 + $step, 3000, '2026-01-15')->create();
    }

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl(['sort' => 'area', 'direction' => 'asc']))
        ->assertJsonPath('data.pages', 2)
        ->assertJsonCount(50, 'data.deeds')
        ->assertJsonPath('data.deeds.0.area', 101);

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl(['sort' => 'area', 'direction' => 'asc', 'page' => 2]))
        ->assertJsonPath('data.page', 2)
        ->assertJsonCount(10, 'data.deeds')
        ->assertJsonPath('data.deeds.9.area', 160);
});

it('has nothing to say about a city whose deeds are not kept', function () {
    deedQuarter(2026, 1, [['الصفا', 150, 3000, '2026-01-10']]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl(['city' => 'الرياض']))
        ->assertSuccessful()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.summary.deals', 0)
        ->assertJsonPath('data.summary.median', null)
        ->assertJsonPath('data.deeds', [])
        ->assertJsonPath('data.monthly', []);
});

it('refuses filters that are not filters', function (array $query, string $field) {
    $this->actingAs($this->admin, 'admin')
        ->getJson(deedsUrl($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    [['area_min' => 'كبير'], 'area_min'],
    [['price_max' => -5], 'price_max'],
    [['quarters' => 7], 'quarters'],
    [['sort' => 'id; drop table'], 'sort'],
    [['direction' => 'sideways'], 'direction'],
    [['page' => 0], 'page'],
]);

it('tells the screen whether the chosen city has deeds to show', function () {
    $period = MarketQuarter::factory()->period(2026, 1)->create();
    MarketPriceStat::factory()->for($period, 'quarter')->create();
    MarketDeed::factory()->for($period, 'quarter')->create();

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices'))
        ->assertJsonPath('data.deeds_available', true);

    $this->actingAs($this->admin, 'admin')
        ->getJson(panelUrl('/api/market-prices?city='.urlencode('الرياض')))
        ->assertJsonPath('data.deeds_available', false);
});
