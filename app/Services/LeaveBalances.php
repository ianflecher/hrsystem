<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What somebody has left to take.
 *
 * Approved leave is paid, so a balance is the difference between a day off and
 * a day of somebody else's money. Days are counted against the calendar year
 * the leave starts in, and a request still waiting on a decision is held
 * against the balance too - otherwise three pending requests could each look
 * affordable on their own and overdraw the year together.
 *
 * A type with no entitlement entered has no limit rather than a limit of zero:
 * nothing here invents a company's policy, and unpaid leave never has one by
 * definition.
 */
class LeaveBalances
{
    /** Never limited: it costs the company nothing to grant. */
    public const UNLIMITED_TYPES = ['unpaid'];

    /** @return array<string, object> entitlement rows by leave type */
    public function entitlements(): array
    {
        return DB::table('leave_entitlements')->get()->keyBy('leave_type')->all();
    }

    /**
     * One employee's standing for a year, by leave type.
     *
     * @return array<string, array{entitled: ?float, used: float, pending: float,
     *                             remaining: ?float, eligible: bool, afterMonths: int}>
     */
    public function forEmployee(int $employeeId, ?int $year = null): array
    {
        $year = $year ?: (int) now()->year;
        $entitlements = $this->entitlements();

        $taken = DB::table('leaves')
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereBetween('start_date', [$year.'-01-01', $year.'-12-31'])
            ->selectRaw('leave_type, status, COALESCE(SUM(total_days), 0) as days')
            ->groupBy('leave_type', 'status')
            ->get();

        $months = $this->serviceMonths($employeeId);
        $types = array_unique(array_merge(array_keys($entitlements), $taken->pluck('leave_type')->all()));
        $balances = [];

        foreach ($types as $type) {
            $entitlement = $entitlements[$type] ?? null;
            $afterMonths = (int) ($entitlement->after_months ?? 0);
            $eligible = $months === null ? false : $months >= $afterMonths;

            $used = (float) $taken->where('leave_type', $type)->where('status', 'approved')->sum('days');
            $pending = (float) $taken->where('leave_type', $type)->where('status', 'pending')->sum('days');

            $entitled = $entitlement && ! in_array($type, self::UNLIMITED_TYPES, true)
                ? (float) $entitlement->days_per_year
                : null;

            $balances[$type] = [
                'entitled'    => $entitled,
                'used'        => $used,
                'pending'     => $pending,
                'remaining'   => $entitled === null ? null : round($entitled - $used - $pending, 1),
                'eligible'    => $entitlement ? $eligible : true,
                'afterMonths' => $afterMonths,
            ];
        }

        ksort($balances);

        return $balances;
    }

    /**
     * Whether a request can be approved, and why not when it cannot.
     *
     * The pending days of the request being decided are its own, so they are
     * added back before the comparison - otherwise a request would always be
     * counted against itself and nothing could ever be approved.
     *
     * @return array{ok: bool, reason: ?string}
     */
    public function canApprove(object $leave): array
    {
        $year = (int) substr((string) $leave->start_date, 0, 4);
        $balance = $this->forEmployee((int) $leave->employee_id, $year)[$leave->leave_type] ?? null;

        if (! $balance || $balance['entitled'] === null) {
            return ['ok' => true, 'reason' => null];
        }

        if (! $balance['eligible']) {
            return ['ok' => false, 'reason' => 'This leave is earned after '
                .$balance['afterMonths'].' months of service, which they have not reached yet.'];
        }

        $available = $balance['entitled'] - $balance['used'];
        $asking = (float) $leave->total_days;

        if ($asking > $available) {
            return ['ok' => false, 'reason' => 'That is '.rtrim(rtrim(number_format($asking, 1), '0'), '.')
                .' day(s) against a remaining balance of '.rtrim(rtrim(number_format(max(0, $available), 1), '0'), '.')
                .' for '.$year.'.'];
        }

        return ['ok' => true, 'reason' => null];
    }

    /** Whole months of service as of today, or null when the hire date is unknown. */
    public function serviceMonths(int $employeeId): ?int
    {
        $hired = DB::table('employees')->where('employee_id', $employeeId)->value('hire_date');

        if (! $hired) {
            return null;
        }

        return (int) Carbon::parse($hired)->diffInMonths(now());
    }
}
