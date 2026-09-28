<?php

namespace App\Services\Attendance;

use App\Support\Tardiness;
use App\Support\ShiftSchedule;
use Carbon\Carbon;
use App\Support\WorkDay;
use Illuminate\Support\Facades\DB;

/**
 * Turns raw scanner punches into attendance days.
 *
 * A scanner emits one row per scan - four on a day somebody goes out for lunch
 * and back. Attendance here is one row per employee per day, so the punches are
 * folded: earliest becomes time_in, latest becomes time_out, and anything
 * between is left alone rather than guessed at as a break.
 *
 * Both sources feed this. The network pull and the file upload differ only in
 * where the punches came from, so everything that could be wrong about the
 * result is decided here, in one place that can be tested without a device.
 */
class PunchImporter
{
    /**
     * @param  array<int, array{biometric_id: string, timestamp: string}>  $punches
     * @return array{days: int, employees: int, unknown: array<int, string>, skipped: int}
     */
    /**
     * The scanner's punch-state keys, as it stores them, to the day's slots.
     * Break out/in are the lunch; the second pair is the coffee break.
     */
    private const STATE_SLOTS = [
        0 => 'time_in', 1 => 'time_out',
        2 => 'lunch_in', 3 => 'lunch_out',
        4 => 'cb_in', 5 => 'cb_out',
    ];

    /** How long after a night shift's end a late time-out still closes that night. */
    private const NIGHT_OUT_GRACE_HOURS = 4;

    /**
     * Which work day a punch belongs to. Usually the date on the clock; for a
     * shift that runs past midnight, the early-morning scans are the end of
     * the previous night.
     *
     * With a shift set, it is decided by that shift. A security guard with no
     * shift set works 07:00-19:00 or 19:00-07:00, so a morning scan is only
     * the end of a night duty when there was an evening scan before it - and
     * never when the key pressed was check in, which is a morning duty
     * starting.
     */
    public static function workDateForPunch(?object $employee, Carbon $moment, ?Carbon $previousPunch = null, ?int $state = null): string
    {
        $today = $moment->toDateString();
        if (! $employee || $state === 0) {
            return $today;
        }

        $yesterday = $moment->copy()->subDay()->toDateString();
        $shift = ShiftSchedule::forEmployeeDate($employee, $yesterday);

        if ($shift['start'] && $shift['end']) {
            if ($shift['rest'] || ! ShiftSchedule::isOvernight($shift['start'], $shift['end'])) {
                return $today;
            }
            $cutoff = $moment->copy()->setTimeFromTimeString($shift['end'])->addHours(self::NIGHT_OUT_GRACE_HOURS);

            return $moment->lt($cutoff) ? $yesterday : $today;
        }

        if (! ShiftSchedule::isGuard($employee) || $moment->hour >= 12) {
            return $today;
        }

        // The evening scan that began the duty: in this batch, or already
        // stored from an earlier sync.
        $evening = $previousPunch && $previousPunch->toDateString() === $yesterday && $previousPunch->hour >= 17
            ? $previousPunch
            : null;
        if (! $evening) {
            $storedIn = DB::table('hr_attendance')->where('employee_id', $employee->employee_id)
                ->whereDate('date', $yesterday)->value('time_in');
            $evening = $storedIn && Carbon::parse($storedIn)->hour >= 17 ? Carbon::parse($storedIn) : null;
        }

        return $evening && $evening->diffInHours($moment) <= 16 ? $yesterday : $today;
    }

