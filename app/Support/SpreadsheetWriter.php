<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * A report as a real Excel workbook (.xlsx) rather than a CSV.
 *
 * A CSV opened in Excel loses what a report needs: amounts come in as text
 * that will not add up, leading zeros go from IDs, and a name with a comma
 * breaks into two columns. An .xlsx keeps numbers as numbers and text as
 * text. It is a zip of a few XML files, written here with the zip support PHP
 * already has, the same way SpreadsheetReader reads them.
 */
class SpreadsheetWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @return string  path of the written file (a temporary file)
     */
    public static function write(string $sheetName, array $headers, iterable $rows): string
    {
        return self::writeSheets([[$sheetName, $headers, $rows]]);
    }

    /**
     * A workbook of several sheets, one per [name, headers, rows].
     *
     * @param  list<array{0: string, 1: list<string>, 2: iterable<array<int, mixed>>}>  $sheets
     * @return string  path of the written file (a temporary file)
     */
    public static function writeSheets(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not build the Excel file.');
        }

        $overrides = $entries = $rels = '';
        foreach (array_values($sheets) as $n => [$sheetName, $headers, $rows]) {
            $zip->addFromString('xl/worksheets/sheet'.($n + 1).'.xml', self::sheet($headers, $rows));
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($n + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $entries .= '<sheet name="'.self::escape(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheetName), 0, 31)).'" sheetId="'.($n + 1).'" r:id="rId'.($n + 10).'"/>';
            $rels .= '<Relationship Id="rId'.($n + 10).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($n + 1).'.xml"/>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$entries.'</sheets>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        // Style 0 plain, 1 bold header on grey, 2 amounts with two decimals.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE5E7EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'</styleSheet>');

        $zip->close();

        return $path;
    }

    private static function sheet(array $headers, iterable $rows): string
    {
        $sheetRows = [self::row(1, $headers, true)];
        $widths = array_map(fn ($h) => mb_strlen((string) $h), $headers);
        $r = 2;
        foreach ($rows as $row) {
            $row = array_values((array) $row);
            foreach ($row as $i => $v) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $v));
            }
            $sheetRows[] = self::row($r++, $row, false);
        }

        $cols = '';
        foreach ($widths as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.min(60, max(8, $w + 2)).'" customWidth="1"/>';
        }
        $lastCol = self::column(max(0, count($headers) - 1));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'<autoFilter ref="A1:'.$lastCol.max(1, $r - 1).'"/>'
            .'</worksheet>';

    }

    /** A download response for the workbook; the temporary file goes afterwards. */
    public static function download(string $filename, string $sheetName, array $headers, iterable $rows)
    {
        return response()->download(self::write($sheetName, $headers, $rows), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @param  list<array{0: string, 1: list<string>, 2: iterable}>  $sheets */
    public static function downloadSheets(string $filename, array $sheets)
    {
        return response()->download(self::writeSheets($sheets), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private static function row(int $r, array $values, bool $header): string
    {
        $cells = '';
        foreach (array_values($values) as $i => $v) {
            $ref = self::column($i).$r;
            if ($v === null || $v === '') {
                continue;
            }
            // Numbers stay numbers - but not IDs with leading zeros, phone
            // numbers, or anything with a letter or a dash in it.
            if (! $header && (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?(0|[1-9]\d{0,14})(\.\d+)?$/', $v)))) {
                $isAmount = is_float($v) || (is_string($v) && str_contains($v, '.'));
                $cells .= '<c r="'.$ref.'"'.($isAmount ? ' s="2"' : '').'><v>'.$v.'</v></c>';
                continue;
            }
            $cells .= '<c r="'.$ref.'" t="inlineStr"'.($header ? ' s="1"' : '').'><is><t xml:space="preserve">'.self::escape((string) $v).'</t></is></c>';
        }

        return '<row r="'.$r.'">'.$cells.'</row>';
    }

    /** 0 to "A", 26 to "AA". */
    private static function column(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function escape(string $v): string
    {
        // Control characters are not allowed in the XML and would corrupt the file.
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
