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

        // A leader answers to the supervisor, not the other way round: a
        // supervisor's own requests go to HR, never to a leader beneath them.
        if (! self::isHr() && auth()->user()->role === 'leader'
            && DB::table('users')->where('user_id', $employee->user_id)->value('role') === 'supervisor') {
            return false;
        }

        return in_array((int) $employee->department_id, self::managedDepartmentIds(), true);
    }

    /** The one person who decides supervisors' leave before HR (config/leave.php). */
    public static function isSupervisorLeaveApprover(): bool
    {
        return auth()->check() && (int) auth()->id() === (int) config('leave.supervisor_approver_user_id');
    }

    /**
     * Whose leave this person decides: their own team's - except a
     * supervisor's, which is only ever the supervisors' approver's.
     */
    public static function decidesLeaveOf(int $employeeId): bool
    {
        $userId = DB::table('employees')->where('employee_id', $employeeId)->value('user_id');
        if (! $userId || (int) $userId === (int) auth()->id()) {
            return false;
        }
        if (DB::table('users')->where('user_id', $userId)->value('role') === 'supervisor') {
            return self::isSupervisorLeaveApprover();
        }

        return self::managesEmployee($employeeId);
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
