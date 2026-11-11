<?php

namespace App\Services\Market;

use App\Models\MarketIndicatorFile;
use App\Models\MarketQuarter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Brings what the panel knows about published files up to date with the open
 * data portal: which exist, where each is downloaded from, and when its
 * publisher last replaced it.
 */
class CatalogueSync
{
    public function __construct(private readonly OpenDataCatalogue $catalogue) {}

    /**
     * @return array{published: int, waiting: int, indicators_waiting: int}
     *
     * @throws RuntimeException When the portal does not return the ministry's catalogue.
     */
    public function run(): array
    {
        $published = $this->catalogue->salesQuarters();

        // The deeds are what the screen stands on; the indicators are an
        // addition to it. A portal that answers for the first and not the
        // second has still told the panel most of what it asked.
        try {
            $indicators = $this->catalogue->indicatorFiles();
        } catch (Throwable) {
            $indicators = [];
        }

        $files = $this->catalogue->files([
            ...array_column($published, 'dataset_id'),
            ...array_column($indicators, 'dataset_id'),
        ]);

        foreach ($published as $period) {
            $this->note(
                MarketQuarter::query()->firstOrNew(['year' => $period['year'], 'quarter' => $period['quarter']]),
                $period['dataset_id'],
                $files,
            );
        }

        foreach ($indicators as $file) {
            $record = MarketIndicatorFile::query()->firstOrNew([
                'year' => $file['year'],
                'quarter' => $file['quarter'],
                'region_key' => PlaceName::key($file['region']),
            ]);
            $record->region ??= $file['region'];

            $this->note($record, $file['dataset_id'], $files);
        }

        return [
            'published' => count($published),
            'waiting' => MarketQuarter::query()->whereNull('imported_at')->count(),
            'indicators_waiting' => MarketIndicatorFile::query()->whereNull('imported_at')->count(),
        ];
    }

    /**
     * @param  MarketQuarter|MarketIndicatorFile  $record
     * @param  array<string, array{download_url: string, updated_at: string|null}>  $files
     */
    private function note(Model $record, string $datasetId, array $files): void
    {
        $record->dataset_id = $datasetId;

        // Left as they were when the portal did not answer for this one.
        if (isset($files[$datasetId])) {
            $record->download_url = $files[$datasetId]['download_url'];
            $record->file_updated_at = $this->moment($files[$datasetId]['updated_at']) ?? $record->file_updated_at;
        }

        $record->save();
    }

    /**
     * The portal writes its dates as «2026-08-06 14:12:03», in Riyadh time.
     */
    private function moment(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Asia/Riyadh')->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
