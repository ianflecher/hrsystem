<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftSchedule
{
    /** @return array{rest: bool, start: ?string, end: ?string} */
    public static function forEmployeeDate(object $employee, string $date): array
    {
        $row = DB::table('shift_assignments')
            ->where('employee_id', $employee->employee_id)
            ->whereDate('work_date', $date)
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
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->work_date, 0, 10));
    }
}
