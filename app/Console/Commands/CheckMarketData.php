<?php

namespace App\Console\Commands;

use App\Services\Market\CatalogueSync;
use Illuminate\Console\Command;
use Throwable;

class CheckMarketData extends Command
{
    protected $signature = 'market:check';

    protected $description = 'Note which quarters of sale deeds the open data portal has published';

    public function handle(CatalogueSync $sync): int
    {
        try {
            $result = $sync->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('المنشور على المنصة %d ربعًا، منها %d لم يُرفع بعد.', $result['published'], $result['waiting']));

        return self::SUCCESS;
    }
}
