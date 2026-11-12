<?php

use App\Enums\MarketLevel;
use App\Models\Admin;
use App\Models\MarketDeed;
use App\Models\MarketIndicatorFile;
use App\Models\MarketPriceStat;
use App\Models\MarketQuarter;
use App\Models\MarketTypeStat;
use App\Services\Market\MarketFileImporter;
use App\Services\Market\UploadedFileArchive;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const ARCHIVE_SALES_HEADER = 'المنطقة,المدينة,المدينة / الحي,الرقم المرجعي للصفقة,تاريخ الصفقة ميلادي,تاريخ الصفقة هجري,تصنيف العقار,عدد العقارات,السعر,المساحة';
const ARCHIVE_INDICATORS_HEADER = 'السنة,الربع,المنطقة,المدينة,النوع,الاستخدام,عدد الصفقات,مجموع قيم الصفقات ر.س,مجموع المساحاتM2,متوسط سعر المتر ر.س,أعلى متوسط سعر متر ر.س,أقل متوسط سعر متر ر.س';

function archiveCsv(string $header, array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'archive').'.csv';
    file_put_contents($path, "\u{FEFF}".$header."\n".implode("\n", $rows)."\n");

    return $path;
}

function archivedSale(string $price, string $date = '2026/02/17'): string
{
    return archiveCsv(ARCHIVE_SALES_HEADER, ['منطقة مكة المكرمه,جده,جده/ الصفا ,1,'.$date.',1447/08/29,سكني,1,"'.$price.'",150.00']);
}

it('keeps a compressed copy of a sales file, exactly as it was uploaded', function () {
    $path = archivedSale('600,000');

    app(MarketFileImporter::class)->import($path, 'sales.csv');

    Storage::disk('local')->assertExists('market/sales/2026-1.csv.gz');

    expect(gzdecode(Storage::disk('local')->get('market/sales/2026-1.csv.gz')))->toBe(file_get_contents($path))
        ->and(app(UploadedFileArchive::class)->all())->toBe(['market/sales/2026-1.csv.gz']);
});

it('keeps an indicators file apart from the deeds, by region', function () {
    app(MarketFileImporter::class)->import(archiveCsv(ARCHIVE_INDICATORS_HEADER, [
        '2026,1,مكة المكرمة,جدة,شقة,سكني,10,5000000,1250,4000,4500,3500',
    ]));
    app(MarketFileImporter::class)->import(archiveCsv(ARCHIVE_INDICATORS_HEADER, [
        '2026,1,الرياض,الرياض,شقة,سكني,10,5000000,1250,4000,4500,3500',
    ]));

    $kept = app(UploadedFileArchive::class)->all();

    expect($kept)->toHaveCount(2)
        ->and($kept)->each->toStartWith('market/indicators/2026-1-')
        // A plain name whatever the region is called.
        ->and($kept)->each->toMatch('#^market/indicators/2026-1-[0-9a-f]{10}\.csv\.gz$#');
});

it('replaces the copy when a quarter is uploaded again', function () {
    app(MarketFileImporter::class)->import(archivedSale('600,000'));
    app(MarketFileImporter::class)->import(archivedSale('750,000'));

    expect(app(UploadedFileArchive::class)->all())->toHaveCount(1)
        ->and(gzdecode(Storage::disk('local')->get('market/sales/2026-1.csv.gz')))->toContain('750,000');
});

it('keeps nothing of a file it could not read', function () {
    expect(fn () => app(MarketFileImporter::class)->import(archiveCsv('الاسم,الهاتف', ['أحمد,0500000000'])))->toThrow(Exception::class);

    expect(app(UploadedFileArchive::class)->all())->toBe([]);
});

it('keeps the copy of a file uploaded in the panel', function () {
    $csv = "\u{FEFF}".ARCHIVE_SALES_HEADER."\n".'منطقة مكة المكرمه,جده,جده/ الصفا ,1,2026/01/15,1447/07/26,سكني,1,"1,500,000",500.00'."\n";

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(panelUrl('/api/market-prices/import'), ['file' => UploadedFile::fake()->createWithContent('sales.csv', $csv)], ['Accept' => 'application/json'])
        ->assertCreated();

    Storage::disk('local')->assertExists('market/sales/2026-1.csv.gz');
});

it('works the figures out again from the copies, with no file uploaded', function () {
    app(MarketFileImporter::class)->import(archivedSale('600,000'));
    app(MarketFileImporter::class)->import(archiveCsv(ARCHIVE_INDICATORS_HEADER, [
        '2026,1,مكة المكرمة,جدة,شقة,سكني,10,5000000,1250,4000,4500,3500',
    ]));

    // As if the figures had been written by an importer that knew less.
    MarketPriceStat::query()->delete();
    MarketDeed::query()->delete();
    MarketTypeStat::query()->delete();
    MarketQuarter::query()->update(['figures_version' => 1]);

    $this->artisan('market:reimport')
        ->expectsOutputToContain('2026 الربع 1')
        ->assertSuccessful();

    expect(MarketQuarter::query()->sole()->isOutdated())->toBeFalse()
        ->and(MarketPriceStat::query()->where('level', MarketLevel::Country)->exists())->toBeTrue()
        ->and(MarketDeed::query()->sole()->price)->toBe(600000)
        ->and(MarketTypeStat::query()->count())->toBe(1)
        ->and(MarketIndicatorFile::query()->sole()->isImported())->toBeTrue()
        // Reading a copy does not make another copy.
        ->and(app(UploadedFileArchive::class)->all())->toHaveCount(2);
});

it('reads the copies in the order they were uploaded', function () {
    $disk = Storage::disk('local');
    $disk->put('market/sales/2026-1.csv.gz', gzencode(file_get_contents(archivedSale('900,000', '2025/11/10'))));
    $disk->put('market/sales/2025-4.csv.gz', gzencode(file_get_contents(archivedSale('600,000', '2025/11/10'))));

    // The file kept under 2026-1 was uploaded first, though its name sorts last.
    touch($disk->path('market/sales/2026-1.csv.gz'), time() - 600);
    touch($disk->path('market/sales/2025-4.csv.gz'), time() - 60);

    expect(app(UploadedFileArchive::class)->all())->toBe(['market/sales/2026-1.csv.gz', 'market/sales/2025-4.csv.gz']);

    $this->artisan('market:reimport')->assertSuccessful();

    // Both cover the last quarter of 2025; the later upload has the last word.
    expect(MarketDeed::query()->sole()->price)->toBe(600000);
});

it('says so when there is nothing kept to read', function () {
    $this->artisan('market:reimport')
        ->expectsOutputToContain('لا توجد نسخ محفوظة')
        ->assertSuccessful();
});

it('reports a copy that is damaged and still reads the rest', function () {
    app(MarketFileImporter::class)->import(archivedSale('600,000'));
    Storage::disk('local')->put('market/sales/2025-4.csv.gz', 'not gzip at all');
    MarketDeed::query()->delete();

    $this->artisan('market:reimport')
        ->expectsOutputToContain('2025-4.csv.gz')
        ->assertFailed();

    expect(MarketDeed::query()->count())->toBe(1);
});
