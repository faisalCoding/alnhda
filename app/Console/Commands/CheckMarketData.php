<?php

namespace App\Console\Commands;

use App\Models\MarketQuarter;
use App\Services\Market\OpenDataCatalogue;
use Illuminate\Console\Command;
use Throwable;

class CheckMarketData extends Command
{
    protected $signature = 'market:check';

    protected $description = 'Note which quarters of sale deeds the open data portal has published';

    public function handle(OpenDataCatalogue $catalogue): int
    {
        try {
            $published = $catalogue->salesQuarters();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($published as $period) {
            MarketQuarter::query()
                ->firstOrNew(['year' => $period['year'], 'quarter' => $period['quarter']])
                ->fill(['dataset_id' => $period['dataset_id']])
                ->save();
        }

        $waiting = MarketQuarter::query()->whereNull('imported_at')->count();

        $this->info(sprintf('المنشور على المنصة %d ربعًا، منها %d لم يُرفع بعد.', count($published), $waiting));

        return self::SUCCESS;
    }
}
