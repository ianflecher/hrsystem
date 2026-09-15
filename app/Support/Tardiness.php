<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The company's lateness rule, in one place.
 *
 *   up to 5 minutes late   no deduction
 *   6 to 14 minutes late   one hour of the daily rate
 *   15 minutes or more     half the daily rate
 *
 * A monthly salary is divided by 22 working days to get the daily rate, and the
 * daily rate by 8 to get the hourly one.
 *
 * It lives here rather than inside the payroll screen because the same rule has
 * to answer two different questions - what an arrival costs, and whether to
 * mark somebody late when they clock in - and those are asked from different
 * places.
 */
class Tardiness
{
    public const GRACE_MINUTES = 5;
    public const HOUR_PENALTY_UNTIL = 14;
    public const WORKING_DAYS_PER_MONTH = 22;
    public const HOURS_PER_DAY = 8;

    public static function dailyRate(float $monthlySalary): float
    {
        return $monthlySalary / self::WORKING_DAYS_PER_MONTH;
    }

    public static function hourlyRate(float $monthlySalary): float
    {
        return self::dailyRate($monthlySalary) / self::HOURS_PER_DAY;
    }

    /**
     * Minutes late, or null when lateness cannot be judged - no shift set, or
     * no clock-in recorded. Arriving early is not negative lateness; it is
     * simply not late.
     */
    public static function minutesLate(?CarbonInterface $timeIn, ?string $shiftStart): ?int
    {
        if (! $timeIn || ! $shiftStart) {
            return null;
        }

        $due = $timeIn->copy()->setTimeFromTimeString($shiftStart);

        return max(0, (int) floor($due->diffInSeconds($timeIn, false) / 60));
    }

    /**
     * What those minutes cost, given the monthly salary.
     */
    public static function deduction(?int $minutesLate, float $monthlySalary): float
    {
        if ($minutesLate === null || $minutesLate <= self::GRACE_MINUTES) {
            return 0.0;
        }

        if ($minutesLate <= self::HOUR_PENALTY_UNTIL) {
            return round(self::hourlyRate($monthlySalary), 2);
        }

        return round(self::dailyRate($monthlySalary) / 2, 2);
    }

    /**
     * How the deduction should be described on a payslip.
     */
    public static function describe(?int $minutesLate): string
    {
        if ($minutesLate === null || $minutesLate <= self::GRACE_MINUTES) {
            return 'within grace';
        }

        return $minutesLate <= self::HOUR_PENALTY_UNTIL ? 'one hour' : 'half day';
    }

    /**
     * Whether an arrival counts as late at all, for the attendance record.
     * Inside the grace period it does not.
     */
    public static function isLate(?CarbonInterface $timeIn, ?string $shiftStart): bool
    {
        $minutes = self::minutesLate($timeIn, $shiftStart);

        return $minutes !== null && $minutes > self::GRACE_MINUTES;
    }
}
