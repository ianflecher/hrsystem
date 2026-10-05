<?php

namespace App\Services;

use App\Support\PayPeriod;
use App\Support\ShiftSchedule;
use App\Support\Tardiness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One cutoff's attendance per person: who was late (how often, how long, and
 * what the late rule takes), who was absent, short, on leave, or never punched
 * out - the things HR checks before payroll.
 */
class CutoffAttendanceSummary
{
    /** @return list<array<string, mixed>> */
    public function forPeriod(PayPeriod $period, ?string $company = null, ?int $departmentId = null): array
    {
        $staff = DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->where('e.status', 'active')
            ->where(fn ($q) => \App\Support\NotInSummaries::scope($q))
            ->when($company, fn ($q) => $q->whereRaw("COALESCE(NULLIF(e.company, ''), 'GKLASAM OPC') = ?", [$company]))
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->orderByRaw("COALESCE(NULLIF(u.last_name, ''), u.full_name)")->orderBy('u.first_name')
            ->get(['e.*', 'u.full_name', 'u.last_name', 'd.department_name']);

        $last = Carbon::parse($period->end)->min(Carbon::yesterday())->toDateString();
        $attendance = DB::table('hr_attendance')->whereIn('employee_id', $staff->pluck('employee_id'))
            ->whereBetween('date', [$period->start, $period->end])->get()
            ->groupBy('employee_id');
        $overtime = DB::table('overtime_requests')->whereIn('employee_id', $staff->pluck('employee_id'))
            ->where('starts_at', '>=', $period->start)->where('starts_at', '<', Carbon::parse($period->end)->addDay()->toDateString())
            ->whereIn('status', ['approved', 'pending', 'pending_hr'])->get()->groupBy('employee_id');

        $rows = [];
        foreach ($staff as $e) {
            $days = ($attendance->get($e->employee_id) ?? collect())->keyBy(fn ($a) => substr((string) $a->date, 0, 10));
            $guard = ShiftSchedule::isGuard($e);
            $late = [];
            $lateMinutes = 0;
            $penaltyHours = 0.0;
            $noOut = [];
            $restWorked = 0;
            $worked = 0;
            foreach ($days as $date => $a) {
                if (! $a->time_in) continue;
                $worked++;
                $shift = ShiftSchedule::forEmployeeDate($e, $date);
                if ($shift['rest']) {
                    $restWorked++;
                }
                if (! $a->time_out && $date <= $last) {
                    $noOut[] = Carbon::parse($date)->format('M j');
                }
                if ($shift['rest'] || ! $shift['start'] || $a->status === 'official_business') continue;
                $minutes = (int) (Tardiness::minutesLate(Carbon::parse($a->time_in), $shift['start']) ?? 0);
                // The late rule: up to 5 minutes is grace, 6-15 costs an hour, 16 or more half a day.
                if ($minutes > 5) {
                    $late[] = Carbon::parse($date)->format('M j').' ('.$minutes.'m)';
                    $lateMinutes += $minutes;
                    if (! $guard) $penaltyHours += $minutes <= 15 ? 1 : 4;
                }
            }

            // Absences, undertime and leave counted the way payroll counts them.
            // Up to yesterday: today is not over, so nobody is absent from it yet.
            $t = $last >= $period->start ? (new TimeDeductions)->forPeriod($e, $period->start, $last)
                : ['absentDays' => 0, 'undertimeDays' => 0, 'leaveDays' => 0, 'unpaidLeaveDays' => 0, 'suspendedDays' => 0, 'dates' => []];
            $ot = $overtime->get($e->employee_id) ?? collect();

            $rows[] = [
                'employee_id' => $e->employee_id,
                'employee_no' => $e->employee_no,
                'name' => $e->full_name,
                'last_name' => $e->last_name,
                'department' => $e->department_name,
                'company' => $e->company ?: 'GKLASAM OPC',
                'no_scanner' => ! $e->biometric_id,
                'worked' => $worked,
                'late_count' => count($late),
                'late_minutes' => $lateMinutes,
                'late_penalty_hours' => $penaltyHours,
                'late_days' => $late,
                'absent' => (int) $t['absentDays'],
                // Suspensions are set ahead, so the whole cutoff counts - not only up to yesterday.
                'suspended' => $days->filter(fn ($a) => ! $a->time_in && $a->notes === 'Suspension')->count(),
                'suspended_days' => $days->filter(fn ($a) => ! $a->time_in && $a->notes === 'Suspension')->keys()->sort()->map(fn ($d) => Carbon::parse($d)->format('M j'))->values()->all(),
                'absent_days' => array_map(fn ($d) => Carbon::parse($d)->format('M j'), $t['dates']['absent'] ?? []),
                'undertime_days' => array_map(fn ($d) => Carbon::parse($d)->format('M j'), $t['dates']['undertime'] ?? []),
                'unpaid_leave_days' => array_map(fn ($d) => Carbon::parse($d)->format('M j'), $t['dates']['unpaid_leave'] ?? []),
                'undertime' => (int) $t['undertimeDays'],
                'paid_leave' => (int) $t['leaveDays'],
                'unpaid_leave' => (int) $t['unpaidLeaveDays'],
                'no_out' => $noOut,
                'rest_worked' => $restWorked,
                'ot_approved' => round($ot->where('status', 'approved')->sum('minutes') / 60, 2),
                'ot_pending' => round($ot->whereIn('status', ['pending', 'pending_hr'])->sum('minutes') / 60, 2),
            ];
        }

        return $rows;
    }
}
