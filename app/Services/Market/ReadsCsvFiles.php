<?php

namespace App\Services\Market;

use App\Exceptions\UnreadableSalesFile;

/**
 * The plumbing the portal's CSV files share: UTF-8 behind a byte order mark,
 * and headings that have to be found by name because their order and spelling
 * change from one quarter to the next.
 */
trait ReadsCsvFiles
{
    /**
     * @return resource
     *
     * @throws UnreadableSalesFile
     */
    private function open(string $path)
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new UnreadableSalesFile('تعذّر فتح الملف.');
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     * @return list<string|null>|null
     */
    private function row($handle): ?array
    {
        $row = fgetcsv($handle, null, ',', '"', '');

        return $row === false ? null : $row;
    }

    /**
     * Where each heading sits, keyed by its name with spacing removed. The
     * first of two headings with the same name wins.
     *
     * @param  list<string|null>  $header
     * @return array<string, int>
     *
     * @throws UnreadableSalesFile
     */
    private function headings(array $header): array
    {
        $headings = [];

        foreach ($header as $position => $heading) {
            $heading = (string) $heading;

            if (! mb_check_encoding($heading, 'UTF-8')) {
                throw new UnreadableSalesFile('ترميز الملف ليس UTF-8. نزّل ملف CSV من منصة البيانات المفتوحة كما هو دون فتحه في Excel.');
            }

            $headings[$this->heading($heading)] ??= $position;
        }

        return $headings;
    }

    /**
     * A heading in the form it is matched on.
     */
    private function heading(string $heading): string
    {
        return (string) preg_replace('/[\s\x{FEFF}]+/u', '', $heading);
    }

    /**
     * A figure written with thousands separators, as «1,800,000». The files
     * write a missing figure as the word NULL.
     */
    private function number(string $value): ?float
    {
        $value = str_replace([',', ' ', "\u{00A0}"], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}
