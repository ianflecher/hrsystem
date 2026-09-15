<?php

namespace App\Services;

use App\Support\Tardiness;
use App\Support\Undertime;
use App\Support\WorkWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a cutoff's attendance costs: arriving late, and leaving early.
 *
 * Both are judged a day at a time, and only on days the person was actually
 * due in - not their rest days, not holidays. A day with no clock-out is not
 * undertime, because nobody knows when they left; a missed scan should not take
 * money off anybody.
 *
 * The two together are capped at the day's rate, so half a day late and half a
 * day short costs one day, never more.
 */
class TimeDeductions
{
    /**
     * @param  object  $employee  the employees row: salary, shift_start, shift_end, rest_days
     * @return array{late: float, lateDays: int, undertime: float, undertimeDays: int, total: float}
     */
    public function forPeriod(object $employee, string $periodStart, string $periodEnd): array
    {
        $salary = (float) $employee->salary;

        $holidays = DB::table('holidays')->whereBetween('date', [$periodStart, $periodEnd])->pluck('date')
            ->map(fn ($date) => substr((string) $date, 0, 10))->all();

        $days = DB::table('hr_attendance')->where('employee_id', $employee->employee_id)
            ->whereBetween('date', [$periodStart, $periodEnd])->whereNotNull('time_in')->get();

        $late = 0.0;
        $lateDays = 0;
        $undertime = 0.0;
        $undertimeDays = 0;
        $total = 0.0;

        foreach ($days as $day) {
            $date = Carbon::parse($day->date);

            if (in_array($date->toDateString(), $holidays, true) || WorkWeek::restsOn($employee->rest_days ?? null, $date)) {
                continue;
            }

            $lateCost = $employee->shift_start
                ? Tardiness::deduction(Tardiness::minutesLate(Carbon::parse($day->time_in), $employee->shift_start), $salary)
                : 0.0;

            $shortCost = ($employee->shift_end ?? null) && $day->time_out
                ? Undertime::deduction(Undertime::minutesShort(Carbon::parse($day->time_out), $employee->shift_end), $salary)
                : 0.0;

            if ($lateCost > 0) {
                $late += $lateCost;
                $lateDays++;
            }

            if ($shortCost > 0) {
                $undertime += $shortCost;
                $undertimeDays++;
            }

            $total += Undertime::capDay($lateCost, $shortCost, $salary);
        }

        return [
            'late'          => round($late, 2),
            'lateDays'      => $lateDays,
            'undertime'     => round($undertime, 2),
            'undertimeDays' => $undertimeDays,
            'total'         => round($total, 2),
        ];
    }
}
