<?php

namespace App\Services\Attendance;

use RuntimeException;

/**
 * Reads punches out of a file exported from the scanner's own software.
 *
 * The fallback for when the network pull cannot run - the device is off the
 * network, the server is somewhere else, or the pull is simply not working yet.
 * Every ZKTeco tool can export attendance, so this path always exists.
 *
 * Two shapes are handled, because ZKTeco's exports are not consistent:
 *
 *   - the tab-separated .dat the device writes, whose first two fields are the
 *     enrolment number and the timestamp
 *   - a CSV with a header row, where the columns are found by name
 *
 * Anything it cannot read is counted rather than guessed at: a wrong column
 * silently importing as a date would be worse than refusing the file.
 */
class PunchFileReader
{
    private const ID_HEADERS = ['biometric_id', 'user id', 'userid', 'user_id', 'enroll id',
                                'enrollid', 'enroll_id', 'employee id', 'ac-no', 'acno', 'no', 'pin'];

    private const TIME_HEADERS = ['timestamp', 'date/time', 'datetime', 'date_time', 'time',
                                  'punch time', 'checktime', 'check time', 'date'];

    /**
     * @return array{punches: array<int, array{biometric_id: string, timestamp: string}>, unreadable: int}
     */
    public function read(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('That file could not be opened.');
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('That file could not be opened.');
        }

        $punches = [];
        $unreadable = 0;
        $idColumn = null;
        $timeColumn = null;
        $firstRow = true;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);

                if (trim($line) === '') {
                    continue;
                }

                $fields = $this->split($line);

                if ($firstRow) {
                    $firstRow = false;

                    // A header row tells us which column is which; a .dat has
                    // none, and falls through to the positional reading below.
                    [$idColumn, $timeColumn] = $this->matchHeaders($fields);

                    if ($idColumn !== null && $timeColumn !== null) {
                        continue;
                    }
                }

                if ($idColumn !== null && $timeColumn !== null) {
                    $id = $fields[$idColumn] ?? '';
                    $stamp = $fields[$timeColumn] ?? '';
                } else {
                    // The device's own .dat: enrolment number, then timestamp.
                    // Some firmware writes "2020-06-11 08:01:00" as one field
                    // and some splits the date and time across two, so the
                    // single field is tried before the pair is joined.
                    $id = $fields[0] ?? '';
                    $stamp = trim((string) ($fields[1] ?? ''));

                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stamp)
                        && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', trim($fields[2] ?? ''))) {
                        $stamp = trim(($fields[1] ?? '').' '.($fields[2] ?? ''));
                    }
                }

                $id = trim((string) $id);
                $stamp = trim((string) $stamp);

                if ($id === '' || $stamp === '' || strtotime($stamp) === false) {
                    $unreadable++;
                    continue;
                }

                $punches[] = ['biometric_id' => $id, 'timestamp' => $stamp];
            }
        } finally {
            fclose($handle);
        }

        if (! $punches && $unreadable === 0) {
            throw new RuntimeException('That file had no rows in it.');
        }

        return ['punches' => $punches, 'unreadable' => $unreadable];
    }

    /**
     * @return array<int, string>
     */
    private function split(string $line): array
    {
        // Tabs first: a .dat is tab separated, and a name with a comma in it
        // would otherwise split a CSV row in the wrong place.
        if (str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line));
        }

        return array_map(fn ($f) => trim($f, " \"'"), str_getcsv($line));
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{0: int|null, 1: int|null}
     */
    private function matchHeaders(array $headers): array
    {
        $idColumn = null;
        $timeColumn = null;

        foreach ($headers as $i => $header) {
            $name = strtolower(trim($header));

            if ($idColumn === null && in_array($name, self::ID_HEADERS, true)) {
                $idColumn = $i;
            }

            if ($timeColumn === null && in_array($name, self::TIME_HEADERS, true)) {
                $timeColumn = $i;
            }
        }

        return [$idColumn, $timeColumn];
    }
}
