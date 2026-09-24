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

    /**
     * Departments this person can supervise.
     *
     * A supervisor may be named directly on a department. A leader may not be
     * recorded there yet, so their own department is also treated as their team.
     *
     * @return array<int, int>
     */
    public static function managedDepartmentIds(): array
    {
        if (! auth()->check()) {
            return [];
        }

        if (self::isHr()) {
            return DB::table('departments')->pluck('department_id')->map(fn ($id) => (int) $id)->all();
        }

        if (! in_array(auth()->user()->role, ['supervisor', 'leader'], true)) {
            return [];
        }

        return DB::table('departments')
            ->where('supervisor_id', auth()->id())
            ->pluck('department_id')
            ->merge(DB::table('employees')->where('user_id', auth()->id())->pluck('department_id'))
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function managesEmployee(int $employeeId): bool
    {
        $employee = DB::table('employees')->where('employee_id', $employeeId)->first();

        if (! $employee) {
            return false;
        }

        if ((int) $employee->user_id === (int) auth()->id()) {
            return false;
        }

        return in_array((int) $employee->department_id, self::managedDepartmentIds(), true);
    }

    public static function managerForEmployee(int $employeeId): void
    {
        abort_unless(self::isHr() || self::managesEmployee($employeeId), 403);
    }

    public static function reviewOvertime(int $employeeId): void
    {
        $employee = DB::table('employees')->where('employee_id', $employeeId)->first();
        abort_unless($employee, 404);
        abort_if((int) $employee->user_id === (int) auth()->id(), 403, 'You cannot approve your own request.');
        abort_unless(self::isHr() || self::managesEmployee($employeeId), 403);
    }
}
