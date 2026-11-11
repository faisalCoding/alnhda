<?php

use App\Enums\MarketLevel;
use App\Exceptions\UnreadableSalesFile;
use App\Models\MarketIndicatorFile;
use App\Models\MarketTypeStat;
use App\Services\Market\IndicatorImporter;
use App\Services\Market\MarketFileImporter;

/**
 * The three layouts the authority has published its sale indicators in.
 */
const INDICATORS_2024 = 'السنة,الربع,الربع ,رقم الربع ,المنطقة,المدينة,الحي,نوع العقار,عدد الصكوك ,مجموع سعر العقار,المساحة M2,متوسط سعر المتر,الحد الأعلى لسعر المتر,الحد الأدنى لسعر المتر ';
const INDICATORS_2025 = 'السنة,الربع,الربع ,المنطقة,المدينة,الحي,نوع العقار,تصنيف العقار,عدد الصكوك ,قيمة الصفقات,المساحة M2,متوسط سعر المتر,الحد الأعلى لسعر المتر,الحد الأدنى لسعر المتر ';
const INDICATORS_2026 = 'السنة,الربع,المنطقة,المدينة,النوع,الاستخدام,عدد الصفقات,مجموع قيم الصفقات ر.س,مجموع المساحاتM2,متوسط سعر المتر ر.س,أعلى متوسط سعر متر ر.س,أقل متوسط سعر متر ر.س';

/**
 * @param  list<string>  $rows
 */
function indicatorsFile(string $header, array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'indicators').'.csv';
    file_put_contents($path, "\u{FEFF}".$header."\n".implode("\n", $rows)."\n");

    return $path;
}

function typeStat(string $type, MarketLevel $level = MarketLevel::City, array $where = []): ?MarketTypeStat
{
    return MarketTypeStat::query()->where('level', $level)->where('property_type', $type)->where($where)->first();
}

it('reads a file published by city', function () {
    $files = app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2026, [
        '2026,1,مكة المكرمة,جدة,شقة,سكني,4307,2655087595,675646.1354,3876.00159,8089.89,1998.95',
        '2026,1,مكة المكرمة,جدة,أرض,سكني,3134,5508359961,5915470.717,2383.224481,11429.53,169.45',
        '2026,1,مكة المكرمة,الطائف,فيلا,سكني,65,56144941.91,20485.14,2578.23078,3311.85,1108.51',
    ]), 'Sales transaction indicators in Makkah Q1 2026.csv');

    expect($files)->toHaveCount(1)
        ->and($files[0]->key())->toBe('2026-1')
        ->and($files[0]->region)->toBe('مكة المكرمة')
        ->and($files[0]->deals_count)->toBe(7506)
        ->and($files[0]->isImported())->toBeTrue()
        ->and($files[0]->source_name)->toBe('Sales transaction indicators in Makkah Q1 2026.csv');

    $flats = typeStat('شقة');
    $land = typeStat('أرض');

    expect($flats->city)->toBe('جدة')
        ->and($flats->city_key)->toBe('جده')
        ->and($flats->classification)->toBe('سكني')
        ->and($flats->deals_count)->toBe(4307)
        ->and($flats->total_price)->toBe(2655087595)
        // The authority's own average, not what was paid over what was sold:
        // for land the two are 2,383 and 931.
        ->and($flats->average_price_per_meter)->toBe(3876.0)
        ->and($land->average_price_per_meter)->toBe(2383.22)
        ->and($land->lowest_price_per_meter)->toBe(169.45)
        ->and($land->highest_price_per_meter)->toBe(11429.53)
        ->and(MarketTypeStat::query()->where('level', MarketLevel::District)->count())->toBe(0);
});

it('keeps a file published by district at both levels', function () {
    app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2025, [
        '2025,1,الربع الأول ,مكة المكرمة,جدة,الصفا,شقة,سكني,30,18000000,4500,4000,4800,3300',
        '2025,1,الربع الأول ,مكة المكرمة,جدة,الواحة,شقة,سكني,10,5000000,1250,NULL,NULL,NULL',
        '2025,1,الربع الأول ,مكة المكرمة,جدة,الصفا,فيلا,سكني,2,6000000,1000,NULL,NULL,NULL',
    ]));

    $safa = typeStat('شقة', MarketLevel::District, ['district_key' => 'الصفا']);
    $waha = typeStat('شقة', MarketLevel::District, ['district_key' => 'الواحه']);
    $city = typeStat('شقة');

    expect(MarketTypeStat::query()->where('level', MarketLevel::District)->count())->toBe(3)
        ->and($safa->average_price_per_meter)->toBe(4000.0)
        // No average given for a row: what was paid over what was sold.
        ->and($waha->average_price_per_meter)->toBe(4000.0)
        ->and($waha->lowest_price_per_meter)->toBeNull()
        ->and($city->deals_count)->toBe(40)
        ->and($city->total_price)->toBe(23000000)
        ->and($city->average_price_per_meter)->toBe(4000.0)
        ->and($city->lowest_price_per_meter)->toBe(3300.0)
        ->and($city->highest_price_per_meter)->toBe(4800.0);
});

