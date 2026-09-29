<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $takenQuery = DB::table('leaves')
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'pending', 'pending_hr'])
            ->whereBetween('start_date', [$year.'-01-01', $year.'-12-31']);

        if (Schema::hasColumn('leaves', 'pay_status')) {
            $takenQuery->where('pay_status', 'paid')->whereNotIn('leave_type', self::UNLIMITED_TYPES);
        } else {
            $takenQuery->whereNotIn('leave_type', self::UNLIMITED_TYPES);
        }

        $taken = $takenQuery
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
            $pending = (float) $taken->where('leave_type', $type)
                ->whereIn('status', ['pending', 'pending_hr'])
                ->sum('days');

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

        $balances = $this->applyPolicy($employeeId, $balances, $taken);
        ksort($balances);

        return $balances;
    }

    /**
     * The company's paid allowance for sick, vacation and emergency leave
     * (config/leave.php): supervisors a number of days per type, everybody
     * else one allowance shared by the three. Other types keep what their
     * entitlement row says.
     */
    private function applyPolicy(int $employeeId, array $balances, $taken): array
    {
        $types = config('leave.paid_types', ['sick', 'vacation', 'emergency']);
        $role = DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('e.employee_id', $employeeId)->value('u.role');
        $used = fn (string $type) => (float) $taken->where('leave_type', $type)->where('status', 'approved')->sum('days');
        $pending = fn (string $type) => (float) $taken->where('leave_type', $type)->whereIn('status', ['pending', 'pending_hr'])->sum('days');

        // Paid every time, so nothing is used up across the year.
        foreach (config('leave.per_occasion', []) as $type => $days) {
            $balances[$type] = [
                'entitled' => (float) $days, 'used' => $used($type), 'pending' => $pending($type),
                'remaining' => (float) $days, 'usedAgainst' => 0.0, 'eligible' => true,
                'afterMonths' => 0, 'shared' => false, 'perOccasion' => true,
            ];
        }

        if ($role === 'supervisor') {
            foreach ($types as $type) {
                $entitled = (float) (config('leave.supervisor_days_per_year')[$type] ?? 0);
                $balances[$type] = [
                    'entitled' => $entitled, 'used' => $used($type), 'pending' => $pending($type),
                    'remaining' => round($entitled - $used($type) - $pending($type), 1),
                    'usedAgainst' => $used($type), 'eligible' => true, 'afterMonths' => 0, 'shared' => false,
                ];
            }

            return $balances;
        }

        $entitled = (float) config('leave.employee_days_per_year', 7);
        $poolUsed = array_sum(array_map($used, $types));
        $poolPending = array_sum(array_map($pending, $types));
        foreach ($types as $type) {
            $balances[$type] = [
                'entitled' => $entitled, 'used' => $used($type), 'pending' => $pending($type),
                'remaining' => round($entitled - $poolUsed - $poolPending, 1),
                // What a request is weighed against: all three types together.
                'usedAgainst' => $poolUsed, 'eligible' => true, 'afterMonths' => 0, 'shared' => true,
            ];
        }

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
        if (! $this->isPaid($leave)) {
            return ['ok' => true, 'reason' => null];
        }

        $year = (int) substr((string) $leave->start_date, 0, 4);
        $balance = $this->forEmployee((int) $leave->employee_id, $year)[$leave->leave_type] ?? null;

        if (! $balance || $balance['entitled'] === null) {
            return ['ok' => true, 'reason' => null];
        }

        if (! $balance['eligible']) {
            return ['ok' => false, 'reason' => 'This leave is earned after '
                .$balance['afterMonths'].' months of service, which they have not reached yet.'];
        }

        // Shared allowances are weighed against everything taken from them.
        $available = $balance['entitled'] - ($balance['usedAgainst'] ?? $balance['used']);
        $asking = (float) $leave->total_days;

        if ($asking > $available) {
            return ['ok' => false, 'reason' => 'That is '.rtrim(rtrim(number_format($asking, 1), '0'), '.')
                .' day(s) against a remaining balance of '.rtrim(rtrim(number_format(max(0, $available), 1), '0'), '.')
                .' for '.$year.'.'];
        }

        return ['ok' => true, 'reason' => null];
    }

    public function payStatusForRequest(int $employeeId, string $type, float $days, string $requested, ?int $year = null): string
    {
        if ($requested === 'unpaid' || $type === 'unpaid') {
            return 'unpaid';
        }

        $balance = $this->forEmployee($employeeId, $year)[$type] ?? null;

        if (! $balance || $balance['entitled'] === null || ! $balance['eligible']) {
            return 'unpaid';
        }

        return $days <= max(0, (float) $balance['remaining']) ? 'paid' : 'unpaid';
    }

    public function isPaid(object $leave): bool
    {
        if (in_array((string) $leave->leave_type, self::UNLIMITED_TYPES, true)) {
            return false;
        }

        if (property_exists($leave, 'pay_status') && $leave->pay_status !== null) {
            return $leave->pay_status === 'paid';
        }

        return ! in_array((string) $leave->leave_type, self::UNLIMITED_TYPES, true);
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
