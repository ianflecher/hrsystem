<?php

namespace App\Support;

/**
 * People kept out of the attendance summaries: the owners' side of the
 * company, who are not on the scanner or the schedule - the president/CEO and
 * the corporate secretary. Their records stay; they are only not counted.
 */
class NotInSummaries
{
    /** Employee numbers. */
    public const NUMBERS = ['IC-00001', 'IC-00002'];

    /** Whether this employee is one of them. */
    public static function covers(?object $employee): bool
    {
        return $employee && in_array((string) ($employee->employee_no ?? ''), self::NUMBERS, true);
    }

    /** Leaves them out of an employees query aliased $alias. */
    public static function scope($query, string $alias = 'e')
    {
        return $query->where(fn ($q) => $q->whereNull($alias.'.employee_no')->orWhereNotIn($alias.'.employee_no', self::NUMBERS));
    }
}