it('averages a city by the deeds behind each district, not by area', function () {
    app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2025, [
        '2025,1,الربع الأول ,مكة المكرمة,جدة,الصفا,أرض,سكني,90,270000000,90000,NULL,NULL,NULL',
        // One plot the size of a district, sold cheap by the metre.
        '2025,1,الربع الأول ,مكة المكرمة,جدة,خارج حدود الاحياء,أرض,سكني,10,100000000,1000000,NULL,NULL,NULL',
    ]));

    // (90 × 3,000 + 10 × 100) ÷ 100. Over area it would be 339.
    expect(typeStat('أرض')->average_price_per_meter)->toBe(2710.0);
});

it('leaves a row with an absurd price out of the city average but counts its deeds', function () {
    app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2025, [
        '2025,2,الربع الثاني ,مكة المكرمة,جدة,الصفا,فيلا,سكني,50,100000000,25000,4000,5200,3100',
        '2025,2,الربع الثاني ,مكة المكرمة,جدة,الواحة,فيلا,سكني,40,72000000,16000,4500,5600,3600',
        '2025,2,الربع الثاني ,مكة المكرمة,جدة,السلامة,فيلا,سكني,2,568046000,2,284023,306304.49,261741',
    ]));

    $villas = typeStat('فيلا');

    expect($villas->deals_count)->toBe(92)
        ->and($villas->average_price_per_meter)->toBe(4222.22)
        ->and($villas->highest_price_per_meter)->toBe(5600.0)
        // The district keeps what the authority published for it.
        ->and(typeStat('فيلا', MarketLevel::District, ['district_key' => 'السلامه'])->average_price_per_meter)->toBe(284023.0);
});

it('reads the 2024 layout, which names no classification', function () {
    $files = app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2024, [
        '2024,1,الربع الأول,20241,مكة المكرمة,جدة,أم السلم,شقة,63,38127519.15,11101.38,3353.456348,3736.53,2898.11',
        '2024,1,الربع الأول,20241,مكة المكرمة,جدة,البشائر,شقة,3,1866300,698.84,NULL,NULL,NULL',
    ]));

    expect($files[0]->key())->toBe('2024-1')
        ->and(typeStat('شقة')->classification)->toBe('')
        ->and(typeStat('شقة')->deals_count)->toBe(66);
});

it('reads land the same way whichever year wrote it', function () {
    app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2024, [
        '2024,1,الربع الأول,20241,مكة المكرمة,جدة,الصفا,قطعة أرض-سكنى,70,140000000,70000,2000,2600,1500',
        '2024,1,الربع الأول,20241,مكة المكرمة,جدة,الصفا,قطعة أرض-تجارى,5,45000000,5000,9000,9500,8000',
        '2024,1,الربع الأول,20241,مكة المكرمة,جدة,الصفا,قطعة أرض- زراعي,1,500000,5000,NULL,NULL,NULL',
        '2024,1,الربع الأول,20241,مكة المكرمة,جدة,الواحة,قطعة أرض,2,4000000,2000,NULL,NULL,NULL',
    ]));

    $land = MarketTypeStat::query()->where('level', MarketLevel::City)->where('property_type', 'أرض')->get();

    expect(MarketTypeStat::query()->pluck('property_type')->unique()->all())->toBe(['أرض'])
        ->and($land->pluck('deals_count', 'classification')->all())
        ->toBe(['سكني' => 70, 'تجاري' => 5, 'زراعي' => 1, '' => 2]);
});

it('merges the classifications of one kind of property only when asked to', function () {
    app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2026, [
        '2026,1,مكة المكرمة,جدة,أرض,سكني,100,200000000,100000,2000,3000,1000',
        '2026,1,مكة المكرمة,جدة,أرض,تجاري,10,90000000,10000,9000,12000,7000',
    ]));

    expect(MarketTypeStat::query()->where('property_type', 'أرض')->pluck('classification')->sort()->values()->all())
        ->toBe(['تجاري', 'سكني']);
});