    public function import(array $punches, bool $overwriteManual = false): array
    {
        if (! $punches) {
            return ['days' => 0, 'employees' => 0, 'unknown' => [], 'skipped' => 0];
        }

        // Everyone the device could be talking about, by enrolment number.
        $employees = DB::table('employees')
            ->whereNotNull('biometric_id')
            ->get(['employee_id', 'biometric_id', 'shift_start', 'shift_end', 'rest_days', 'job_title'])
            ->keyBy('biometric_id');

        $ids = DB::table('employees')
            ->whereNotNull('biometric_id')
            ->pluck('employee_id', 'biometric_id');

        $byEmployeeDay = [];
        $unknown = [];
        $skipped = 0;

        // In time order, so a morning scan can see the evening one before it:
        // that is how a night duty's time-out finds the night it ends.
        usort($punches, fn ($a, $b) => strcmp((string) ($a['timestamp'] ?? ''), (string) ($b['timestamp'] ?? '')));
        $previous = [];

        // A pull from the scanner carries its keys and is the device's whole
        // log from its first punch on. Within that reach it is the truth: what
        // was stored there before is replaced, not merged - a morning time-out
        // once filed under the wrong day must not come back. A file upload may
        // be partial, so it only ever adds.
        $fromDevice = (bool) array_filter($punches, fn ($p) => isset($p['state']));
        $reachStart = $fromDevice ? Carbon::parse((string) ($punches[0]['timestamp'] ?? '')) : null;

        foreach ($punches as $punch) {
            $bio = (string) ($punch['biometric_id'] ?? '');
            $stamp = $punch['timestamp'] ?? null;

            if ($bio === '' || ! $stamp) {
                $skipped++;
                continue;
            }

            if (! isset($ids[$bio])) {
                // Recorded rather than dropped: an enrolment number nobody owns
                // usually means somebody was enrolled on the device but never
                // linked here, and silently ignoring it loses their whole month.
                $unknown[$bio] = true;
                $skipped++;
                continue;
            }

            try {
                $moment = Carbon::parse($stamp);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }

            // The day the work belongs to, not the day on the clock: a night
            // duty's morning time-out is the previous night's. The punch keeps
            // its real timestamp either way.
            $workDate = self::workDateForPunch($employees[$bio] ?? null, $moment, $previous[$bio] ?? null, $punch['state'] ?? null);
            $previous[$bio] = $moment;

            $key = $ids[$bio].'|'.$workDate;

            if (! isset($byEmployeeDay[$key])) {
                $byEmployeeDay[$key] = [
                    'employee_id' => $ids[$bio],
                    'date'        => $workDate,
                    'employee'    => $employees[$bio] ?? null,
                    // Every punch, not just the outer two: the middle ones are
                    // the breaks, and keeping only first and last was what made
                    // them invisible.
                    'punches'     => [],
                ];
            }

            // The device's own in/out flag when it sends one. It only has
            // those two keys - no break-in or break-out - so it cannot say
            // lunch from coffee break, but knowing which way somebody went
            // through the door survives a missed punch, and counting
            // positions does not.
            $direction = strtolower(trim((string) ($punch['type'] ?? '')));
            $direction = match (true) {
                in_array($direction, ['i', '0', 'in'], true) => 'in',
                in_array($direction, ['o', '1', 'out'], true) => 'out',
                default => null,
            };

            // The key pressed, where the device sends one: which of the six
            // slots the person said this punch was.
            $slot = self::STATE_SLOTS[$punch['state'] ?? -1] ?? null;
            if ($slot && ! $direction) {
                $direction = in_array($slot, ['time_in', 'lunch_out', 'cb_out'], true) ? 'in' : 'out';
            }

            $byEmployeeDay[$key]['punches'][] = ['at' => $moment, 'direction' => $direction, 'slot' => $slot, 'keyed' => $slot !== null];
        }

        $days = 0;
        $touchedEmployees = [];

        DB::transaction(function () use ($byEmployeeDay, $overwriteManual, $reachStart, &$days, &$touchedEmployees) {
            foreach ($byEmployeeDay as $day) {
                $existing = DB::table('hr_attendance')
                    ->where('employee_id', $day['employee_id'])
                    ->whereDate('date', $day['date'])
                    ->first();

                // Incremental exports must retain punches imported earlier.
                if ($existing && $existing->notes === 'From the biometric scanner') {
                    foreach (array_keys(WorkDay::PUNCHES) as $column) {
                        if (($existing->{$column} ?? null) && ! ($reachStart && Carbon::parse($existing->{$column})->gte($reachStart))) {
                            $day['punches'][] = [
                                'at' => Carbon::parse($existing->{$column}),
                                // A slot already knows which way it was.
                                'direction' => in_array($column, ['time_in', 'lunch_out', 'cb_out'], true) ? 'in' : 'out',
                                'slot' => $column,
                            ];
                        }
                    }
                }

                $slots = self::intoSlots($day['punches']);

                $day['first'] = $slots['time_in'] ? Carbon::parse($slots['time_in']) : null;

                $shift = $day['employee']
                    ? ShiftSchedule::forEmployeeDate($day['employee'], $day['date'])
                    : ['rest' => false, 'start' => null, 'end' => null];

                $status = ($day['first'] && ! $shift['rest'] && Tardiness::isLate($day['first'], $shift['start']))
                    ? 'late'
                    : 'present';

                $row = $slots + [
                    'status'     => $status,
                    'updated_at' => now(),
                ];

                if (! $existing) {
                    DB::table('hr_attendance')->insert($row + [
                        'employee_id' => $day['employee_id'],
                        'date'        => $day['date'],
                        'notes'       => 'From the biometric scanner',
                        'created_at'  => now(),
                    ]);

                    $days++;
                    $touchedEmployees[$day['employee_id']] = true;

                    continue;
                }

                // A row somebody typed by hand is a decision. The scanner does
                // not overrule it unless asked, or a correction made this
                // morning would be wiped by tonight's sync.
                $wasManual = $existing->notes !== 'From the biometric scanner';

                if ($wasManual && ! $overwriteManual) {
                    continue;
                }

                DB::table('hr_attendance')
                    ->where('attendance_id', $existing->attendance_id)
                    ->update($row);

                $days++;
                $touchedEmployees[$day['employee_id']] = true;
            }

            // A day the scanner made that no longer has a punch of its own -
            // the morning after a night duty, filed separately before night
            // shifts were understood - would read as a day worked. Within the
            // pull's reach, and only rows the scanner wrote, it goes.
            if ($reachStart) {
                $kept = [];
                foreach ($byEmployeeDay as $day) {
                    $kept[$day['employee_id']][] = $day['date'];
                }
                foreach ($kept as $employeeId => $dates) {
                    DB::table('hr_attendance')
                        ->where('employee_id', $employeeId)
                        ->where('notes', 'From the biometric scanner')
                        ->whereDate('date', '>', $reachStart->toDateString())
                        ->whereNotIn(DB::raw('DATE(date)'), $dates)
                        ->delete();
                }
            }
        });

        return [
            'days'      => $days,
            'employees' => count($touchedEmployees),
            // Cast back: PHP turns a numeric string array key into an int,
            // so a purely numeric enrolment number would come back as 999
            // rather than '999' and compare unequal to what the device sent.
            'unknown'   => array_map('strval', array_keys($unknown)),
            'skipped'   => $skipped,
        ];
    }

