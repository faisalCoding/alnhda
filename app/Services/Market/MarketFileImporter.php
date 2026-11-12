<?php

namespace App\Services\Market;

use App\Exceptions\UnreadableSalesFile;
use App\Models\MarketIndicatorFile;
use App\Models\MarketQuarter;

/**
 * Takes whichever of the two kinds of file the panel reads and hands it to the
 * importer that understands it, so the person uploading never has to say which
 * one it is.
 */
class MarketFileImporter
{
    public const SALES = 'sales';

    public const INDICATORS = 'indicators';

    public function __construct(
        private readonly SalesImporter $sales,
        private readonly IndicatorImporter $indicators,
        private readonly IndicatorFileReader $indicatorReader,
        private readonly UploadedFileArchive $archive,
    ) {}

    /**
     * @param  bool  $keep  Whether to keep a copy for working the figures out again; false when reading such a copy.
     * @return array{kind: string, periods: list<array{key: string, year: int, quarter: int, deals: int, region: string|null}>}
     *
     * @throws UnreadableSalesFile
     */
    public function import(string $path, ?string $sourceName = null, bool $keep = true): array
    {
        $result = $this->read($path, $sourceName);

        if ($keep) {
            // The figures are in; a copy that could not be kept costs a
            // re-upload some day, not this import.
            rescue(fn () => $this->archive->keep($path, $result));
        }

        return $result;
    }

    /**
     * @return array{kind: string, periods: list<array{key: string, year: int, quarter: int, deals: int, region: string|null}>}
     *
     * @throws UnreadableSalesFile
     */
    private function read(string $path, ?string $sourceName): array
    {
        if ($this->indicatorReader->recognises($path)) {
            return [
                'kind' => self::INDICATORS,
                'periods' => array_map(fn (MarketIndicatorFile $file): array => [
                    'key' => $file->key(),
                    'year' => $file->year,
                    'quarter' => $file->quarter,
                    'deals' => $file->deals_count,
                    'region' => $file->region,
                ], $this->indicators->import($path, $sourceName)),
            ];
        }

        // Anything else is read as deeds, whose reader says what is missing.
        return [
            'kind' => self::SALES,
            'periods' => array_map(fn (MarketQuarter $quarter): array => [
                'key' => $quarter->key(),
                'year' => $quarter->year,
                'quarter' => $quarter->quarter,
                'deals' => $quarter->deals_count,
                'region' => null,
            ], $this->sales->import($path, $sourceName)),
        ];
    }
}
