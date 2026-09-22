<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Every change to somebody's pay, kept as a log rather than an overwrite.
 *
 * employee_salary_history already existed and the employee profile already
 * read it. Nothing had ever written to it, so a raise replaced the old figure
 * and the old figure was gone: no record of what somebody used to earn, when
 * it changed, who changed it, or why. When a person disputes a payslip - or a
 * DOLE inspector asks - "the system says so" is not an answer.
 *
 * The rows are closed off rather than deleted, so the table reads as a
 * timeline: each period has an effective_from, and every period but the
 * current one has an effective_until.
 */
class SalaryHistory
{
    /**
     * Records a pay period for an employee, closing the one before it.
     *
     * Returns false when nothing about the pay actually changed, so that
     * editing somebody's shift or department does not litter the log with
     * entries that say nothing.
     */
    public static function record(
        int $employeeId,
        float $salary,
        float $allowance = 0.0,
        string $payBasis = 'monthly',
        ?float $dailyRate = null,
        ?string $effectiveFrom = null,
        ?string $reason = null,
    ): bool {
        $effectiveFrom ??= now()->toDateString();

        $current = DB::table('employee_salary_history')
            ->where('employee_id', $employeeId)
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->first();

        if ($current && self::same($current, $salary, $allowance, $payBasis, $dailyRate)) {
            return false;
        }

        DB::transaction(function () use ($employeeId, $salary, $allowance, $payBasis, $dailyRate, $effectiveFrom, $reason, $current) {
            if ($current && $current->effective_until === null) {
                // The old rate runs up to the day before the new one starts.
                // A raise effective today means yesterday was the last day on
                // the old figure, and a same-day correction closes at the same
                // date rather than going backwards.
                $until = \Carbon\Carbon::parse($effectiveFrom)->subDay()->toDateString();

                DB::table('employee_salary_history')->where('id', $current->id)->update([
                    'effective_until' => max($until, (string) $current->effective_from),
                    'updated_at' => now(),
                ]);
            }

            DB::table('employee_salary_history')->insert([
                'employee_id'     => $employeeId,
                'salary'          => $salary,
                'allowance'       => $allowance,
                'pay_basis'       => $payBasis,
                'daily_rate'      => $dailyRate,
                'effective_from'  => $effectiveFrom,
                'effective_until' => null,
                'reason'          => $reason,
                'changed_by'      => auth()->id(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        });

        Auditor::record('pay_changed', 'employees', $employeeId,
            $current ? [
                'salary' => (float) $current->salary,
                'allowance' => (float) ($current->allowance ?? 0),
                'pay_basis' => $current->pay_basis,
                'daily_rate' => $current->daily_rate === null ? null : (float) $current->daily_rate,
            ] : null,
            [
                'salary' => $salary, 'allowance' => $allowance,
                'pay_basis' => $payBasis, 'daily_rate' => $dailyRate,
                'effective_from' => $effectiveFrom, 'reason' => $reason,
            ]);

        return true;
    }

    /** Nothing about the pay differs, so there is nothing worth logging. */
    private static function same(object $row, float $salary, float $allowance, string $payBasis, ?float $dailyRate): bool
    {
        return self::cents($row->salary) === self::cents($salary)
            && self::cents($row->allowance ?? 0) === self::cents($allowance)
            && (string) $row->pay_basis === $payBasis
            && self::cents($row->daily_rate) === self::cents($dailyRate);
    }

    private static function cents(mixed $amount): ?int
    {
        return $amount === null ? null : (int) round(((float) $amount) * 100);
    }

    /** The pay timeline for one employee, newest first. */
    public static function forEmployee(int $employeeId)
    {
        return DB::table('employee_salary_history as h')
            ->leftJoin('users as u', 'u.user_id', '=', 'h.changed_by')
            ->where('h.employee_id', $employeeId)
            ->orderByDesc('h.effective_from')->orderByDesc('h.id')
            ->select('h.*', 'u.full_name as changed_by_name')
            ->get();
    }

    /** What somebody was on for a given date, for recomputing an old payslip. */
    public static function onDate(int $employeeId, string $date): ?object
    {
        return DB::table('employee_salary_history')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->first();
    }
}
