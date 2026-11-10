<?php

namespace App\Console\Commands;

use App\Exceptions\UnreadableSalesFile;
use App\Services\Market\SalesImporter;
use Illuminate\Console\Command;

class ImportMarketSales extends Command
{
    protected $signature = 'market:import {path : A sales CSV from the open data portal, or a folder of them}';

    protected $description = 'Read quarters of Ministry of Justice sale deeds into the market prices screen';

    public function handle(SalesImporter $importer): int
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
                foreach ($importer->import($file, basename($file)) as $quarter) {
                    $this->info(sprintf(
                        '%d الربع %d: %s صفقة (%s)',
                        $quarter->year,
                        $quarter->quarter,
                        number_format($quarter->deals_count),
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
