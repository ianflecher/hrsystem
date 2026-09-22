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
            // New applications nobody has looked at.
            'hr.applications' => (int) DB::table('job_applications')->where('status', 'pending')->count(),

            // Leave asked for and not yet answered.
            'hr.leave' => (int) DB::table('leaves')->where('status', 'pending')->count(),

            // Payslips calculated and waiting on approval.
            'hr.payroll' => (int) DB::table('hr_payroll')->where('status', 'calculated')->count(),

            // Overtime the same.
            'overtime' => (int) DB::table('overtime_requests')->where('status', 'pending')->count(),
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

        return self::remember('staff', fn () => [
            // Interviews given to them that they have not reported on. Whether
            // the interview has happened yet is not the test: an unanswered
            // one is outstanding either way, and a supervisor should see it
            // coming rather than only once it is late.
            'employee.interviews' => (int) DB::table('application_interviews')
                ->where('interviewer_id', $id)
                ->where('status', '!=', 'cancelled')
                ->whereNull('recommendation')
                ->count(),
        ]);
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
