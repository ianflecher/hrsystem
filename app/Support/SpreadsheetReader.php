<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * The first sheet of an .xlsx, or a .csv, as rows of plain strings.
 *
 * HR keeps schedules in Excel. An .xlsx is a zip of XML; reading the first
 * sheet's cells and the shared strings they point to is all a schedule needs,
 * without a spreadsheet library in the project for it.
 */
class SpreadsheetReader
{
    /** @return list<list<string>> */
    /**
     * @param  (callable(list<string>): ?string)|null  $pickSheet  given the sheet names, the one to read (null: the first)
     */
    public static function rows(string $path, ?string $originalName = null, ?callable $pickSheet = null): array
    {
        $extension = strtolower(pathinfo($originalName ?? $path, PATHINFO_EXTENSION));

        return $extension === 'xlsx' ? self::xlsx($path, $pickSheet) : self::csv($path);
    }

    /** @return list<list<string>> */
    private static function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('The file could not be opened.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn ($cell) => trim((string) $cell, " \t\r\n\xEF\xBB\xBF"), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<list<string>> */
    private static function xlsx(string $path, ?callable $pickSheet = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('That does not look like an Excel file. Save it as .xlsx or .csv and try again.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                // Plain text, or rich text split into runs.
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', iterator_to_array($si->xpath('.//*[local-name()="t"]'), false)));
            }
        }

        $sheetPath = self::firstSheet($zip, $pickSheet);
        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('The workbook has no sheet to read.');
        }

        $doc = simplexml_load_string($xml);
        $rows = [];
        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::column((string) $c['r']);
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    default => (string) ($c->v ?? ''),
                };
                $cells[$col] = trim($value);
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $rows[(int) $row['r'] - 1] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
            }
        }
        ksort($rows);

        return array_values($rows);
    }

    /** The workbook's first sheet, by the order Excel shows them - or the one $pickSheet names. */
    private static function firstSheet(ZipArchive $zip, ?callable $pickSheet = null): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook && $rels) {
            $wb = simplexml_load_string($workbook);
            $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $sheets = $wb->xpath('//m:sheets/m:sheet');
            $first = $sheets[0] ?? null;
            if ($pickSheet && ($name = $pickSheet(array_map(fn ($s) => (string) $s['name'], $sheets))) !== null) {
                foreach ($sheets as $sheet) {
                    if ((string) $sheet['name'] === $name) {
                        $first = $sheet;
                    }
                }
            }
            $rid = $first ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
            foreach (simplexml_load_string($rels)->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** "C7" to 2. */
    private static function column(string $ref): int
    {
        $letters = preg_replace('/\d/', '', $ref);
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
