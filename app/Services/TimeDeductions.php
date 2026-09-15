<?php

namespace App\Services;

use App\Support\Tardiness;
use App\Support\Undertime;
use App\Support\WorkWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a cutoff's attendance costs: days missed, plus arriving late and leaving
 * early on the days worked.
 *
 * Everything is judged a day at a time, and only on days the person was
 * actually due in - not their rest days, not holidays, nothing before they were
 * hired, and nothing in the future, so a payslip generated mid-cutoff does not
 * charge anybody for days that have not happened yet.
 *
 * On a day they were due but did not clock in:
 *   - approved leave that is not the unpaid kind costs nothing;
 *   - approved unpaid leave costs the day;
 *   - nothing at all - no record, or a record marked absent - also costs the
 *     day, because from payroll's point of view it is the same day missed.
 *
 * On a day they did work, lateness and undertime apply as before, the two
 * together capped at what the day is worth.
 */
class TimeDeductions
{
    /**
     * Leave types that are not paid. Everything else in the leaves table -
     * vacation, sick, maternity and the rest - is paid leave, so it is a day
     * off rather than a day missing.
     */
    public const UNPAID_LEAVE_TYPES = ['unpaid'];

    /**
     * @param  object  $employee  the employees row: employee_id, salary, hire_date,
     *                            shift_start, shift_end, rest_days
     * @return array{late: float, lateDays: int, undertime: float, undertimeDays: int,
     *               absence: float, absentDays: int, unpaidLeave: float, unpaidLeaveDays: int,
     *               leaveDays: int, total: float}
     */
    public function forPeriod(object $employee, string $periodStart, string $periodEnd): array
    {
        $salary = (float) $employee->salary;
        $dailyRate = round(Tardiness::dailyRate($salary), 2);

        $holidays = DB::table('holidays')->whereBetween('date', [$periodStart, $periodEnd])->pluck('date')
            ->map(fn ($date) => substr((string) $date, 0, 10))->all();

        $attendance = DB::table('hr_attendance')->where('employee_id', $employee->employee_id)
            ->whereBetween('date', [$periodStart, $periodEnd])->get()
            ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        // Approved leave only. A pending request is not yet a day off, and
        // paying it before the decision would make the decision meaningless.
        $leaves = DB::table('leaves')->where('employee_id', $employee->employee_id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $periodEnd)->where('end_date', '>=', $periodStart)
            ->get();

        $totals = ['late' => 0.0, 'lateDays' => 0, 'undertime' => 0.0, 'undertimeDays' => 0,
            'absence' => 0.0, 'absentDays' => 0, 'unpaidLeave' => 0.0, 'unpaidLeaveDays' => 0,
            'leaveDays' => 0, 'total' => 0.0];

        // Never past today: the rest of the cutoff has not happened.
        $last = Carbon::parse($periodEnd)->min(Carbon::today());
        $hired = $employee->hire_date ? Carbon::parse($employee->hire_date) : null;

        for ($day = Carbon::parse($periodStart); $day->lte($last); $day->addDay()) {
            $date = $day->toDateString();

            if (in_array($date, $holidays, true) || WorkWeek::restsOn($employee->rest_days ?? null, $day)) {
                continue;
            }

            if ($hired && $day->lt($hired)) {
                continue;
            }

            $row = $attendance->get($date);

            if ($row && $row->time_in) {
                $this->chargeWorkedDay($totals, $row, $employee, $salary);

                continue;
            }

            if (($row && $row->status === 'on_leave') || $this->leaveOn($leaves, $date)) {
                if ($this->unpaidLeaveOn($leaves, $date)) {
                    $totals['unpaidLeave'] += $dailyRate;
                    $totals['unpaidLeaveDays']++;
                    $totals['total'] += $dailyRate;
                } else {
                    $totals['leaveDays']++;
                }

                continue;
            }

            $totals['absence'] += $dailyRate;
            $totals['absentDays']++;
            $totals['total'] += $dailyRate;
        }

        foreach (['late', 'undertime', 'absence', 'unpaidLeave', 'total'] as $money) {
            $totals[$money] = round($totals[$money], 2);
        }

        return $totals;
    }

    private function chargeWorkedDay(array &$totals, object $row, object $employee, float $salary): void
    {
        $lateCost = $employee->shift_start
            ? Tardiness::deduction(Tardiness::minutesLate(Carbon::parse($row->time_in), $employee->shift_start), $salary)
            : 0.0;

        $shortCost = ($employee->shift_end ?? null) && $row->time_out
            ? Undertime::deduction(Undertime::minutesShort(Carbon::parse($row->time_out), $employee->shift_end), $salary)
            : 0.0;

        if ($lateCost > 0) {
            $totals['late'] += $lateCost;
            $totals['lateDays']++;
        }

        if ($shortCost > 0) {
            $totals['undertime'] += $shortCost;
            $totals['undertimeDays']++;
        }

        $totals['total'] += Undertime::capDay($lateCost, $shortCost, $salary);
    }

    private function leaveOn(iterable $leaves, string $date): bool
    {
        foreach ($leaves as $leave) {
            if ($this->covers($leave, $date)) {
                return true;
            }
        }

        return false;
    }

    private function unpaidLeaveOn(iterable $leaves, string $date): bool
    {
        foreach ($leaves as $leave) {
            if ($this->covers($leave, $date) && in_array($leave->leave_type, self::UNPAID_LEAVE_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    private function covers(object $leave, string $date): bool
    {
        return substr((string) $leave->start_date, 0, 10) <= $date
            && substr((string) $leave->end_date, 0, 10) >= $date;
    }
}
