<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 13th month pay: one twelfth of the basic salary earned in a calendar year.
 *
 * "Basic salary earned" is the phrase that does the work. It is not the annual
 * rate, and not what was paid out: overtime, the holiday premium and days not
 * worked all come out of it, so somebody who was absent for a month earns a
 * smaller 13th month, which is the point of the rule.
 *
 * Contributions and tax are not subtracted - those come out of pay, they are
 * not a reduction in what was earned.
 *
 * TAX: the first PHP 90,000 of 13th month and other benefits in a year is
 * exempt. Anything above that is taxable, and withholding it correctly means
 * looking at the whole year's compensation, which this does not do - so the
 * excess is flagged for somebody to handle rather than guessed at. Nothing is
 * withheld here.
 *
 * It is payable on or before 24 December.
 */
class ThirteenthMonth
{
    public const TAX_EXEMPT_CEILING = 90000.0;

    /** Payslips of this kind are the 13th month itself, and never its own base. */
    public const KIND = '13th_month';

    /**
     * What one employee earned as basic salary during the year, and the
     * resulting 13th month.
     *
     * @return array{basic: float, amount: float, payslips: int, taxableExcess: float}
     */
    public function forEmployee(int $employeeId, int $year): array
    {
        $row = DB::table('hr_payroll')
            ->where('employee_id', $employeeId)
            ->where('kind', 'regular')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('period_start', [$year.'-01-01', $year.'-12-31'])
            ->selectRaw('COUNT(*) as payslips, COALESCE(SUM(gross_pay - overtime_pay - holiday_pay - time_deduction), 0) as basic')
            ->first();

        $basic = round(max(0, (float) ($row->basic ?? 0)), 2);
        $amount = round($basic / 12, 2);

        return [
            'basic'         => $basic,
            'amount'        => $amount,
            'payslips'      => (int) ($row->payslips ?? 0),
            'taxableExcess' => round(max(0, $amount - self::TAX_EXEMPT_CEILING), 2),
        ];
    }

    /**
     * Everybody who has earned something in the year, with what they are owed
     * and whether it has already been recorded.
     */
    public function forYear(int $year): array
    {
        $employees = DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->select('e.employee_id', 'u.full_name', 'e.status')
            ->orderBy('u.full_name')->get();

        $paid = DB::table('hr_payroll')->where('kind', self::KIND)
            ->whereBetween('period_start', [$year.'-01-01', $year.'-12-31'])
            ->pluck('net_pay', 'employee_id');

        $rows = [];

        foreach ($employees as $employee) {
            $figures = $this->forEmployee($employee->employee_id, $year);

            if ($figures['amount'] <= 0) {
                continue;
            }

            $rows[] = $figures + [
                'employee_id' => $employee->employee_id,
                'name'        => $employee->full_name,
                'recorded'    => $paid->has($employee->employee_id) ? (float) $paid->get($employee->employee_id) : null,
            ];
        }

        return $rows;
    }

    /**
     * Records the 13th month as its own payslip, once per employee per year.
     *
     * It is marked as its own kind, so next year's calculation does not treat
     * this payment as salary earned.
     *
     * @return int how many were recorded
     */
    public function generate(int $year): int
    {
        $recorded = 0;

        foreach ($this->forYear($year) as $row) {
            if ($row['recorded'] !== null) {
                continue;
            }

            DB::transaction(function () use ($row, $year, &$recorded) {
                $exists = DB::table('hr_payroll')->where('employee_id', $row['employee_id'])
                    ->where('kind', self::KIND)
                    ->whereBetween('period_start', [$year.'-01-01', $year.'-12-31'])
                    ->lockForUpdate()->exists();

                if ($exists) {
                    return;
                }

                $notes = '13th month pay for '.$year.': one twelfth of PHP '.number_format($row['basic'], 2)
                    .' basic salary earned over '.$row['payslips'].' payslip(s).';

                if ($row['taxableExcess'] > 0) {
                    $notes .= ' PHP '.number_format($row['taxableExcess'], 2)
                        .' is above the PHP 90,000 exemption and is taxable - withholding has not been applied.';
                }

                DB::table('hr_payroll')->insert([
                    'employee_id'    => $row['employee_id'],
                    // The statutory deadline, and a date no cutoff starts on,
                    // so it never collides with a December payslip.
                    'period_start'   => $year.'-12-24',
                    'period_end'     => $year.'-12-24',
                    'gross_pay'      => $row['amount'],
                    'deductions'     => 0,
                    'net_pay'        => $row['amount'],
                    'overtime_pay'   => 0,
                    'holiday_pay'    => 0,
                    'time_deduction' => 0,
                    'loan_deduction' => 0,
                    'kind'           => self::KIND,
                    'status'         => 'calculated',
                    'notes'          => $notes,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $recorded++;
            });
        }

        return $recorded;
    }
}
