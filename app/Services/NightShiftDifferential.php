<?php

namespace App\Services;

use App\Support\ShiftSchedule;
use App\Support\Statutory;
use App\Support\WorkDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NightShiftDifferential
{
    public function forAttendance(object $attendance, float $monthlySalary, ?object $employee = null): float
    {
        $effectiveOut = WorkDay::effectiveOut($attendance);
        if (! $attendance->time_in || ! $effectiveOut) return 0.0;
        $start = Carbon::parse($attendance->time_in);
        $end = $effectiveOut;
        if ($end->lessThanOrEqualTo($start)) $end->addDay();

        return round($this->calculateSegmented($start, $end, $monthlySalary, $employee), 2);
    }

    public function forPeriod(object $employee, string $periodStart, string $periodEnd, ?string $ruleDate = null): array
    {
        $rows = DB::table('hr_attendance')
            ->where('employee_id', $employee->employee_id)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereNotNull('time_in')->get();

        $amount = 0.0; $hours = 0.0;
        foreach ($rows as $row) {
            $effectiveOut = WorkDay::effectiveOut($row);
            if (! $effectiveOut) continue;

            $start = Carbon::parse($row->time_in); $end = $effectiveOut;
            if ($end->lessThanOrEqualTo($start)) $end->addDay();
            // Only the scheduled shift earns the differential: coming in at
            // 5:55 for an 8:00 start is not night work, and time past the
            // shift is overtime, paid on its own request.
            $shift = ShiftSchedule::forEmployeeDate($employee, substr((string) $row->date, 0, 10));
            if ($shift['start'] && $shift['end'] && ! $shift['rest']) {
                $from = Carbon::parse(substr((string) $row->date, 0, 10).' '.$shift['start']);
                $to = Carbon::parse(substr((string) $row->date, 0, 10).' '.$shift['end']);
                if ($to->lessThanOrEqualTo($from)) $to->addDay();
                $start = $start->max($from);
                $end = $end->min($to);
                if ($end->lessThanOrEqualTo($start)) continue;
            }
            $hours += $this->nightHours($start, $end);
            $amount += $this->calculateSegmented($start, $end, (float) $employee->salary, $employee, $ruleDate);
        }
        return ['hours' => round($hours, 2), 'amount' => round($amount, 2)];
    }

    private function calculateSegmented(Carbon $start, Carbon $end, float $monthlySalary, ?object $employee, ?string $ruleDate = null): float
    {
        $total = 0.0;
        $cursor = $start->copy()->startOfDay()->subDay();
        for ($i = 0; $i < 5 && $cursor->lessThan($end); $i++, $cursor->addDay()) {
            $date = $cursor->toDateString();
            $windowStart = $cursor->copy()->setTime(22, 0);
            $windowEnd = $cursor->copy()->addDay()->setTime(6, 0);
            $overlapStart = $start->greaterThan($windowStart) ? $start : $windowStart;
            $overlapEnd = $end->lessThan($windowEnd) ? $end : $windowEnd;
            if (! $overlapEnd->greaterThan($overlapStart)) continue;

            $hours = $overlapStart->diffInMinutes($overlapEnd) / 60;
            $premiumBase = $this->premiumMultiplier($employee, $date);
            $rate = Statutory::tableForDate('nsd', $ruleDate ?: $date)['rate'];
            // An hour is the day rate / 8 for day-rated staff (PHP 600 -> 75).
            $hourly = $employee && (float) ($employee->daily_rate ?? 0) > 0
                ? (float) $employee->daily_rate / 8
                : $monthlySalary / 22 / 8;
            $total += $hours * $hourly * $premiumBase * $rate;
        }
        return $total;
    }

    private function premiumMultiplier(?object $employee, string $date): float
    {
        if (! $employee) return 1.0;
        $shift = ShiftSchedule::forEmployeeDate($employee, $date);
        $holiday = DB::table('holidays')->whereDate('date', $date)->first();
        $type = $holiday ? (string) ($holiday->classification ?? ($holiday->type === 'special' ? 'special_non_working' : $holiday->type)) : null;

        return match (true) {
            $type === 'regular' && $shift['rest'] => 2.60,
            $type === 'regular' => 2.00,
            in_array($type, ['special_non_working', 'special'], true) && $shift['rest'] => 1.50,
            in_array($type, ['special_non_working', 'special'], true) => 1.30,
            $shift['rest'] => 1.30,
            default => 1.00,
        };
    }

    private function nightHours(Carbon $start, Carbon $end): float
    {
        $cursor = $start->copy()->startOfDay()->subDay();
        $total = 0.0;
        for ($i = 0; $i < 5 && $cursor->lessThan($end); $i++, $cursor->addDay()) {
            $windowStart = $cursor->copy()->setTime(22, 0);
            $windowEnd = $cursor->copy()->addDay()->setTime(6, 0);
            $overlapStart = $start->greaterThan($windowStart) ? $start : $windowStart;
            $overlapEnd = $end->lessThan($windowEnd) ? $end : $windowEnd;
            if ($overlapEnd->greaterThan($overlapStart)) $total += $overlapStart->diffInMinutes($overlapEnd) / 60;
        }
        return $total;
    }
}