    /**
     * A day's punches into the six slots a day has.
     *
     * Position is the rule, because it is the only thing that holds. The first
     * punch opens the day, the last closes it, and what falls between is the
     * lunch and then the coffee break - which is optional, so four punches are
     * a day with a lunch and no break rather than a day missing two punches.
     *
     * The device's in/out flag is not used to shape the day. It has only two
     * keys and staff use them loosely - the real export has people punching
     * out twice in a row and in again the next morning - so reading the shape
     * from the flags produced days with no complete break at all where the
     * order plainly showed one.
     *
     * The flag is still worth one thing: telling an arrival from a departure
     * when there is a single punch, which order alone cannot do.
     *
     * @param  list<array{at: \Carbon\Carbon, direction: ?string}>  $punches
     * @return array<string, ?string>
     */
    private static function intoSlots(array $punches): array
    {
        $slots = array_fill_keys(array_keys(WorkDay::PUNCHES), null);

        // A scanner double-read a minute apart is one punch. Two rows for it
        // would shift every later slot and turn a lunch into a coffee break.
        $unique = [];

        // The first seen wins: the device's punches come before the ones read
        // back from the stored day, and the device knows which key was
        // pressed where a stored slot may only have been counted into place.
        foreach ($punches as $punch) {
            $unique[$punch['at']->format('Y-m-d H:i')] ??= $punch;
        }

        $ordered = array_values($unique);
        usort($ordered, fn ($a, $b) => $a['at'] <=> $b['at']);

        if (! $ordered) {
            return $slots;
        }

        // One punch is half a day. Which half is the one thing the flag can
        // say that the order cannot: an out on its own is somebody leaving
        // whose arrival was missed, not somebody arriving.
        if (count($ordered) === 1) {
            $only = $ordered[0];
            $slots[$only['direction'] === 'out' ? 'time_out' : 'time_in'] = $only['at']->toDateTimeString();

            return $slots;
        }

        // What the person pressed decides where a punch goes. A lunch scan is
        // the lunch whatever else the day holds - an unfinished day is not
        // read as ending at lunch, and a missed punch does not shift the rest.
        // A key pressed twice keeps its first (its last, for check out), and
        // the extra punches fill whatever the day is still missing, in order,
        // so a slip on the keypad loses nothing.
        // Only when the scanner itself said which key: a stored day's slots
        // may have been counted into place, and a file export has no keys.
        if (array_filter($ordered, fn ($punch) => ! empty($punch['keyed']))) {
            $spare = [];
            foreach ($ordered as $punch) {
                $slot = $punch['slot'] ?? null;
                $at = $punch['at']->toDateTimeString();
                if (! $slot) {
                    $spare[] = $at;
                } elseif ($slots[$slot] === null) {
                    $slots[$slot] = $at;
                } elseif ($slot === 'time_out') {
                    $spare[] = $slots['time_out'];
                    $slots['time_out'] = $at;
                } else {
                    $spare[] = $at;
                }
            }
            sort($spare);
            foreach ($spare as $at) {
                foreach (['time_in', 'lunch_in', 'lunch_out', 'cb_in', 'cb_out'] as $slot) {
                    if ($slots[$slot] === null && ($slot === 'time_in' || $at > ($slots['time_in'] ?? ''))) {
                        $slots[$slot] = $at;
                        continue 2;
                    }
                }
            }

            return $slots;
        }

        // The last punch is the final out, whatever else the day holds.
        $slots['time_out'] = end($ordered)['at']->toDateTimeString();

        $keys = ['time_in', 'lunch_in', 'lunch_out', 'cb_in', 'cb_out'];

        foreach (array_slice($ordered, 0, -1) as $i => $punch) {
            if (! isset($keys[$i])) {
                break;
            }

            $slots[$keys[$i]] = $punch['at']->toDateTimeString();
        }

        return $slots;
    }
}
