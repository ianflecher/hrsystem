<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Leaving before the shift ends, charged the same way arriving late is.
 *
 *   up to 5 minutes early   no deduction
 *   6 to 14 minutes early   one hour of the daily rate
 *   15 minutes or more      half the daily rate
 *
 * ASSUMPTION: no separate undertime rule was given, so this mirrors the
 * lateness one - same grace, same brackets, same rates - because an hour
 * missing off the end of a day costs the company what an hour missing off the
 * start does. If undertime should instead be charged by the minute, or with a
 * different grace, this class is the only thing that changes.
 *
 * A day with no clock-out is not undertime: nobody knows when they left, and
 * guessing would take money off somebody for a scanner that missed a scan.
 */
class Undertime
{
    /**
     * Minutes short at the end of the day, or null when it cannot be judged -
     * no shift end set, or no clock-out recorded. Staying late is not negative
     * undertime; it is simply not undertime.
     *
     * The shift end is read against the date the person clocked out, so a night
     * shift that ends at 06:00 works out correctly for somebody leaving that
     * morning.
     */
    public static function minutesShort(?CarbonInterface $timeOut, ?string $shiftEnd): ?int
    {
        if (! $timeOut || ! $shiftEnd) {
            return null;
        }

        $due = $timeOut->copy()->setTimeFromTimeString($shiftEnd);

        return max(0, (int) floor($timeOut->diffInSeconds($due, false) / 60));
    }

    /**
     * What those minutes cost, given the monthly salary.
     */
    public static function deduction(?int $minutesShort, float $monthlySalary): float
    {
        if ($minutesShort === null || $minutesShort <= Tardiness::GRACE_MINUTES) {
            return 0.0;
        }

        if ($minutesShort <= Tardiness::HOUR_PENALTY_UNTIL) {
            return round(Tardiness::hourlyRate($monthlySalary), 2);
        }

        return round(Tardiness::dailyRate($monthlySalary) / 2, 2);
    }

    public static function describe(?int $minutesShort): string
    {
        if ($minutesShort === null || $minutesShort <= Tardiness::GRACE_MINUTES) {
            return 'within grace';
        }

        return $minutesShort <= Tardiness::HOUR_PENALTY_UNTIL ? 'one hour' : 'half day';
    }

    /** Whether leaving then counts as undertime at all. */
    public static function isShort(?CarbonInterface $timeOut, ?string $shiftEnd): bool
    {
        $minutes = self::minutesShort($timeOut, $shiftEnd);

        return $minutes !== null && $minutes > Tardiness::GRACE_MINUTES;
    }

    /**
     * Lateness and undertime on the same day cannot cost more than the day is
     * worth. Half a day each is the most the two rules can produce, so this is
     * a floor under a future change to either bracket rather than something
     * that bites today.
     */
    public static function capDay(float $lateCost, float $undertimeCost, float $monthlySalary): float
    {
        return round(min($lateCost + $undertimeCost, Tardiness::dailyRate($monthlySalary)), 2);
    }
}
