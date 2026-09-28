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
     * Minutes worked: the span from first in to final out, less a fixed break,
     * capped at eight hours.
     */
    public static function workedMinutes(?object $row, int $assumedBreakMinutes = 60): int
    {
        $punches = self::punches($row);
        $in = $punches['time_in'];
        $out = $punches['time_out'];

        if (! $in || ! $out || ! $out->greaterThan($in)) {
            return 0;
        }

        $span = (int) round($in->diffInMinutes($out));

        return min(480, (int) max(0, $span - max(0, $assumedBreakMinutes)));
    }

    public static function workedHours(?object $row, int $assumedBreakMinutes = 60): float
    {
        return round(self::workedMinutes($row, $assumedBreakMinutes) / 60, 2);
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

        if ($punches['time_in'] && ! $punches['time_out']) {
            $problems[] = 'No final out';
        }

        return array_values(array_unique($problems));
    }
}
