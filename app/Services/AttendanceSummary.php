<?php

namespace App\Services;

use App\Support\PayPeriod;
use App\Support\WorkDay;
use Illuminate\Support\Facades\DB;

/**
 * How many days each person was present, late, absent and on leave in a
 * cutoff.
 *
 * Absent and late come from TimeDeductions, the same calculation payroll docks
 * pay with, rather than being counted a second way here. Two counts of the
 * same thing drift apart, and then HR is looking at "2 absences" on this
 * screen while the payslip deducts three.
 *
 * Present is simply a day with a first-in punch.
 */
class AttendanceSummary
{
    /**
     * @param  list<int>  $employeeIds
     * @return array<int, array{present: int, late: int, absent: int, leave: int, unpaid_leave: int, worked_minutes: int}>
     */
    public function forEmployees(array $employeeIds, PayPeriod $period): array
    {
        if (! $employeeIds) {
            return [];
        }

        $present = DB::table('hr_attendance')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$period->start, $period->end])
            ->where(fn ($q) => $q->whereNotNull('time_in')->orWhere('status', 'official_business'))
            ->selectRaw('employee_id, COUNT(*) n')
            ->groupBy('employee_id')
            ->pluck('n', 'employee_id');

        $workedMinutes = DB::table('hr_attendance')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$period->start, $period->end])
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->sum(fn ($row) => WorkDay::workedMinutes($row)));

        $employees = DB::table('employees')
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $deductions = new TimeDeductions;
        $out = [];

        foreach ($employeeIds as $id) {
            $employee = $employees->get($id);

            if (! $employee) {
                continue;
            }

            $t = $deductions->forPeriod($employee, $period->start, $period->end);

            $out[$id] = [
                'present'      => (int) ($present[$id] ?? 0),
                'late'         => (int) ($t['lateDays'] ?? 0),
                'absent'       => (int) ($t['absentDays'] ?? 0),
                'leave'        => (int) ($t['leaveDays'] ?? 0),
                'unpaid_leave' => (int) ($t['unpaidLeaveDays'] ?? 0),
                'worked_minutes' => (int) ($workedMinutes[$id] ?? 0),
            ];
        }

        return $out;
    }

    /** The cutoff containing a date: 1-15, or 16 to the month's last day. */
    public static function cutoffFor(string $date): PayPeriod
    {
        return PayPeriod::fromStart($date);
    }
}
