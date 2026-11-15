<?php

namespace App\Services\Market;

use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

/**
 * Writes a table as an Excel workbook a person can open and read as it is:
 * a title over it, a coloured header that stays in view and can be filtered,
 * right to left, sums of money with their thousands, dates as dates and links
 * that open.
 *
 * An .xlsx file is a zip of XML parts. Only the parts Excel needs are
 * written, so nothing is installed for it.
 *
 * Columns are given as `['label' => …, 'type' => …, 'width' => …]`, where the
 * type is one of text, number, money, date or link. A row is its cells in the
 * same order, and may be marked to be shaded: a link cell is
 * `['url' => …, 'text' => …]`, a date cell a `Y-m-d` string.
 */
class ListingsWorkbook
{
    /** The header's colour: the panel's own green. */
    private const HEADER_COLOUR = 'FF3A7440';

    /** A marked row's shade. */
    private const MARKED_COLOUR = 'FFFEF3C7';

    /**
     * Cell styles by column type: plain, then marked.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const STYLES = [
        'text' => [2, 7],
        'number' => [3, 8],
        'money' => [4, 9],
        'date' => [5, 10],
        'link' => [6, 11],
    ];

    private const TITLE_STYLE = 12;

    private const HEADER_STYLE = 1;

    /**
     * @param  list<array{label: string, type: string, width: int|float}>  $columns
     * @param  iterable<array{cells: list<mixed>, marked?: bool}>  $rows
     */
    public function write(string $path, string $sheetName, string $title, array $columns, iterable $rows): void
    {
        $sheet = $this->sheet($title, $columns, $rows, $links);

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not write the workbook.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->packageRelations());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheetName, count($columns), $links['last_row']));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelations());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->sheetRelations($links['targets']));
        $zip->close();
    }

    /**
     * @param  list<array{label: string, type: string, width: int|float}>  $columns
     * @param  iterable<array{cells: list<mixed>, marked?: bool}>  $rows
     *
     * @param-out array{targets: list<string>, last_row: int} $links
     */
    private function sheet(string $title, array $columns, iterable $rows, ?array &$links): string
    {
        $last = $this->column(count($columns));
        $targets = [];
        $hyperlinks = [];

        $xml = '<sheetData>';
        $xml .= '<row r="1" ht="28" customHeight="1">'.$this->text('A1', $title, self::TITLE_STYLE).'</row>';
        $xml .= '<row r="2" ht="32" customHeight="1">';

        foreach ($columns as $index => $column) {
            $xml .= $this->text($this->column($index + 1).'2', $column['label'], self::HEADER_STYLE);
        }

        $xml .= '</row>';
        $number = 2;

        foreach ($rows as $row) {
            $number++;
            $marked = (int) ($row['marked'] ?? false);
            $xml .= '<row r="'.$number.'">';

            foreach ($columns as $index => $column) {
                $ref = $this->column($index + 1).$number;
                $value = $row['cells'][$index] ?? null;
                $style = self::STYLES[$column['type']][$marked];

                if ($value === null || $value === '' || ($column['type'] === 'link' && blank($value['url'] ?? null))) {
                    $xml .= '<c r="'.$ref.'" s="'.$style.'"/>';

                    continue;
                }

                $xml .= match ($column['type']) {
                    'number', 'money' => '<c r="'.$ref.'" s="'.$style.'"><v>'.(0 + $value).'</v></c>',
                    'date' => $this->date($ref, (string) $value, $style),
                    'link' => $this->text($ref, $value['text'] ?? $value['url'], $style),
                    default => $this->text($ref, (string) $value, $style),
                };

                if ($column['type'] === 'link') {
                    $targets[] = $this->uri($value['url']);
                    $hyperlinks[] = '<hyperlink ref="'.$ref.'" r:id="rId'.count($targets).'"/>';
                }
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        $links = ['targets' => $targets, 'last_row' => $number];

        $widths = '';

        foreach ($columns as $index => $column) {
            $widths .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$column['width'].'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView rightToLeft="1" workbookViewId="0">'
            .'<pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<cols>'.$widths.'</cols>'
            .$xml
            .'<autoFilter ref="A2:'.$last.max(2, $number).'"/>'
            .'<mergeCells count="1"><mergeCell ref="A1:'.$last.'1"/></mergeCells>'
            .($hyperlinks === [] ? '' : '<hyperlinks>'.implode('', $hyperlinks).'</hyperlinks>')
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function text(string $ref, string $value, int $style): string
    {
        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    /**
     * A day as Excel counts days, so it sorts and filters as a date.
     */
    private function date(string $ref, string $value, int $style): string
    {
        $day = Carbon::parse($value, 'UTC')->startOfDay();
        $serial = (int) Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC')->diffInDays($day);

        return '<c r="'.$ref.'" s="'.$style.'"><v>'.$serial.'</v></c>';
    }

    /**
     * A link as a workbook stores it: Arabic and spaces written out, the rest
     * as it was.
     */
    private function uri(string $url): string
    {
        return (string) preg_replace_callback('/[^\x21-\x7E]/u', fn (array $match): string => rawurlencode($match[0]), $url);
    }

    /**
     * The letters a column goes by: 1 is A, 27 is AA.
     */
    private function column(int $number): string
    {
        $letters = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $number = intdiv($number - 1, 26);
        }

        return $letters;
    }

    private function escape(string $value): string
    {
        // Characters XML 1.0 cannot carry at all are dropped.
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function packageRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(string $sheetName, int $columns, int $lastRow): string
    {
        $name = $this->escape(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':', "'"], ' ', $sheetName), 0, 31));
        $range = "'".$name."'!\$A\$2:\$".$this->column($columns).'$'.max(2, $lastRow);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView/></bookViews>'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">'.$range.'</definedName></definedNames>'
            .'</workbook>';
    }

    private function workbookRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  list<string>  $targets
     */
    private function sheetRelations(array $targets): string
    {
        $relations = '';

        foreach ($targets as $index => $target) {
            $relations .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.$this->escape($target).'" TargetMode="External"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'</Relationships>';
    }

    /**
     * Fonts: 0 plain, 1 the header's, 2 the title's, 3 a link's. Fills: 2 the
     * header's, 3 a marked row's. Formats: 164 thousands, 165 a day in the
     * Gregorian calendar, whatever the reader's language would show.
     * Cell styles follow STYLES: 1 header, 2–6 plain cells, 7–11 marked
     * ones, 12 the title.
     */
    private function styles(): string
    {
        $cell = fn (int $format, int $font, int $fill, string $align = '') => '<xf numFmtId="'.$format.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="1" xfId="0"'
            .($format > 0 ? ' applyNumberFormat="1"' : '').' applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            .'<alignment vertical="center"'.$align.'/></xf>';

        $kinds = fn (int $fill): string => $cell(0, 0, $fill, ' wrapText="1"')
            .$cell(0, 0, $fill, ' horizontal="center"')
            .$cell(164, 0, $fill, ' horizontal="center"')
            .$cell(165, 0, $fill, ' horizontal="center"')
            .$cell(0, 3, $fill);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0"/><numFmt numFmtId="165" formatCode="[$-409]yyyy-mm-dd"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Arial"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF305C35"/><name val="Arial"/><family val="2"/></font>'
            .'<font><u/><sz val="11"/><color rgb="FF1D4ED8"/><name val="Arial"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="'.self::HEADER_COLOUR.'"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="'.self::MARKED_COLOUR.'"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD4D4D8"/></left><right style="thin"><color rgb="FFD4D4D8"/></right><top style="thin"><color rgb="FFD4D4D8"/></top><bottom style="thin"><color rgb="FFD4D4D8"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="13">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .$cell(0, 1, 2, ' horizontal="center" wrapText="1"')
            .$kinds(0)
            .$kinds(3)
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
