<?php

namespace App\Console\Commands;

use App\Exceptions\UnreadableSalesFile;
use App\Services\Market\ListingFileImporter;
use Illuminate\Console\Command;

class ImportMarketListings extends Command
{
    protected $signature = 'market:import-listings
                            {path : A CSV of property listings collected from listing sites}
                            {--kind= : The kind of property for a listing whose title does not say}';

    protected $description = 'Read listings collected from listing sites into the hand-entered market units';

    public function handle(ListingFileImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error('لا يوجد ملف في: '.$path);

            return self::FAILURE;
        }

        try {
            $report = $importer->import($path, $this->option('kind') ?: null);
        } catch (UnreadableSalesFile $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'أُضيف %d إعلانًا وحُدّث %d. تمثّل %d عقارًا مختلفًا، منها %d معروض في أكثر من إعلان.',
            $report['added'],
            $report['updated'],
            $report['properties'],
            $report['repeated'],
        ));

        foreach ($report['skipped'] as $skipped) {
            $this->warn(sprintf('السطر %d لم يُستورد: %s', $skipped['row'], $skipped['reason']));
        }

        if ($report['unknown_districts'] !== []) {
            $this->warn('أحياء ليست في بيانات السوق، فلن تُقارَن بها مشاريع تلك الأحياء: '.implode('، ', $report['unknown_districts']));
        }

        return self::SUCCESS;
    }
}
