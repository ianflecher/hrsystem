<?php

namespace App\Services;

use App\Support\ShiftSchedule;
use App\Support\Statutory;
use App\Support\Tardiness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calculates a reviewable Philippine overtime amount from attendance context.
 * HR can still override the suggestion; the reason is retained on the request.
 */
class PhilippineOvertime
{
    public function suggest(object $employee, string $startsAt, string $endsAt): array
    {
        $start = Carbon::parse($startsAt);
        $end = Carbon::parse($endsAt);
        if ($end->lessThanOrEqualTo($start)) $end->addDay();

        $hours = intdiv(max(0, (int) $start->diffInMinutes($end)), 60);
        $minutes = $hours * 60;
        $date = $start->toDateString();
        $shift = ShiftSchedule::forEmployeeDate($employee, $date);
        $holiday = DB::table('holidays')->whereDate('date', $date)->first();
        $classification = $holiday ? (string) ($holiday->classification ?? ($holiday->type === 'special' ? 'special_non_working' : 'regular')) : null;

        // Pay is set by the day: an hour is the day rate / 8 (PHP 600 -> 75).
        // Only somebody still on a monthly salary is worked back from it.
        $base = (float) ($employee->daily_rate ?? 0) > 0
            ? (float) $employee->daily_rate / Tardiness::HOURS_PER_DAY
            : Tardiness::hourlyRate((float) $employee->salary);
        $multiplier = match (true) {
            $classification === 'regular' && $shift['rest'] => 3.38,
            $classification === 'regular' => 2.60,
            in_array($classification, ['special_non_working', 'special'], true) && $shift['rest'] => 1.95,
            in_array($classification, ['special_non_working', 'special'], true) => 1.69,
            $shift['rest'] => 1.69,
            default => 1.25,
        };

        $amount = round($hours * $base * $multiplier, 2);

        return [
            'minutes' => $minutes,
            'hours' => round($hours, 2),
            'hourly_rate' => round($base, 2),
            'multiplier' => $multiplier,
            'holiday_classification' => $classification,
            'rest_day' => (bool) $shift['rest'],
            'suggested_amount' => $amount,
            // Unrounded, so a cutoff's overtime is rounded once, at the total.
            'exact_amount' => $hours * $base * $multiplier,
            'rule_version' => Statutory::snapshot($date)['version'],
        ];
    }
}
