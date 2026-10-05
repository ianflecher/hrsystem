<?php

namespace App\Services;

use App\Support\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A cutoff's schedule from HR's own spreadsheet.
 *
 * HR already plans each cutoff in Excel: names down the side, the days across
 * the top, and in each cell the shift ("8-5", "12NN-9PM"), RD, S for a
 * suspension, LEAVE, an event. This reads that sheet as it is kept, shows
 * what it understood, and only once HR confirms writes it where attendance
 * and payroll read it.
 *
 * Nothing it cannot read is guessed at: an unknown cell or a name that
 * matches nobody is listed for HR, and left alone.
 */
class ScheduleUpload
{
    /**
     * @param  list<list<string>>  $rows
     * @return array{period: array{start: string, end: string, label: string}, people: list<array>, unmatched: list<string>, unknown: list<string>, days: list<string>}
     */
    /** @param  list<int>|null  $onlyEmployeeIds  a team leader's upload: names are matched within the team */
    public function read(array $rows, PayPeriod $period, ?array $onlyEmployeeIds = null): array
    {
        [$headerAt, $dateColumns] = $this->findDates($rows, $period);
        if ($headerAt === null) {
            throw new \RuntimeException("No row of dates for {$period->label()} was found. The sheet needs the days of the cutoff across one row - day numbers like 1, 2, 3 or full dates.");
        }

        $firstDateColumn = min(array_keys($dateColumns));
        $staff = $this->staff();
        if ($onlyEmployeeIds !== null) {
            $staff = $staff->whereIn('employee_id', $onlyEmployeeIds)->values();
        }
        $people = [];
        $unmatched = [];
        $unknown = [];

        foreach (array_slice($rows, $headerAt + 1, null, true) as $r => $row) {
            $label = $this->rowLabel($row, $firstDateColumn);
            $cells = [];
            foreach ($dateColumns as $col => $date) {
                $raw = trim((string) ($row[$col] ?? ''));
                if ($raw !== '') {
                    $cells[$date] = $raw;
                }
            }
            if ($label === '' || ! $cells) {
                continue;
            }

            $employee = $this->match($row, $firstDateColumn, $staff);
            if (! $employee) {
                $unmatched[] = $label;
                continue;
            }

            $days = [];
            foreach ($cells as $date => $raw) {
                $action = self::understand($raw);
                if ($action['type'] === 'unknown') {
                    $unknown[] = "{$employee->full_name}, ".Carbon::parse($date)->format('M j').": \"{$raw}\"";
                    continue;
                }
                $days[$date] = $action;
            }

            $people[$employee->employee_id] = [
                'employee_id' => $employee->employee_id,
                'name' => $employee->full_name,
                'sheet_name' => $label,
                'days' => $days,
            ];
        }

        return [
            'period' => ['start' => $period->start, 'end' => $period->end, 'label' => $period->label()],
            'people' => array_values($people),
            'unmatched' => $unmatched,
            'unknown' => $unknown,
            'days' => array_values($dateColumns),
        ];
    }

