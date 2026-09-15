<?php

namespace App\Services\Attendance;

use App\Support\Tardiness;
use Carbon\Carbon;
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
            ->pluck('shift_start', 'biometric_id');

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
                    'shift'       => $employees[$bio] ?? null,
                    'first'       => $moment,
                    'last'        => $moment,
                ];

                continue;
            }

            if ($moment->lt($byEmployeeDay[$key]['first'])) {
                $byEmployeeDay[$key]['first'] = $moment;
            }

            if ($moment->gt($byEmployeeDay[$key]['last'])) {
                $byEmployeeDay[$key]['last'] = $moment;
            }
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
                    foreach ([$existing->time_in, $existing->time_out] as $stamp) {
                        if ($stamp) {
                            $moment = Carbon::parse($stamp);
                            if ($moment->lt($day['first'])) {
                                $day['first'] = $moment;
                            }
                            if ($moment->gt($day['last'])) {
                                $day['last'] = $moment;
                            }
                        }
                    }
                }

                // A single punch is an arrival, not a whole day - leaving
                // time_out null is truer than pretending they left when they
                // arrived.
                $timeOut = $day['last']->equalTo($day['first']) ? null : $day['last']->toDateTimeString();

                $status = Tardiness::isLate($day['first'], $day['shift']) ? 'late' : 'present';

                $row = [
                    'time_in'    => $day['first']->toDateTimeString(),
                    'time_out'   => $timeOut,
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
}
