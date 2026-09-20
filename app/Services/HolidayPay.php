<?php

namespace App\Services;

use App\Support\Statutory;
use App\Support\Tardiness;
use App\Support\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HolidayPay
{
    /**
     * Return only the premium above the ordinary monthly-paid wage.
     * The calculation is based on the hours actually worked on each holiday,
     * capped at eight ordinary hours per holiday date. Overnight attendance is
     * split across calendar dates so a shift crossing midnight is not assigned
     * wholly to the day it started.
     */
    public function forPeriod(object $employee, string $periodStart, string $periodEnd): array
    {
        $holidays = DB::table('holidays')->whereBetween('date', [
            Carbon::parse($periodStart)->subDay()->toDateString(),
            Carbon::parse($periodEnd)->addDay()->toDateString(),
        ])->get()->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        if ($holidays->isEmpty()) {
            return ['amount'=>0.0,'days'=>0,'regularDays'=>0,'specialDays'=>0,'specialWorkingDays'=>0];
        }

        $attendance = DB::table('hr_attendance')
            ->where('employee_id', $employee->employee_id)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereNotNull('time_in')->whereNotNull('time_out')->get();

        $dailyRate = Tardiness::dailyRate((float) $employee->salary);
        $hourlyRate = $dailyRate / Tardiness::HOURS_PER_DAY;
        $hired = $employee->hire_date ? substr((string) $employee->hire_date, 0, 10) : null;
        $amount = 0.0; $regularDays = 0; $specialDays = 0; $specialWorkingDays = 0;
        $seen = [];
        $rules = Statutory::tableForDate('holiday', $periodStart);

        foreach ($attendance as $row) {
            $start = Carbon::parse($row->time_in);
            $end = Carbon::parse($row->time_out);
            if ($end->lessThanOrEqualTo($start)) $end->addDay();

            for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
                $dateKey = $date->toDateString();
                if ($dateKey < $periodStart || $dateKey > $periodEnd || ($hired && $dateKey < $hired)) continue;
                $holiday = $holidays->get($dateKey);
                if (! $holiday) continue;

                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->addDay()->startOfDay();
                $overlapStart = $start->greaterThan($dayStart) ? $start : $dayStart;
                $overlapEnd = $end->lessThan($dayEnd) ? $end : $dayEnd;
                $hours = $overlapEnd->greaterThan($overlapStart) ? $overlapStart->diffInMinutes($overlapEnd) / 60 : 0;
                if ($hours <= 0) continue;
                $hours = min(8.0, $hours);

                $shift = ShiftSchedule::forEmployeeDate($employee, $dateKey);
                $rest = $shift['rest'];
                $type = (string) ($holiday->classification ?? ($holiday->type === 'special' ? 'special_non_working' : $holiday->type));
                $multiplier = match ($type) {
                    'regular' => $rest ? $rules['regular_rest'] : $rules['regular'],
                    'special_non_working', 'special' => $rest ? $rules['special_non_working_rest'] : $rules['special_non_working'],
                    'special_working' => $rules['special_working'],
                    default => 1.00,
                };

                $amount += $hourlyRate * $hours * max(0, $multiplier - 1.00);
                if (! isset($seen[$dateKey])) {
                    $seen[$dateKey] = true;
                    if ($type === 'regular') $regularDays++; elseif ($type === 'special_working') $specialWorkingDays++; else $specialDays++;
                }
            }
        }

        return [
            'amount'=>round($amount,2),
            'days'=>count($seen),
            'regularDays'=>$regularDays,
            'specialDays'=>$specialDays,
            'specialWorkingDays'=>$specialWorkingDays,
        ];
    }

    public static function describe(string $type): string
    {
        return match ($type) {
            'regular' => 'Regular holiday',
            'special_working' => 'Special working day',
            default => 'Special non-working day',
        };
    }

    public static function isHoliday(string $date): bool
    {
        return DB::table('holidays')->whereDate('date', Carbon::parse($date)->toDateString())->exists();
    }
}