    /**
     * One cell, in HR's words, into what it means.
     *
     * @return array{type: string, start?: string, end?: string, note?: string, raw?: string}
     */
    public static function understand(string $raw): array
    {
        $text = strtoupper(trim(preg_replace('/\s+/', ' ', $raw)));

        // Excel turns "8-5" typed in a cell into a date (5 August) and saves it
        // as the day number 46239. Read it back as month-day: 8-5.
        if (preg_match('/^\d{5}(\.0+)?$/', $text) && (int) $text > 30000 && (int) $text < 60000) {
            $day = Carbon::create(1899, 12, 30)->addDays((int) $text);
            $text = $day->month.'-'.$day->day;
        }

        if ($text === '') {
            return ['type' => 'blank'];
        }
        // What happened rather than what was planned - the attendance already says so:
        // an absence, or the times somebody actually came and went ("7:57 am 5:11 pm").
        if (preg_match('/^\d{1,2}[:;.]\d{2}\s*(AM|PM)\b/', $text) || preg_match('/^(ABSENT|AWOL|NO WORK|CONTRACT END|RESIGNED|TERMINATED)\b/', $text)
            || in_array($text, ['REGULAR HOLIDAY', 'SPECIAL HOLIDAY', 'HOLIDAY', 'LEGAL HOLIDAY'], true)) {
            return ['type' => 'blank'];
        }
        if (in_array($text, ['RD', 'REST', 'REST DAY', 'DAY OFF', 'OFF'], true)) {
            return ['type' => 'rest'];
        }
        if (in_array($text, ['S', 'SUSP', 'SUSPENSION', 'SUSPENDED'], true) || str_starts_with($text, 'SUSPENDED')) {
            return ['type' => 'suspension'];
        }
        if ($text === 'SCHOOL') {
            return ['type' => 'school'];
        }
        if (str_contains($text, 'LEAVE')) {
            return ['type' => preg_match('/WITH\s*PAY|W\/\s*PAY/', $text) && ! preg_match('/W\/\s*O|WITHOUT/', $text) ? 'leave_paid' : 'leave_unpaid'];
        }
        if (in_array($text, ['LWP', 'VL', 'SL'], true)) {
            return ['type' => 'leave_paid'];
        }
        if ($text === 'OB' || str_contains($text, 'EVENT') || str_starts_with($text, 'OB ')) {
            return ['type' => 'ob', 'note' => trim($raw)];
        }
        if ($shift = self::times($text)) {
            return ['type' => 'shift'] + $shift;
        }
        // A time with a note after it: "8am-7pm (2hrs OT)", "HALFDAY 1-5PM", "9-3pm undertime".
        $clean = trim(preg_replace('/^HALF\s*DAY\s*/', '', $text));
        if (preg_match('/^(\d{1,2}(?::\d{2})?\s*(?:AM|PM|NN|N|MN)?\s*(?:-|–|TO)\s*\d{1,2}(?::\d{2})?\s*(?:AM|PM|NN|N|MN)?)(?![\d:])/', $clean, $m)
            && ($shift = self::times(trim($m[1])))) {
            return ['type' => 'shift'] + $shift;
        }

        return ['type' => 'unknown', 'raw' => $raw];
    }

    /**
     * "8-5", "10-7", "12NN-9PM", "1PM-10PM", "7PM-7AM", "19:00-07:00".
     * Without AM/PM, the way the sheets are written: a start from 7 to 11 is
     * morning, 12 is noon, 1 to 6 is afternoon; an end earlier than the start
     * is in the afternoon or evening, and past that, the next morning.
     *
     * @return array{start: string, end: string}|null
     */
    public static function times(string $text): ?array
    {
        if (! preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM|NN|N|MN)?\s*(?:-|–|TO)\s*(\d{1,2})(?::(\d{2}))?\s*(AM|PM|NN|N|MN)?$/', $text, $m)) {
            return null;
        }

        $hour = function (int $h, string $suffix, ?int $after = null): ?int {
            if ($h > 24) {
                return null;
            }
            if ($suffix === 'AM') return $h % 12;
            if ($suffix === 'PM') return $h % 12 + 12;
            if (in_array($suffix, ['NN', 'N'], true)) return 12;
            if ($suffix === 'MN') return 0;
            if ($h >= 13) return $h % 24;
            if ($after === null) {
                return $h === 12 ? 12 : ($h <= 6 ? $h + 12 : $h);
            }

            return $h * 60 <= $after ? ($h + 12) % 24 : $h;
        };

        $startMinute = (int) ($m[2] ?? 0);
        $endMinute = (int) ($m[5] ?? 0);

        // "19:00-07:00": written on the 24-hour clock, taken as written.
        if (($m[2] ?? '') !== '' && ($m[5] ?? '') !== '' && ($m[3] ?? '') === '' && ($m[6] ?? '') === '') {
            if ((int) $m[1] > 23 || (int) $m[4] > 23) {
                return null;
            }

            return ['start' => sprintf('%02d:%02d', $m[1], $startMinute), 'end' => sprintf('%02d:%02d', $m[4], $endMinute)];
        }
        $start = $hour((int) $m[1], $m[3] ?? '');
        if ($start === null) {
            return null;
        }
        // "6-3": an early start ending on a smaller number is a morning shift
        // (6AM-3PM), not an evening one running into the next afternoon.
        if (($m[3] ?? '') === '' && ($m[6] ?? '') === '' && (int) $m[1] <= 6 && (int) $m[4] < (int) $m[1]) {
            $start = (int) $m[1];
        }
        $end = $hour((int) $m[4], $m[6] ?? '', $start * 60 + $startMinute);
        if ($end === null) {
            return null;
        }

