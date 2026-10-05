<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * One day's punches, and what they add up to.
 *
 * A day is six: first in, lunch in, lunch out, cb in, cb out, final out. Every
 * screen that shows a day and every calculation that pays for one goes through
 * here, so the attendance table, the payslip and the employee's own screen
 * cannot disagree about how long somebody worked.
 *
 * Work hours are counted from first in to final out, then a fixed lunch break
 * is deducted and the paid day is capped at eight hours. Lunch and CB punches
 * are kept for display and checking, but their exact scanned duration does not
 * change the payable total.
 */
class WorkDay
{
    /** Less than four hours is not paid as a worked day; four (a half day) is. */
    public const MINIMUM_PAID_MINUTES = 240;

    /** In the order they happen, which is the order they are shown. */
    public const PUNCHES = [
        'time_in'   => 'First in',
        'lunch_in'  => 'Lunch in',
        'lunch_out' => 'Lunch out',
        'cb_in'     => 'CB in',
        'cb_out'    => 'CB out',
        'time_out'  => 'Final out',
    ];

    /** The two spans that come out of the paid day. */
    private const BREAKS = [
        ['lunch_in', 'lunch_out'],
        ['cb_in', 'cb_out'],
    ];

    /**
     * The six punches of a row, in order, as Carbon or null.
     *
     * @return array<string, ?Carbon>
     */
    public static function punches(?object $row): array
    {
        $out = [];

        foreach (array_keys(self::PUNCHES) as $column) {
            $value = $row->{$column} ?? null;
            $out[$column] = $value ? Carbon::parse($value) : null;
        }

        return $out;
    }

    /**
     * Minutes of break actually punched.
     *
     * A break with only one of its two punches counts as nothing: somebody who
     * went to lunch and never came back has a problem to look at, not a break
     * to deduct, and guessing at its length would quietly pay them for it or
     * dock them for it depending on which way the guess fell.
     */
    public static function breakMinutes(?object $row): int
    {
        $punches = self::punches($row);
        $minutes = 0;

        foreach (self::BREAKS as [$start, $end]) {
            $from = $punches[$start];
            $to = $punches[$end];

            if ($from && $to && $to->greaterThan($from)) {
                // Carbon returns a float here, and adding it to an int is
                // deprecated - it truncates silently, so a break is rounded
                // down by up to a minute without anybody being told.
                $minutes += (int) round($from->diffInMinutes($to));
            }
        }

        return $minutes;
    }

    /** Whether any break was punched at all, as opposed to assumed. */
    public static function hasPunchedBreak(?object $row): bool
    {
        $punches = self::punches($row);

        foreach (self::BREAKS as [$start, $end]) {
            if ($punches[$start] && $punches[$end]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Final out for payroll/display. If the final-out key was missed, use the
     * latest later punch from lunch/CB as the best available out time.
     */
    public static function effectiveOut(?object $row): ?Carbon
    {
        $punches = self::punches($row);
        $in = $punches['time_in'];
        $out = $punches['time_out'];

        foreach (['lunch_in', 'lunch_out', 'cb_in', 'cb_out'] as $slot) {
            $punch = $punches[$slot];

            if ($punch && $in && $punch->greaterThan($in) && (! $out || $punch->greaterThan($out))) {
                $out = $punch;
            }
        }

        return $out;
    }

    /**
     * Minutes worked: the span from first in to final out, less a fixed break,
     * capped at eight hours.
     *
     * Pass the employee and a security guard is read on their own terms: a
     * twelve-hour post with no break taken off - they do not leave it - and
     * the cap at twelve. The timestamps are real ones, so a night duty that
     * ends the next morning is simply a longer span.
     */
    public static function workedMinutes(?object $row, int $assumedBreakMinutes = 60, ?object $employee = null): int
    {
        $punches = self::punches($row);
        $in = $punches['time_in'];
        $out = self::effectiveOut($row);

        if (! $in || ! $out || ! $out->greaterThan($in)) {
            return 0;
        }

        $span = (int) round($in->diffInMinutes($out));
        $guard = ShiftSchedule::isGuard($employee);
        $break = $guard || self::leftBeforeLunch($row, $span) ? 0 : max(0, $assumedBreakMinutes);

        $worked = (int) max(0, $span - $break);

        if ($worked < self::MINIMUM_PAID_MINUTES) {
            return 0;
        }

        return min(ShiftSchedule::dailyCapMinutes($employee), $worked);
    }

    /**
     * Gone before lunch: no lunch punched and in for about four hours or less (out by a quarter past)
     * (a morning half day, 8 to 12). No lunch hour comes off a day without one.
     */
    public static function leftBeforeLunch(?object $row, int $spanMinutes): bool
    {
        $p = self::punches($row);

        return ! $p['lunch_in'] && ! $p['lunch_out'] && $spanMinutes <= 255;
    }

    public static function workedHours(?object $row, int $assumedBreakMinutes = 60, ?object $employee = null): float
    {
        return round(self::workedMinutes($row, $assumedBreakMinutes, $employee) / 60, 2);
    }

    /**
     * Punches that do not make sense together, in words somebody can act on.
     *
     * Not thrown and not blocking: a scanner misread or a forgotten punch is
     * ordinary, and the row still has to be saved and shown. HR needs to be
     * told which day to look at, not stopped from looking at it.
     *
     * @return list<string>
     */
    public static function problems(?object $row): array
    {
        $punches = self::punches($row);
        $problems = [];

        foreach (self::BREAKS as [$start, $end]) {
            $from = $punches[$start];
            $to = $punches[$end];

            if ($from && ! $to) {
                $problems[] = self::PUNCHES[$start].' with no '.lcfirst(self::PUNCHES[$end]);
            }

            if (! $from && $to) {
                $problems[] = self::PUNCHES[$end].' with no '.lcfirst(self::PUNCHES[$start]);
            }

            if ($from && $to && $to->lessThan($from)) {
                $problems[] = self::PUNCHES[$end].' is before '.lcfirst(self::PUNCHES[$start]);
            }
        }

        // Out of order overall: each punch should follow the one before it.
        $previousKey = null;
        $previous = null;

        foreach ($punches as $key => $moment) {
            if (! $moment) {
                continue;
            }

            if ($previous && $moment->lessThan($previous)) {
                $problems[] = self::PUNCHES[$key].' is before '.lcfirst(self::PUNCHES[$previousKey]);
            }

            $previousKey = $key;
            $previous = $moment;
        }

        if ($punches['time_in'] && ! self::effectiveOut($row)) {
            $problems[] = 'No final out';
        }

        return array_values(array_unique($problems));
    }
}
