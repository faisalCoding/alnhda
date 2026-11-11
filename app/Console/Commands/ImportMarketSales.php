<?php

namespace App\Console\Commands;

use App\Exceptions\UnreadableSalesFile;
use App\Services\Market\MarketFileImporter;
use Illuminate\Console\Command;

class ImportMarketSales extends Command
{
    protected $signature = 'market:import {path : A CSV from the open data portal, or a folder of them}';

    protected $description = 'Read Ministry of Justice sale deeds or authority sale indicators into the market prices screen';

    public function handle(MarketFileImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $files = is_dir($path) ? (glob(rtrim($path, '/').'/*.csv') ?: []) : [$path];

        if ($files === [] || ! is_file($files[0])) {
            $this->error('لا يوجد ملف CSV في: '.$path);

            return self::FAILURE;
        }

        sort($files);
        $failed = false;

        foreach ($files as $file) {
            try {
                $result = $importer->import($file, basename($file));

                foreach ($result['periods'] as $period) {
                    $this->info(sprintf(
                        '%s%d الربع %d: %s صفقة (%s)',
                        $result['kind'] === MarketFileImporter::INDICATORS ? 'مؤشرات '.$period['region'].' ' : '',
                        $period['year'],
                        $period['quarter'],
                        number_format($period['deals']),
                        basename($file),
                    ));
                }
            } catch (UnreadableSalesFile $e) {
                $failed = true;
                $this->error(basename($file).': '.$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
