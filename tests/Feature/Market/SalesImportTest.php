<?php

use App\Enums\MarketLevel;
use App\Exceptions\UnreadableSalesFile;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Services\Market\SalesImporter;

const SALES_HEADER = 'المنطقة,المدينة,المدينة / الحي,الرقم المرجعي للصفقة,تاريخ الصفقة ميلادي,تاريخ الصفقة هجري,تصنيف العقار,عدد العقارات,السعر,المساحة';

/**
 * Writes a sales file the way the portal serves it: UTF-8 behind a byte order
 * mark, figures with thousands separators in quotes.
 *
 * @param  list<string>  $rows
 */
function salesFile(array $rows, string $header = SALES_HEADER): string
{
    $path = tempnam(sys_get_temp_dir(), 'sales').'.csv';
    file_put_contents($path, "\u{FEFF}".$header."\n".implode("\n", $rows)."\n");

    return $path;
}

function saleRow(string $district, string $price, string $area, string $date = '2026/01/15', string $classification = 'سكني', string $city = 'جده', string $region = 'منطقة مكة المكرمه'): string
{
    return sprintf('%s,%s,%s/ %s ,1,%s,1447/07/26,%s,1,"%s","%s"', $region, $city, $city, $district, $date, $classification, $price, $area);
}

function marketStat(MarketLevel $level, string $classification = '', array $where = []): ?MarketPriceStat
{
    return MarketPriceStat::query()
        ->where('level', $level)
        ->where('classification', $classification)
        ->where($where)
        ->first();
}

it('counts each deed for its district, city, region and the country', function () {
    $quarters = app(SalesImporter::class)->import(salesFile([
        saleRow('الصفا', '1,000,000', '500.00'),
        saleRow('الصفا', '3,000,000', '500.00'),
        saleRow('الواحة', '900,000', '300.00'),
        saleRow('العليا', '4,000,000', '400.00', city: 'الرياض', region: 'منطقة الرياض'),
    ]), 'sales.csv');

    expect($quarters)->toHaveCount(1)
        ->and($quarters[0]->year)->toBe(2026)
        ->and($quarters[0]->quarter)->toBe(1)
        ->and($quarters[0]->deals_count)->toBe(4)
        ->and($quarters[0]->source_name)->toBe('sales.csv')
        ->and($quarters[0]->isImported())->toBeTrue();

    $country = marketStat(MarketLevel::Country);
    $jeddah = marketStat(MarketLevel::City, '', ['city_key' => 'جده']);
    $safa = marketStat(MarketLevel::District, '', ['district_key' => 'الصفا']);

    expect($country->deals_count)->toBe(4)
        ->and($country->total_price)->toBe(8900000)
        ->and($jeddah->deals_count)->toBe(3)
        ->and($jeddah->city)->toBe('جدة')
        ->and($jeddah->region)->toBe('مكة المكرمة')
        ->and($safa->deals_count)->toBe(2)
        ->and($safa->total_area)->toBe(1000.0)
        // 2,000 and 6,000 a metre: the median sits between them.
        ->and($safa->median_price_per_meter)->toBe(4000.0)
        ->and($safa->averagePricePerMeter())->toBe(4000.0)
        ->and(MarketPriceStat::query()->where('level', MarketLevel::Region)->where('classification', '')->count())->toBe(2);
});

it('takes the median, which one enormous plot does not move', function () {
    app(SalesImporter::class)->import(salesFile([
        saleRow('الصفا', '2,000,000', '500.00'),
        saleRow('الصفا', '2,100,000', '500.00'),
        saleRow('الصفا', '1,000,000', '1,000,000.00'),
    ]));

    $safa = marketStat(MarketLevel::District);

    expect($safa->median_price_per_meter)->toBe(4000.0)
        ->and($safa->averagePricePerMeter())->toBeLessThan(10);
});

it('keeps a row for each classification beside the one for all of them', function () {
    app(SalesImporter::class)->import(salesFile([
        saleRow('الصفا', '1,000,000', '500.00'),
        saleRow('الصفا', '9,000,000', '1,000.00', classification: 'تجاري'),
    ]));

    expect(marketStat(MarketLevel::City)->deals_count)->toBe(2)
        ->and(marketStat(MarketLevel::City, 'سكني')->median_price_per_meter)->toBe(2000.0)
        ->and(marketStat(MarketLevel::City, 'تجاري')->median_price_per_meter)->toBe(9000.0);
});