it('passes over rows that sold nothing or name no property', function () {
    $files = app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2026, [
        '2026,1,مكة المكرمة,جدة,شقة,سكني,10,5000000,1250,4000,4500,3500',
        '2026,1,مكة المكرمة,جدة,فيلا,سكني,0,0,0,NULL,NULL,NULL',
        '2026,1,مكة المكرمة,جدة,,سكني,5,5000000,1250,4000,4500,3500',
        '2026,9,مكة المكرمة,جدة,دور,سكني,5,5000000,1250,4000,4500,3500',
        ',,,,,,,,,,,',
    ]));

    expect($files[0]->deals_count)->toBe(10)
        ->and(MarketTypeStat::query()->count())->toBe(1);
});

it('replaces a region\'s quarter when its file is uploaded again and leaves the others', function () {
    $importer = app(IndicatorImporter::class);

    $importer->import(indicatorsFile(INDICATORS_2026, ['2026,1,الرياض,الرياض,شقة,سكني,900,5000000,1250,4000,4500,3500']));
    $importer->import(indicatorsFile(INDICATORS_2026, [
        '2026,1,مكة المكرمة,جدة,شقة,سكني,10,5000000,1250,4000,4500,3500',
        '2026,1,مكة المكرمة,جدة,فيلا,سكني,10,5000000,1250,4000,4500,3500',
    ]));
    $importer->import(indicatorsFile(INDICATORS_2026, ['2026,1,مكة المكرمة,جدة,شقة,سكني,25,5000000,1250,4000,4500,3500']));

    expect(MarketIndicatorFile::query()->count())->toBe(2)
        ->and(MarketTypeStat::query()->where('city_key', 'جده')->count())->toBe(1)
        ->and(typeStat('شقة', where: ['city_key' => 'جده'])->deals_count)->toBe(25)
        ->and(typeStat('شقة', where: ['city_key' => 'الرياض'])->deals_count)->toBe(900);
});

it('keeps what the catalogue already knew about a file', function () {
    MarketIndicatorFile::factory()->pending()->period(2026, 1)->create(['dataset_id' => 'makkah-q1-2026']);

    app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2026, [
        '2026,1,منطقة مكة المكرمه,جدة,شقة,سكني,10,5000000,1250,4000,4500,3500',
    ]));

    expect(MarketIndicatorFile::query()->sole()->dataset_id)->toBe('makkah-q1-2026')
        ->and(MarketIndicatorFile::query()->sole()->isImported())->toBeTrue();
});

it('refuses a file with no indicators in it', function () {
    expect(fn () => app(IndicatorImporter::class)->import(indicatorsFile(INDICATORS_2026, [])))
        ->toThrow(UnreadableSalesFile::class);

    expect(fn () => app(IndicatorImporter::class)->import(indicatorsFile('الاسم,الهاتف', ['أحمد,0500000000'])))
        ->toThrow(UnreadableSalesFile::class);

    expect(MarketIndicatorFile::query()->count())->toBe(0);
});

it('tells the two kinds of file apart without being told', function () {
    $importer = app(MarketFileImporter::class);

    $indicators = $importer->import(indicatorsFile(INDICATORS_2026, ['2026,1,مكة المكرمة,جدة,شقة,سكني,10,5000000,1250,4000,4500,3500']));
    $sales = $importer->import(indicatorsFile(
        'المنطقة,المدينة,المدينة / الحي,الرقم المرجعي للصفقة,تاريخ الصفقة ميلادي,تاريخ الصفقة هجري,تصنيف العقار,نوع العقار,عدد العقارات,السعر,المساحة',
        ['منطقة الرياض,الرياض,الرياض/السويدى,19683341,2023/04/01,1444/09/10,تجاري,قطعة أرض,2,2500000.0,1000.0'],
    ));

    expect($indicators['kind'])->toBe(MarketFileImporter::INDICATORS)
        ->and($indicators['periods'][0])->toMatchArray(['key' => '2026-1', 'deals' => 10, 'region' => 'مكة المكرمة'])
        // A 2023 sales file names the kind of property too, and is still deeds.
        ->and($sales['kind'])->toBe(MarketFileImporter::SALES)
        ->and($sales['periods'][0])->toMatchArray(['key' => '2023-2', 'deals' => 1, 'region' => null]);
});

it('imports an indicators file from the command line', function () {
    $path = indicatorsFile(INDICATORS_2026, ['2026,1,مكة المكرمة,جدة,شقة,سكني,10,5000000,1250,4000,4500,3500']);

    $this->artisan('market:import', ['path' => $path])
        ->expectsOutputToContain('مؤشرات مكة المكرمة 2026 الربع 1')
        ->assertSuccessful();

    expect(MarketIndicatorFile::query()->imported()->count())->toBe(1);
});
