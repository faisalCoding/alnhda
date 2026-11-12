<?php

namespace App\Console\Commands;

use App\Services\Market\MarketFileImporter;
use App\Services\Market\UploadedFileArchive;
use Illuminate\Console\Command;
use Throwable;

class ReimportMarketFiles extends Command
{
    protected $signature = 'market:reimport';

    protected $description = 'Work the market figures out again from the copies kept of the files that were uploaded';

    public function handle(UploadedFileArchive $archive, MarketFileImporter $importer): int
    {
        $files = $archive->all();

        if ($files === []) {
            $this->warn('لا توجد نسخ محفوظة من الملفات المرفوعة. ما رُفع قبل بدء الحفظ يحتاج رفعه مرة أخرى.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($files as $file) {
            $path = null;

            try {
                $path = $archive->restore($file);
                $result = $importer->import($path, basename($file, '.gz'), keep: false);

                foreach ($result['periods'] as $period) {
                    $this->info(sprintf(
                        '%s%d الربع %d: %s صفقة',
                        $result['kind'] === MarketFileImporter::INDICATORS ? 'مؤشرات '.$period['region'].' ' : '',
                        $period['year'],
                        $period['quarter'],
                        number_format($period['deals']),
                    ));
                }
            } catch (Throwable $e) {
                $failed = true;
                $this->error($file.': '.$e->getMessage());
            } finally {
                if ($path !== null) {
                    @unlink($path);
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
