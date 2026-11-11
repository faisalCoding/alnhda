<?php

namespace App\Services\Market;

use App\Models\MarketIndicatorFile;
use App\Models\MarketQuarter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Brings what the panel knows about published files up to date with the open
 * data portal: which exist, where each is downloaded from, and when its
 * publisher last replaced it.
 *
 * Reading the portal and recording what it said are two steps, because they
 * do not always happen on the same machine: the portal answers only from
 * inside the Kingdom, so the live server is told what a computer there read.
 */
class CatalogueSync
{
    private const CHECKED_AT = 'market.catalogue_checked_at';

    private const UNREACHABLE = 'market.portal_unreachable';

    public function __construct(private readonly OpenDataCatalogue $catalogue) {}

    /**
     * @return array{published: int, waiting: int, indicators_waiting: int}
     *
     * @throws RuntimeException When the portal does not return the ministry's catalogue.
     */
    public function run(): array
    {
        return $this->apply($this->gather());
    }

    /**
     * Reads the portal. Nothing is written.
     *
     * @return array{quarters: list<array{year: int, quarter: int, dataset_id: string, download_url: string|null, file_updated_at: string|null}>, indicator_files: list<array{year: int, quarter: int, region: string, dataset_id: string, download_url: string|null, file_updated_at: string|null}>}
     *
     * @throws RuntimeException When the portal does not return the ministry's catalogue.
     */
    public function gather(): array
    {
        try {
            $published = $this->catalogue->salesQuarters();
        } catch (Throwable $e) {
            Cache::forever(self::UNREACHABLE, true);

            throw $e;
        }

        Cache::forget(self::UNREACHABLE);

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

        $withFile = fn (array $entry): array => $entry + [
            'download_url' => $files[$entry['dataset_id']]['download_url'] ?? null,
            'file_updated_at' => $this->moment($files[$entry['dataset_id']]['updated_at'] ?? null)?->toISOString(),
        ];

        return [
            'quarters' => array_map($withFile, $published),
            'indicator_files' => array_map($withFile, $indicators),
        ];
    }

    /**
     * Records what the portal said, wherever it was read.
     *
     * @param  array{quarters?: list<array<string, mixed>>, indicator_files?: list<array<string, mixed>>}  $catalogue
     * @return array{published: int, waiting: int, indicators_waiting: int}
     */
    public function apply(array $catalogue): array
    {
        $quarters = $catalogue['quarters'] ?? [];

        foreach ($quarters as $entry) {
            $this->note(
                MarketQuarter::query()->firstOrNew(['year' => $entry['year'], 'quarter' => $entry['quarter']]),
                $entry,
            );
        }

        foreach ($catalogue['indicator_files'] ?? [] as $entry) {
            $record = MarketIndicatorFile::query()->firstOrNew([
                'year' => $entry['year'],
                'quarter' => $entry['quarter'],
                'region_key' => PlaceName::key($entry['region']),
            ]);
            $record->region ??= PlaceName::preferred(PlaceName::region($entry['region']));

            $this->note($record, $entry);
        }

        Cache::forever(self::CHECKED_AT, now()->toISOString());

        return [
            'published' => count($quarters),
            'waiting' => MarketQuarter::query()->whereNull('imported_at')->count(),
            'indicators_waiting' => MarketIndicatorFile::query()->whereNull('imported_at')->count(),
        ];
    }

    /**
     * Whether this machine's last attempt to read the portal failed. A server
     * outside the Kingdom fails every time, so the weekly check stops asking
     * once it has; asking by hand still tries, and clears this if it works.
     */
    public function portalUnreachable(): bool
    {
        return (bool) Cache::get(self::UNREACHABLE, false);
    }

    /**
     * When the portal's catalogue was last recorded here, by this machine or
     * by one that sent it.
     */
    public function lastCheckedAt(): ?string
    {
        return Cache::get(self::CHECKED_AT);
    }

    /**
     * @param  MarketQuarter|MarketIndicatorFile  $record
     * @param  array<string, mixed>  $entry
     */
    private function note(Model $record, array $entry): void
    {
        $record->dataset_id = $entry['dataset_id'];

        // Left as they were when the portal did not answer for this one.
        if (! empty($entry['download_url'])) {
            $record->download_url = $entry['download_url'];
            $record->file_updated_at = $this->moment($entry['file_updated_at'] ?? null) ?? $record->file_updated_at;
        }

        $record->save();
    }

    /**
     * The portal writes its dates as «2026-08-06 14:12:03», in Riyadh time; a
     * date that was already read arrives as ISO 8601 and keeps its own zone.
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