it('folds the spellings of one place into one row', function () {
    app(SalesImporter::class)->import(salesFile([
        saleRow('أم السلم', '500,000', '500.00'),
        saleRow('أم السلم', '500,000', '500.00'),
        saleRow('ام السلم', '500,000', '500.00'),
        saleRow('الصفا', '500,000', '500.00', region: 'منطقة مكة المكرمة'),
    ]));

    expect(MarketPriceStat::query()->where('level', MarketLevel::District)->where('classification', '')->count())->toBe(2)
        ->and(marketStat(MarketLevel::District, '', ['district_key' => 'ام السلم'])->deals_count)->toBe(3)
        // Shown the way it was written most often.
        ->and(marketStat(MarketLevel::District, '', ['district_key' => 'ام السلم'])->district)->toBe('أم السلم')
        ->and(MarketPriceStat::query()->where('level', MarketLevel::Region)->where('classification', '')->count())->toBe(1);
});

it('reads the layout the first quarter of 2023 was published in', function () {
    $header = 'رقم مرجعي,المنطقة,المدينة,الحي,المخطط,رقم القطعة,التاريخ,تصنيف العقار,نوع العقار,عدد العقارات,السعر بالريال السعودي,المساحة ,سعر المتر المربع';

    $quarters = app(SalesImporter::class)->import(salesFile([
        '18923186,منطقة مكة المكرمة,جده,حي/المروة,مخطط/أخرى, قطعة 505,2/13/2023,سكني,قطعة أرض,2,1500000,500.0,3000.0',
        '18923187,المنطقة الشرقية,الدمام,حي/الشاطئ,مخطط/أخرى, قطعة 12,3/31/2023,تجاري,قطعة أرض,1,800000,400.0,2000.0',
    ], $header));

    expect($quarters)->toHaveCount(1)
        ->and($quarters[0]->year)->toBe(2023)
        ->and($quarters[0]->quarter)->toBe(1)
        ->and(marketStat(MarketLevel::District, '', ['district_key' => 'المروه'])->median_price_per_meter)->toBe(3000.0)
        ->and(marketStat(MarketLevel::District, '', ['district_key' => 'المروه'])->properties_count)->toBe(2)
        ->and(marketStat(MarketLevel::Region, '', ['region_key' => 'الشرقيه'])->region)->toBe('الشرقية');
});

it('reads figures written without separators and with decimals', function () {
    app(SalesImporter::class)->import(salesFile([
        'منطقة الرياض,الرياض,الرياض/السويدى,19683341,2023/04/01,1444/09/10,تجاري,2,2500000.0,1000.0',
    ]));

    expect(marketStat(MarketLevel::Country)->total_price)->toBe(2500000)
        ->and(marketStat(MarketLevel::Country)->median_price_per_meter)->toBe(2500.0);
});

it('passes over rows that are not deeds and says how many', function () {
    $quarters = app(SalesImporter::class)->import(salesFile([
        saleRow('الصفا', '1,000,000', '500.00'),
        saleRow('الصفا', '', '500.00'),
        saleRow('الصفا', '1,000,000', '0'),
        saleRow('الصفا', '1,000,000', '500.00', date: 'غير معروف'),
        ',,,,,,,,,',
    ]));

    expect($quarters[0]->deals_count)->toBe(1)
        ->and($quarters[0]->skipped_rows)->toBe(4);
});

it('replaces a quarter when its file is uploaded again', function () {
    $importer = app(SalesImporter::class);

    $importer->import(salesFile([saleRow('الصفا', '1,000,000', '500.00'), saleRow('الواحة', '1,000,000', '500.00')]));
    $importer->import(salesFile([saleRow('الصفا', '2,000,000', '500.00')]));

    expect(MarketQuarter::query()->count())->toBe(1)
        ->and(MarketQuarter::query()->first()->deals_count)->toBe(1)
        ->and(MarketPriceStat::query()->where('level', MarketLevel::District)->where('classification', '')->count())->toBe(1)
        ->and(marketStat(MarketLevel::Country)->median_price_per_meter)->toBe(4000.0);
});