        return ['start' => sprintf('%02d:%02d', $start, $startMinute), 'end' => sprintf('%02d:%02d', $end, $endMinute)];
    }

    /**
     * The sheet of a workbook that holds a cutoff: named for its month ("SEPT
     * 1-30 SCHEDULE", "OCT 1-15"), the half of the month when the name says.
     * Null when no name looks like it, so the first sheet is read.
     *
     * @param  list<string>  $names
     */
    /**
     * The months a sheet says it is for, from its first rows: a full date
     * (an Excel date like 10/1/2026) or a month's name ("OCTOBER 1-15").
     * Empty when it does not say - day numbers alone name no month.
     *
     * @return list<string>  "2026-10"
     */
    public static function sheetMonths(array $rows, int $year): array
    {
        $months = [];
        $names = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        foreach (array_slice($rows, 0, 6) as $row) {
            foreach ($row as $cell) {
                $cell = strtoupper(trim((string) $cell));
                if (preg_match('/^\d{5}(\.0+)?$/', $cell) && (int) $cell > 40000 && (int) $cell < 60000) {
                    $months[] = Carbon::create(1899, 12, 30)->addDays((int) $cell)->format('Y-m');
                } elseif (preg_match('/\b('.implode('|', $names).')[A-Z]*\.?\s*(\d{4})?/', $cell, $m) && ! preg_match('/^(MON|TUE|WED|THU|FRI|SAT|SUN)/', $cell)) {
                    $y = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $year;
                    $months[] = sprintf('%04d-%02d', $y, array_search($m[1], $names, true) + 1);
                }
            }
        }

        return array_values(array_unique($months));
    }

    public static function pickSheet(array $names, PayPeriod $period): ?string
    {
        $start = Carbon::parse($period->start);
        $month = strtoupper($start->format('M'));
        $best = null;
        $bestScore = 0;
        foreach ($names as $name) {
            $n = strtoupper($name);
            if (! preg_match('/\b'.$month.'/', $n)) continue;
            $score = 10 + (str_contains($n, (string) $start->year) ? 1 : 0);
            if (preg_match('/\b1\s*-\s*15\b/', $n)) $score += $period->isSecondCutoff ? -20 : 3;
            if (preg_match('/\b16\s*-\s*3\d\b/', $n)) $score += $period->isSecondCutoff ? 3 : -20;
            if (str_contains($n, 'SCHED')) $score += 1;
            if ($score > $bestScore) {
                $best = $name;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Writes a confirmed plan. Returns what it did, for the message HR sees.
     *
     * @return array<string, int>
     */
    public function apply(array $plan, ?int $byUserId): array
    {
        $done = ['shifts' => 0, 'rest' => 0, 'suspensions' => 0, 'leave' => 0, 'ob' => 0, 'kept_scans' => 0];

        DB::transaction(function () use ($plan, $byUserId, &$done) {
            foreach ($plan['people'] as $person) {
                $id = (int) $person['employee_id'];
                $leaveDays = ['leave_paid' => [], 'leave_unpaid' => []];

                foreach ($person['days'] as $date => $day) {
                    switch ($day['type']) {
                        case 'shift':
                        case 'rest':
                        case 'school':
                            $rest = $day['type'] !== 'shift';
                            DB::table('shift_assignments')->updateOrInsert(
                                ['employee_id' => $id, 'work_date' => $date],
                                ['starts_at' => $rest ? null : $day['start'], 'ends_at' => $rest ? null : $day['end'],
                                    'rest_day' => $rest, 'label' => match ($day['type']) { 'shift' => 'Assigned shift', 'rest' => 'Rest day', 'school' => 'School' },
                                    'status' => 'approved', 'created_by' => $byUserId, 'approved_by' => $byUserId, 'approved_at' => now(),
                                    'created_at' => now(), 'updated_at' => now()]
                            );
                            $done[$rest ? 'rest' : 'shifts']++;
                            break;

                        case 'suspension':
                            // A working day not worked, and not paid.
                            DB::table('shift_assignments')->where('employee_id', $id)->whereDate('work_date', $date)->where('rest_day', true)->delete();
                            $row = DB::table('hr_attendance')->where('employee_id', $id)->whereDate('date', $date)->first();
                            if ($row && $row->time_in) {
                                $done['kept_scans']++;
                                break;
                            }
                            $this->attendance($id, $date, $row, ['status' => 'absent', 'notes' => 'Suspension']);
                            $done['suspensions']++;
                            break;

                        case 'ob':
                            $row = DB::table('hr_attendance')->where('employee_id', $id)->whereDate('date', $date)->first();
                            $this->attendance($id, $date, $row, ['status' => 'official_business', 'notes' => 'Official business: '.$day['note']]);
                            $done['ob']++;
                            break;

                        case 'leave_paid':
                        case 'leave_unpaid':
                            $leaveDays[$day['type']][] = $date;
                            break;
                    }
                }

                // Consecutive days of the same leave are one request.
                foreach ($leaveDays as $type => $dates) {
                    foreach ($this->runs($dates) as [$from, $to]) {
                        $covered = DB::table('leaves')->where('employee_id', $id)->where('status', 'approved')
                            ->where('start_date', '<=', $from)->where('end_date', '>=', $to)->exists();
                        if ($covered) {
                            continue;
                        }
                        // "Leave with pay" is paid only while the yearly balance lasts; past it, unpaid.
                        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
                        $paid = $type === 'leave_paid'
                            ? (int) (new LeaveBalances)->paidDaysFor($id, 'vacation', (float) $days, (int) substr($from, 0, 4)) : 0;
                        $parts = [];
                        if ($paid > 0) $parts[] = ['paid', $from, Carbon::parse($from)->addDays($paid - 1)->toDateString(), $paid];
                        if ($paid < $days) $parts[] = ['unpaid', Carbon::parse($from)->addDays($paid)->toDateString(), $to, $days - $paid];
                        foreach ($parts as [$pay, $a, $b, $n]) {
                            DB::table('leaves')->insert([
                                'employee_id' => $id, 'leave_type' => $pay === 'paid' ? 'vacation' : 'unpaid',
                                'pay_status' => $pay,
                                'start_date' => $a, 'end_date' => $b, 'total_days' => $n,
                                'reason' => ($pay === 'paid' ? 'Leave with pay' : ($type === 'leave_paid' ? 'Leave with pay - no paid balance left, so unpaid' : 'Leave without pay'))." (from the {$plan['period']['label']} schedule)",
                                'status' => 'approved', 'approved_by' => $byUserId, 'approved_at' => now(),
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $done['leave']++;
                        }
                    }
                }
            }
        });

        Auditor::record('create', 'shift_assignments', 0, null,
            ['schedule_upload' => $plan['period']['label'], 'people' => count($plan['people'])] + $done);

        return $done;
    }

    private function attendance(int $id, string $date, ?object $row, array $values): void
    {
        if ($row) {
            DB::table('hr_attendance')->where('attendance_id', $row->attendance_id)->update($values + ['updated_at' => now()]);
        } else {
            DB::table('hr_attendance')->insert($values + ['employee_id' => $id, 'date' => $date, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @return list<array{0: string, 1: string}> */
    private function runs(array $dates): array
    {
        sort($dates);
        $runs = [];
        foreach ($dates as $date) {
            $last = count($runs) - 1;
            if ($last >= 0 && Carbon::parse($runs[$last][1])->addDay()->toDateString() === $date) {
                $runs[$last][1] = $date;
            } else {
                $runs[] = [$date, $date];
            }
        }

        return $runs;
    }

    /**
     * The row holding the cutoff's days, and which column is which date. Day
     * numbers, Excel's own date numbers and written dates all count; the row
     * with the most of the cutoff's days wins.
     *
     * @return array{0: ?int, 1: array<int, string>}
     */
    private function findDates(array $rows, PayPeriod $period): array
    {
        $start = Carbon::parse($period->start);
        $end = Carbon::parse($period->end);
        $best = [null, []];

        foreach (array_slice($rows, 0, 15, true) as $r => $row) {
            $found = [];
            foreach ($row as $col => $cell) {
                $date = $this->asDate((string) $cell, $start, $end);
                if ($date && ! in_array($date, $found, true)) {
                    $found[$col] = $date;
                }
            }
            if (count($found) > count($best[1]) && count($found) >= 3) {
                $best = [$r, $found];
            }
        }

        return $best;
    }

    private function asDate(string $cell, Carbon $start, Carbon $end): ?string
    {
        $cell = trim($cell);
        // A shift is not a date: "10-7" in an October sheet would otherwise
        // read as October 7th and pass itself off as the row of dates.
        if ($cell === '' || self::times(strtoupper($cell)) !== null) {
            return null;
        }
        $date = null;
        if (preg_match('/^\d{1,2}(\.0+)?$/', $cell)) {
            $day = (int) $cell;
            if ($day >= 1 && $day <= 31) {
                $date = $start->copy()->day(min($day, $start->daysInMonth));
                if ($date->day !== $day) {
                    return null;
                }
            }
        } elseif (preg_match('/^\d{5}(\.0+)?$/', $cell)) {
            // Excel keeps dates as days since 1899-12-30.
            $date = Carbon::create(1899, 12, 30)->addDays((int) $cell);
        } elseif (preg_match('/\d/', $cell) && preg_match('/[a-z\/\-]/i', $cell)) {
            try {
                $date = Carbon::parse($cell);
            } catch (\Throwable) {
                return null;
            }
        }

        return $date && $date->betweenIncluded($start, $end) ? $date->toDateString() : null;
    }

    /** The name written on the row: the text cells before the first date. */
    private function rowLabel(array $row, int $firstDateColumn): string
    {
        $parts = [];
        foreach (array_slice($row, 0, $firstDateColumn) as $cell) {
            $cell = trim((string) $cell);
            if ($cell !== '' && ! preg_match('/^\d+$/', $cell)) {
                $parts[] = $cell;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function staff(): Collection
    {
        return DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('e.status', 'active')
            ->get(['e.employee_id', 'e.employee_no', 'u.full_name', 'u.first_name', 'u.last_name']);
    }

    /**
     * Who the row is. An employee number anywhere before the dates decides
     * it; otherwise every word of the name written must be found in exactly
     * one active person's name - "Esguerra, Carla", "Avegiel F. Blanca" and
     * "FATIMA" all work, a nickname nobody's name contains does not.
     */
    private function match(array $row, int $firstDateColumn, Collection $staff): ?object
    {
        $before = array_slice($row, 0, $firstDateColumn);

        foreach ($before as $cell) {
            if (preg_match('/\b(IC|CAFE|SL)\s*-\s*(\d+)\b/i', (string) $cell, $m)) {
                $number = strtoupper($m[1]).'-'.str_pad($m[2], 5, '0', STR_PAD_LEFT);
                if (($found = $staff->firstWhere('employee_no', $number)) && $this->sameName($before, $found)) {
                    return $found;
                }
                // A number belonging to somebody else (a typo in the sheet): the name decides.
            }
        }

        $words = fn (string $s) => array_values(array_filter(preg_split('/[^a-z0-9ñ]+/u', mb_strtolower($s)), fn ($w) => $w !== ''));
        $hitsFor = fn (array $written) => $staff->filter(function ($person) use ($written, $words) {
            $name = $words($person->full_name.' '.$person->first_name.' '.$person->last_name);
            foreach ($written as $w) {
                // A long word one or two letters off still counts: "Catorse" is Catorce.
                $ok = strlen($w) === 1
                    ? (bool) array_filter($name, fn ($n) => str_starts_with($n, $w))
                    : (in_array($w, $name, true)
                        || (mb_strlen($w) >= 5 && (bool) array_filter($name, fn ($n) => mb_strlen($n) >= 5 && levenshtein($w, $n) <= 2))
                        // A short name for a longer one: "Aila" is Ailamarie.
                        || (mb_strlen($w) >= 3 && (bool) array_filter($name, fn ($n) => mb_strlen($n) > mb_strlen($w) && str_starts_with($n, $w))));
                if (! $ok) {
                    return false;
                }
            }

            return true;
        });

        // Each cell on its own first: a sheet may carry job titles and notes
        // beside the name ("CONCEPCION SACRIZ | Production Team Leader").
        $cells = array_values(array_filter(array_map('strval', $before), fn ($c) => ! $this->notAName($c)));
        foreach ($cells as $cell) {
            $hits = $hitsFor($words($cell));
            if ($hits->count() === 1) {
                return $hits->first();
            }
        }
        // Then the name written across several cells (first name, surname).
        $written = array_merge(...array_map($words, $cells ?: ['']));
        if (! $written) {
            return null;
        }
        $hits = $hitsFor($written);

        return $hits->count() === 1 ? $hits->first() : null;
    }

    /**
     * Cells before the dates that are not part of a name: an employee number,
     * a row number, or a schedule cell (RD, 8-5) from an earlier cutoff's columns.
     */
    private function notAName(string $cell): bool
    {
        $cell = trim($cell);

        return $cell === '' || preg_match('/^\s*(IC|CAFE|SL)\s*-/i', $cell) || preg_match('/^\d+(\.\d+)?$/', $cell)
            || self::understand($cell)['type'] !== 'unknown'
            || (preg_match('/^[A-Z ]+$/', $cell) && in_array(self::understand($cell)['type'], ['ob'], true));
    }

    /** Whether a row's written name, if it has one, shares a word with the person's. */
    private function sameName(array $before, object $person): bool
    {
        $words = fn (string $s) => array_filter(preg_split('/[^a-z0-9ñ]+/u', mb_strtolower($s)), fn ($w) => mb_strlen($w) > 1);
        $written = [];
        foreach ($before as $cell) {
            if ($this->notAName((string) $cell)) continue;
            $written = array_merge($written, $words((string) $cell));
        }

        return ! $written || (bool) array_intersect($written, $words($person->full_name.' '.$person->first_name.' '.$person->last_name));
    }
}
