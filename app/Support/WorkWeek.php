<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Which days somebody works.
 *
 * A schedule used to be assigned date by date, which meant a calendar only
 * existed for the days HR had remembered to fill in. It is now a property of
 * the employee - their shift times and their rest days - so the calendar draws
 * itself for any month, and the only thing left to enter is the holidays,
 * which are the company's and not any one person's.
 *
 * Rest days are stored as ISO weekday numbers (Monday 1 ... Sunday 7) joined by
 * commas, because a person's rest days are read far more often than written and
 * this keeps them on the employee row rather than in a second table.
 */
class WorkWeek
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** @return array<int, int> ISO weekday numbers, sorted, no duplicates */
    public static function days(?string $restDays): array
    {
        if ($restDays === null || trim($restDays) === '') {
            return [];
        }

        $days = array_filter(
            array_map('intval', explode(',', $restDays)),
            fn ($day) => $day >= 1 && $day <= 7
        );

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    /** Normalised for storage; null when nothing is set. */
    public static function store(?array $days): ?string
    {
        $days = self::days($days ? implode(',', $days) : null);

        return $days === [] ? null : implode(',', $days);
    }

    public static function restsOn(?string $restDays, int|CarbonInterface $day): bool
    {
        $iso = $day instanceof CarbonInterface ? $day->dayOfWeekIso : $day;

        return in_array($iso, self::days($restDays), true);
    }

    /** How the rest days read on screen, e.g. "Saturday, Sunday". */
    public static function label(?string $restDays): string
    {
        $days = self::days($restDays);

        if ($days === []) {
            return 'None set';
        }

        return implode(', ', array_map(fn ($day) => self::DAYS[$day], $days));
    }
}
