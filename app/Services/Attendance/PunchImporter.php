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

    public function import(array $punches, bool $overwriteManual = false): array
    {
        if (! $punches) {
            return ['days' => 0, 'employees' => 0, 'unknown' => [], 'skipped' => 0];
        }

        // Everyone the device could be talking about, by enrolment number.
        $employees = DB::table('employees')
            ->whereNotNull('biometric_id')
            ->get(['employee_id', 'biometric_id', 'shift_start', 'shift_end', 'rest_days'])
            ->keyBy('biometric_id');

        $ids = DB::table('employees')
            ->whereNotNull('biometric_id')
            ->pluck('employee_id', 'biometric_id');

        $byEmployeeDay = [];
        $unknown = [];
        $skipped = 0;

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

            $key = $ids[$bio].'|'.$moment->toDateString();

            if (! isset($byEmployeeDay[$key])) {
                $byEmployeeDay[$key] = [
                    'employee_id' => $ids[$bio],
                    'date'        => $moment->toDateString(),
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

            $byEmployeeDay[$key]['punches'][] = ['at' => $moment, 'direction' => $direction, 'slot' => $slot];
        }

        $days = 0;
        $touchedEmployees = [];

        DB::transaction(function () use ($byEmployeeDay, $overwriteManual, &$days, &$touchedEmployees) {
            foreach ($byEmployeeDay as $day) {
                $existing = DB::table('hr_attendance')
                    ->where('employee_id', $day['employee_id'])
                    ->whereDate('date', $day['date'])
                    ->first();

                // Incremental exports must retain punches imported earlier.
                if ($existing && $existing->notes === 'From the biometric scanner') {
                    foreach (array_keys(WorkDay::PUNCHES) as $column) {
                        if ($existing->{$column} ?? null) {
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

        // What the person pressed, when every punch has a key and no key was
        // pressed twice: that says lunch from coffee break even on a day with
        // a punch missing, which counting cannot. Any doubt, and the day is
        // read by position as before.
        $byKey = [];
        foreach ($ordered as $punch) {
            $slot = $punch['slot'] ?? null;
            if (! $slot || isset($byKey[$slot])) {
                $byKey = null;
                break;
            }
            $byKey[$slot] = $punch['at']->toDateTimeString();
        }
        if ($byKey) {
            return array_merge($slots, $byKey);
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
