<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PeopleAccess
{
    public static function isHr(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'hr'], true);
    }

    public static function hr(): void
    {
        abort_unless(self::isHr(), 403);
    }

    public static function isManager(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin','hr','supervisor','leader'], true);
    }

    public static function manager(): void
    {
        abort_unless(self::isManager(), 403);
    }

    public static function employeeId(): int
    {
        $id = DB::table('employees')->where('user_id', auth()->id())->value('employee_id');
        abort_unless($id, 403, 'An employee record is required.');
        return (int) $id;
    }

    public static function ownOrHr(int $employeeId): void
    {
        abort_unless(self::isHr() || self::employeeId() === $employeeId, 403);
    }

    public static function reviewOvertime(int $employeeId): void
    {
        $employee = DB::table('employees')->where('employee_id', $employeeId)->first();
        abort_unless($employee, 404);
        abort_if((int) $employee->user_id === (int) auth()->id(), 403, 'You cannot approve your own request.');
        $supervisor = auth()->user()->role === 'supervisor'
            && DB::table('departments')->where('department_id', $employee->department_id)
                ->where('supervisor_id', auth()->id())->exists();
        abort_unless(self::isHr() || $supervisor, 403);
    }
}