it('leaves other quarters alone', function () {
    $importer = app(SalesImporter::class);

    $importer->import(salesFile([saleRow('الصفا', '1,000,000', '500.00', date: '2025/11/02')]));
    $importer->import(salesFile([saleRow('الصفا', '2,000,000', '500.00')]));

    expect(MarketQuarter::query()->chronological()->get()->map->key()->all())->toBe(['2025-4', '2026-1'])
        ->and(MarketPriceStat::query()->where('level', MarketLevel::Country)->where('classification', '')->count())->toBe(2);
});

it('does not open a quarter for a stray date', function () {
    $rows = array_fill(0, 150, saleRow('الصفا', '1,000,000', '500.00'));
    $rows[] = saleRow('الصفا', '1,000,000', '500.00', date: '2062/01/15');

    $quarters = app(SalesImporter::class)->import(salesFile($rows));

    expect($quarters)->toHaveCount(1)
        ->and($quarters[0]->key())->toBe('2026-1')
        ->and($quarters[0]->skipped_rows)->toBe(1)
        ->and(MarketQuarter::query()->count())->toBe(1);
});

it('keeps what the catalogue already knew about a quarter', function () {
    MarketQuarter::factory()->pending()->period(2026, 1)->create(['dataset_id' => 'abc-123']);

    app(SalesImporter::class)->import(salesFile([saleRow('الصفا', '1,000,000', '500.00')]));

    expect(MarketQuarter::query()->count())->toBe(1)
        ->and(MarketQuarter::query()->first()->dataset_id)->toBe('abc-123')
        ->and(MarketQuarter::query()->first()->isImported())->toBeTrue();
});

it('refuses a file that is not the ministry\'s sales file', function (array $rows, string $header) {
    expect(fn () => app(SalesImporter::class)->import(salesFile($rows, $header)))
        ->toThrow(UnreadableSalesFile::class);

    expect(MarketQuarter::query()->count())->toBe(0);
})->with([
    'another spreadsheet' => [['أحمد,0500000000'], 'الاسم,الهاتف'],
    'no deeds in it' => [[], SALES_HEADER],
    'no price column' => [['منطقة الرياض,الرياض,الرياض/العليا,2026/01/01,سكني,500'], 'المنطقة,المدينة,المدينة / الحي,تاريخ الصفقة ميلادي,تصنيف العقار,المساحة'],
]);

it('refuses a file saved in another encoding', function () {
    $path = tempnam(sys_get_temp_dir(), 'sales').'.csv';
    file_put_contents($path, mb_convert_encoding(SALES_HEADER."\n", 'UTF-16LE', 'UTF-8'));

    expect(fn () => app(SalesImporter::class)->import($path))->toThrow(UnreadableSalesFile::class);
});

it('imports a file from the command line', function () {
    $path = salesFile([saleRow('الصفا', '1,000,000', '500.00')]);

    $this->artisan('market:import', ['path' => $path])
        ->expectsOutputToContain('2026 الربع 1')
        ->assertSuccessful();

    expect(MarketQuarter::query()->imported()->count())->toBe(1);
});

it('imports every file in a folder and reports the ones it could not read', function () {
    $folder = sys_get_temp_dir().'/sales-'.uniqid();
    mkdir($folder);
    rename(salesFile([saleRow('الصفا', '1,000,000', '500.00')]), $folder.'/a.csv');
    rename(salesFile([saleRow('الصفا', '1,000,000', '500.00', date: '2025/08/01')]), $folder.'/b.csv');
    rename(salesFile(['أحمد,0500000000'], 'الاسم,الهاتف'), $folder.'/c.csv');

    $this->artisan('market:import', ['path' => $folder])
        ->expectsOutputToContain('c.csv')
        ->assertFailed();

    expect(MarketQuarter::query()->imported()->count())->toBe(2);
});

it('says so when there is no file to import', function () {
    $this->artisan('market:import', ['path' => '/nowhere/sales.csv'])->assertFailed();
});
