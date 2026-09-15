<?php

namespace App\Support;

/**
 * What one semi-monthly payslip comes to.
 *
 * Staff are paid twice a month, so each payslip is half the monthly salary.
 * SSS, PhilHealth and Pag-IBIG are monthly obligations, and this company takes
 * them whole on the second cutoff - so the 1st-15th payslip carries none and
 * the 16th-end one carries the lot. That makes the two payslips different
 * sizes on purpose.
 *
 * WARNING: the contribution figures are simplified and were carried over from
 * the original code - the SSS brackets are coarse, PhilHealth has no floor or
 * ceiling applied, and Pag-IBIG is the flat maximum. Check them against the
 * current SSS, PhilHealth and BIR tables before anyone is paid from them.
 */
class PayrollCalculator
{
    /**
     * @param  float  $monthlySalary  the full monthly rate, not the half
     * @param  float  $lateDeduction  what tardiness cost during this cutoff
     * @param  bool   $isSecondCutoff whether the monthly contributions land here
     */
    public static function forCutoff(
        float $monthlySalary,
        float $lateDeduction = 0.0,
        bool $isSecondCutoff = true,
        float $overtimePay = 0.0,
        float $holidayPay = 0.0,
    ): array {
        $gross = round($monthlySalary / 2 + $overtimePay + $holidayPay, 2);

        // Contributions are monthly amounts, taken whole on one cutoff.
        $sss        = $isSecondCutoff ? self::sss($monthlySalary) : 0.0;
        $philhealth = $isSecondCutoff ? self::philHealth($monthlySalary) : 0.0;
        $pagibig    = $isSecondCutoff ? 100.0 : 0.0;

        // Tax follows what is actually taxable: the half-month gross, less the
        // contributions that fall in this cutoff, less time not worked - which
        // was never earned, so was never taxable.
        $taxable = max(0, $gross - ($sss + $philhealth + $pagibig) - $lateDeduction);
        $tax = self::tax($taxable);

        $deductions = $sss + $philhealth + $pagibig + $tax + $lateDeduction;

        return [
            'gross'      => $gross,
            'overtime'   => round($overtimePay, 2),
            'holiday'    => round($holidayPay, 2),
            'sss'        => round($sss, 2),
            'philhealth' => round($philhealth, 2),
            'pagibig'    => round($pagibig, 2),
            'tax'        => round($tax, 2),
            'late'       => round($lateDeduction, 2),
            'taxable'    => round($taxable, 2),
            'deductions' => round($deductions, 2),
            'net'        => round($gross - $deductions, 2),
        ];
    }

    /** Monthly SSS contribution. Coarse brackets - see the class note. */
    public static function sss(float $monthlySalary): float
    {
        if ($monthlySalary <= 10000) return 450;
        if ($monthlySalary <= 20000) return 900;
        if ($monthlySalary <= 30000) return 1350;
        if ($monthlySalary <= 40000) return 1800;
        if ($monthlySalary <= 50000) return 2250;

        return 2700;
    }

    /** Monthly PhilHealth employee share: 4% of salary, split with the employer. */
    public static function philHealth(float $monthlySalary): float
    {
        return ($monthlySalary * 0.04) / 2;
    }

    /**
     * Withholding tax on a semi-monthly taxable amount.
     *
     * These are the BIR semi-monthly brackets - the monthly ones halved. Using
     * the monthly table against a half-month figure would tax almost everyone
     * at zero.
     */
    public static function tax(float $semiMonthlyTaxable): float
    {
        if ($semiMonthlyTaxable <= 10417) {
            return 0;
        }

        if ($semiMonthlyTaxable <= 16667) {
            return ($semiMonthlyTaxable - 10417) * 0.15;
        }

        if ($semiMonthlyTaxable <= 33333) {
            return 937.50 + ($semiMonthlyTaxable - 16667) * 0.20;
        }

        if ($semiMonthlyTaxable <= 83333) {
            return 4270.70 + ($semiMonthlyTaxable - 33333) * 0.25;
        }

        if ($semiMonthlyTaxable <= 333333) {
            return 16770.70 + ($semiMonthlyTaxable - 83333) * 0.30;
        }

        return 91770.70 + ($semiMonthlyTaxable - 333333) * 0.35;
    }

    /**
     * How the payslip describes itself.
     */
    /**
     * @param  array  $time  the TimeDeductions breakdown, when there is one:
     *                       late, lateDays, undertime, undertimeDays
     */
    public static function note(array $c, array $time = []): string
    {
        $parts = [];

        if ($c['sss'] > 0)        $parts[] = 'SSS: PHP '.number_format($c['sss'], 2);
        if ($c['philhealth'] > 0) $parts[] = 'PhilHealth: PHP '.number_format($c['philhealth'], 2);
        if ($c['pagibig'] > 0)    $parts[] = 'Pag-IBIG: PHP '.number_format($c['pagibig'], 2);

        $parts[] = 'Tax: PHP '.number_format($c['tax'], 2);

        // $c['late'] is every time deduction together. Each is named from its
        // own figure so nothing on the payslip is an unexplained sum.
        if ($c['late'] > 0) {
            $named = 0.0;

            foreach ([
                'absentDays'      => ['Absent', 'absence'],
                'unpaidLeaveDays' => ['Unpaid leave', 'unpaidLeave'],
                'lateDays'        => ['Late', 'late'],
                'undertimeDays'   => ['Undertime', 'undertime'],
            ] as $dayKey => [$label, $amountKey]) {
                $days = (int) ($time[$dayKey] ?? 0);

                if ($days === 0) {
                    continue;
                }

                $amount = (float) ($time[$amountKey] ?? 0);
                $named += $amount;
                $parts[] = $label.' ('.$days.' day'.($days === 1 ? '' : 's').'): PHP '.number_format($amount, 2);
            }

            if (round($named, 2) < $c['late']) {
                $parts[] = 'Other time deductions: PHP '.number_format($c['late'] - round($named, 2), 2);
            }
        }

        if ($c['holiday'] > 0) {
            $parts[] = 'Holiday premium: PHP '.number_format($c['holiday'], 2);
        }

        if (($time['leaveDays'] ?? 0) > 0) {
            $parts[] = 'Paid leave: '.$time['leaveDays'].' day'.($time['leaveDays'] === 1 ? '' : 's');
        }

        if ($c['sss'] == 0 && $c['philhealth'] == 0 && $c['pagibig'] == 0) {
            $parts[] = 'contributions fall on the second cutoff';
        }

        return implode(' | ', $parts);
    }
}
