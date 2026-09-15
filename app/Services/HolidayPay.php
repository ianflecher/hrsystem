<?php

namespace App\Services;

use App\Support\Tardiness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The premium for working on a holiday.
 *
 * These staff are paid a monthly salary, which already covers the days they do
 * not work - a holiday nobody works is therefore already paid, and the absence
 * rule leaves it alone. What is missing is the extra for the people who did
 * come in.
 *
 *   regular holiday worked   one extra day's rate (the 200% rule, half of
 *                            which the monthly salary has already paid)
 *   special day worked       30% of a day's rate
 *
 * CHECK WITH YOUR ACCOUNTANT before the first run. This is the usual treatment
 * for monthly-paid staff, but the premium differs for daily-paid staff, for
 * rest days that fall on a holiday, and for overtime worked on one. Those cases
 * are not handled here, and the rates live in PREMIUMS so there is one place to
 * correct.
 */
class HolidayPay
{
    /** Extra day-rates earned by working the day, by holiday type. */
    public const PREMIUMS = ['regular' => 1.00, 'special' => 0.30];

    /**
     * @return array{amount: float, days: int, regularDays: int, specialDays: int}
     */
    public function forPeriod(object $employee, string $periodStart, string $periodEnd): array
    {
        $holidays = DB::table('holidays')->whereBetween('date', [$periodStart, $periodEnd])->get()
            ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        if ($holidays->isEmpty()) {
            return ['amount' => 0.0, 'days' => 0, 'regularDays' => 0, 'specialDays' => 0];
        }

        $worked = DB::table('hr_attendance')->where('employee_id', $employee->employee_id)
            ->whereBetween('date', [$periodStart, $periodEnd])->whereNotNull('time_in')
            ->get(['date'])->map(fn ($row) => substr((string) $row->date, 0, 10));

        $dailyRate = Tardiness::dailyRate((float) $employee->salary);
        $hired = $employee->hire_date ? substr((string) $employee->hire_date, 0, 10) : null;

        $amount = 0.0;
        $regularDays = 0;
        $specialDays = 0;

        foreach ($worked as $date) {
            $holiday = $holidays->get($date);

            if (! $holiday || ($hired && $date < $hired)) {
                continue;
            }

            $amount += $dailyRate * (self::PREMIUMS[$holiday->type] ?? 0);

            if ($holiday->type === 'regular') {
                $regularDays++;
            } else {
                $specialDays++;
            }
        }

        return [
            'amount'      => round($amount, 2),
            'days'        => $regularDays + $specialDays,
            'regularDays' => $regularDays,
            'specialDays' => $specialDays,
        ];
    }

    /** How a holiday reads on screen. */
    public static function describe(string $type): string
    {
        return $type === 'regular' ? 'Regular holiday' : 'Special non-working day';
    }

    /**
     * Carbon is only used here to keep the date handling in one style with the
     * rest of payroll; the dates themselves are compared as strings.
     */
    public static function isHoliday(string $date): bool
    {
        return DB::table('holidays')->whereDate('date', Carbon::parse($date)->toDateString())->exists();
    }
}
