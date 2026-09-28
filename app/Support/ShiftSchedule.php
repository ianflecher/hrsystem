<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftSchedule
{
    /**
     * Security guards stand 12-hour posts - 07:00 to 19:00, or 19:00 to 07:00
     * the next morning - where everybody else works eight hours.
     */
    public static function isGuard(?object $employee): bool
    {
        return $employee && stripos((string) ($employee->job_title ?? ''), 'security guard') !== false;
    }

    /** The most a normal day pays for, before approved overtime. */
    public static function dailyCapMinutes(?object $employee): int
    {
        return self::isGuard($employee) ? 720 : 480;
    }

    /** A shift that ends at or before it starts runs past midnight. */
    public static function isOvernight(?string $start, ?string $end): bool
    {
        return $start && $end && substr($end, 0, 5) <= substr($start, 0, 5);
    }

    /** @return array{rest: bool, start: ?string, end: ?string} */
    public static function forEmployeeDate(object $employee, string $date): array
    {
        $row = DB::table('shift_assignments')
            ->where('employee_id', $employee->employee_id)
            ->whereDate('work_date', $date)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'approved'))
            ->first();

        if ($row) {
            return [
                'rest' => (bool) $row->rest_day,
                'start' => $row->starts_at ? (string) $row->starts_at : null,
                'end' => $row->ends_at ? (string) $row->ends_at : null,
            ];
        }

        return [
            'rest' => WorkWeek::restsOn($employee->rest_days ?? null, \Carbon\Carbon::parse($date)),
            'start' => $employee->shift_start ? (string) $employee->shift_start : null,
            'end' => $employee->shift_end ? (string) $employee->shift_end : null,
        ];
    }

    /**
     * @return Collection<string, object>
     */
    public static function mapForEmployeeDates(int $employeeId, array $dates): Collection
    {
        if (! $dates) {
            return collect();
        }

        return DB::table('shift_assignments')
            ->where('employee_id', $employeeId)
            ->whereIn('work_date', $dates)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'approved'))
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->work_date, 0, 10));
    }
}
