<?php

namespace App\Services\Market;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Keeps a compressed copy of every market file that was read.
 *
 * The figures are worked out when a file is uploaded, so each time the
 * importer learned to keep something new, every quarter needed its file again
 * — and the portal hands those files only to a person, one download at a time.
 * With the files kept, the figures are worked out again from here instead.
 * These are public open data; nothing private is stored.
 */
class UploadedFileArchive
{
    private const FOLDER = 'market';

    /**
     * Keeps the file under the first period it covers. A later upload for the
     * same period replaces the copy, as it replaces the figures.
     *
     * @param  array{kind: string, periods: list<array{key: string, region: string|null}>}  $result
     */
    public function keep(string $path, array $result): void
    {
        $period = $result['periods'][0] ?? null;
        $contents = @file_get_contents($path);

        if ($period === null || $contents === false) {
            return;
        }

        // A region is written in Arabic; its hash keeps the file name plain.
        $name = $period['key'].($period['region'] === null ? '' : '-'.substr(sha1(PlaceName::key($period['region'])), 0, 10));

        $this->disk()->put(self::FOLDER.'/'.$result['kind'].'/'.$name.'.csv.gz', (string) gzencode($contents, 9));
    }

    /**
     * The kept files, oldest upload first: read again in that order, a file
     * that covered a quarter is still overridden by a later one that did too.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $disk = $this->disk();

        return collect($disk->allFiles(self::FOLDER))
            ->filter(fn (string $file): bool => str_ends_with($file, '.csv.gz'))
            ->sortBy(fn (string $file): array => [$disk->lastModified($file), $file])
            ->values()
            ->all();
    }

    /**
     * Writes a kept file out as the CSV it was, for the importer to read. The
     * caller deletes it when done.
     *
     * @throws RuntimeException
     */
    public function restore(string $file): string
    {
        $contents = @gzdecode((string) $this->disk()->get($file));

        if ($contents === false) {
            throw new RuntimeException('النسخة المحفوظة تالفة: '.$file);
        }

        $path = tempnam(sys_get_temp_dir(), 'market').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
