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

            $byEmployeeDay[$key]['punches'][] = $moment;
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
                            $day['punches'][] = Carbon::parse($existing->{$column});
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
     * A day's punches, in order, into the six slots a day has.
     *
     * The scanner gives a stream with no labels - it records that somebody
     * touched it, not why - so the order is all there is to go on. Six or more
     * fills every slot; anything in between fills as far as it reaches and
     * leaves the rest null rather than guessing which break was skipped.
     *
     * A lone punch is an arrival, not a whole day: leaving time_out null is
     * truer than pretending somebody left the moment they came in.
     *
     * @param  list<\Carbon\Carbon>  $punches
     * @return array<string, ?string>
     */
    private static function intoSlots(array $punches): array
    {
        $slots = array_fill_keys(array_keys(WorkDay::PUNCHES), null);

        // Sorted and de-duplicated: a scanner double-read a second apart is one
        // punch, and two rows for it would shift every later slot along by one.
        $unique = [];

        foreach ($punches as $punch) {
            $unique[$punch->format('Y-m-d H:i')] = $punch;
        }

        $ordered = array_values($unique);
        usort($ordered, fn ($a, $b) => $a <=> $b);

        if (! $ordered) {
            return $slots;
        }

        $keys = array_keys(WorkDay::PUNCHES);

        if (count($ordered) === 1) {
            $slots['time_in'] = $ordered[0]->toDateTimeString();

            return $slots;
        }

        // The last punch is always the final out, whatever else was recorded.
        $slots['time_out'] = end($ordered)->toDateTimeString();
        $middle = array_slice($ordered, 0, -1);

        foreach ($middle as $i => $punch) {
            if (! isset($keys[$i]) || $keys[$i] === 'time_out') {
                break;
            }

            $slots[$keys[$i]] = $punch->toDateTimeString();
        }

        return $slots;
    }
}
