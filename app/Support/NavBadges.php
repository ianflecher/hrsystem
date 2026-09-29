<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The counts that sit on the sidebar.
 *
 * A badge is a claim that something is waiting for the person reading it, so
 * only work that is genuinely theirs to do gets one. Counting everything would
 * be easy and would teach people to ignore the numbers - a badge on every line
 * is the same as a badge on none.
 *
 * So: things that are pending a decision, and the person who has to make it.
 * Not totals, not "how many employees are there", and nothing that is merely
 * interesting.
 *
 * Both sidebars render on every page, so each figure is a single indexed
 * count, and the whole set is worked out once per request.
 */
class NavBadges
{
    /** @var array<string, array<string, int>>|null */
    private static ?array $cache = null;

    /**
     * What is waiting in the back office.
     *
     * @return array<string, int>
     */
    public static function hr(): array
    {
        return self::remember('hr', fn () => [
            // Applications that need HR to do something next. Counting only
            // "pending" read as broken, because it stayed at zero while work
            // plainly sat there: an application reviewed a week ago with no
            // interview booked is waiting on HR just as much as one nobody
            // has opened.
            //
            // Three things qualify, and nothing settled does:
            //   - nobody has looked at it yet
            //   - reviewed, but no interview arranged
            //   - every interview answered, and still no decision
            'hr.applications' => (int) DB::table('job_applications as ja')
                ->where(function ($q) {
                    $q->where('ja.status', 'pending')
                        ->orWhere(function ($q) {
                            $q->where('ja.status', 'reviewed')
                                ->whereNotExists(fn ($e) => $e->select(DB::raw(1))
                                    ->from('application_interviews as ai')
                                    ->whereColumn('ai.application_id', 'ja.application_id')
                                    ->where('ai.status', '!=', 'cancelled'));
                        })
                        ->orWhere(function ($q) {
                            $q->where('ja.status', 'reviewed')
                                ->whereExists(fn ($e) => $e->select(DB::raw(1))
                                    ->from('application_interviews as ai')
                                    ->whereColumn('ai.application_id', 'ja.application_id')
                                    ->where('ai.status', '!=', 'cancelled'))
                                ->whereNotExists(fn ($e) => $e->select(DB::raw(1))
                                    ->from('application_interviews as ai')
                                    ->whereColumn('ai.application_id', 'ja.application_id')
                                    ->where('ai.status', '!=', 'cancelled')
                                    ->whereNull('ai.recommendation'));
                        });
                })
                ->count(),

            // Leave already checked by the team lead and waiting for HR.
            'hr.leave' => (int) DB::table('leaves')->where('status', 'pending_hr')->count(),

            // Payslips calculated and waiting on approval.
            'hr.payroll' => (int) DB::table('hr_payroll')->where('status', 'calculated')->count(),

            // Overtime the same.
            'overtime' => (int) DB::table('overtime_requests')->where('status', 'pending_hr')->count(),
        ]);
    }

    /**
     * What is waiting for the signed-in member of staff.
     *
     * @return array<string, int>
     */
    public static function staff(): array
    {
        $id = Auth::id();

        if (! $id) {
            return [];
        }

        return self::remember('staff', function () use ($id) {
            $departmentIds = PeopleAccess::managedDepartmentIds();

            // Only what this person can actually decide: never their own
            // request, and a supervisor's leave only for its approver.
            $leaveIds = DB::table('leaves')->where('status', 'pending')->pluck('employee_id');
            $otIds = $departmentIds
                ? DB::table('overtime_requests as o')->join('employees as e', 'e.employee_id', '=', 'o.employee_id')
                    ->where('o.status', 'pending')->whereIn('e.department_id', $departmentIds)->pluck('o.employee_id')
                : collect();
            $teamPending = $leaveIds->filter(fn ($id) => PeopleAccess::decidesLeaveOf((int) $id))->count()
                + $otIds->filter(fn ($id) => PeopleAccess::managesEmployee((int) $id))->count();

            return [
            // Interviews given to them that they have not reported on. Whether
            // the interview has happened yet is not the test: an unanswered
            // one is outstanding either way, and a supervisor should see it
            // coming rather than only once it is late.
            'employee.interviews' => (int) DB::table('application_interviews')
                ->where('interviewer_id', $id)
                ->where('status', '!=', 'cancelled')
                ->whereNull('recommendation')
                ->count(),

            'hr.operations.manager' => $teamPending,
            'employee.team' => $teamPending,
            ];
        });
    }

    /**
     * Whether this person conducts interviews at all.
     *
     * "My interviews" is the interviewer's screen - the candidates somebody
     * has been asked to meet - and it was in the sidebar for everybody. A
     * sewer with no interviews to give got a menu item that reads as though it
     * is about their own, opens on an empty page, and never changes. It shows
     * only for somebody who has actually been assigned one.
     */
    public static function conductsInterviews(): bool
    {
        $id = Auth::id();

        if (! $id) {
            return false;
        }

        return self::remember('conducts-interviews', fn () => [
            'yes' => DB::table('application_interviews')
                ->where('interviewer_id', $id)
                ->where('status', '!=', 'cancelled')
                ->exists(),
        ])['yes'];
    }

    /** One count per request, however many times the sidebar asks. */
    private static function remember(string $key, callable $make): array
    {
        self::$cache ??= [];

        return self::$cache[$key] ??= $make();
    }

    /** Lets a test see a fresh count after it has changed the data. */
    public static function forget(): void
    {
        self::$cache = null;
    }
}
