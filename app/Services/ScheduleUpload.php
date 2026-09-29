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
    public function read(array $rows, PayPeriod $period): array
    {
        [$headerAt, $dateColumns] = $this->findDates($rows, $period);
        if ($headerAt === null) {
            throw new \RuntimeException("No row of dates for {$period->label()} was found. The sheet needs the days of the cutoff across one row - day numbers like 1, 2, 3 or full dates.");
        }

        $firstDateColumn = min(array_keys($dateColumns));
        $staff = $this->staff();
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

        if ($text === '') {
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
            return ['type' => str_contains($text, 'WITH PAY') || str_contains($text, 'W/ PAY') ? 'leave_paid' : 'leave_unpaid'];
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
        $end = $hour((int) $m[4], $m[6] ?? '', $start * 60 + $startMinute);
        if ($end === null) {
            return null;
        }

        return ['start' => sprintf('%02d:%02d', $start, $startMinute), 'end' => sprintf('%02d:%02d', $end, $endMinute)];
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
                        DB::table('leaves')->insert([
                            'employee_id' => $id, 'leave_type' => $type === 'leave_paid' ? 'vacation' : 'unpaid',
                            'start_date' => $from, 'end_date' => $to,
                            'total_days' => Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1,
                            'reason' => ($type === 'leave_paid' ? 'Leave with pay' : 'Leave without pay')." (from the {$plan['period']['label']} schedule)",
                            'status' => 'approved', 'approved_by' => $byUserId, 'approved_at' => now(),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $done['leave']++;
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
        if (preg_match('/^\d{1,2}$/', $cell)) {
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
                if ($found = $staff->firstWhere('employee_no', $number)) {
                    return $found;
                }
            }
        }

        $words = fn (string $s) => array_values(array_filter(preg_split('/[^a-z0-9ñ]+/u', mb_strtolower($s)), fn ($w) => $w !== ''));
        $written = [];
        foreach ($before as $cell) {
            if (preg_match('/^\s*(IC|CAFE|SL)\s*-/i', (string) $cell) || preg_match('/^\d+$/', trim((string) $cell))) {
                continue;
            }
            $written = array_merge($written, $words((string) $cell));
        }
        if (! $written) {
            return null;
        }

        $hits = $staff->filter(function ($person) use ($written, $words) {
            $name = $words($person->full_name.' '.$person->first_name.' '.$person->last_name);
            foreach ($written as $w) {
                $ok = strlen($w) === 1
                    ? (bool) array_filter($name, fn ($n) => str_starts_with($n, $w))
                    : in_array($w, $name, true);
                if (! $ok) {
                    return false;
                }
            }

            return true;
        });

        return $hits->count() === 1 ? $hits->first() : null;
    }
}
