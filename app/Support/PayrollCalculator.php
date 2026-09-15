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
    ): array {
        $gross = round($monthlySalary / 2, 2);

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
    public static function note(array $c, int $lateDays = 0): string
    {
        $parts = [];

        if ($c['sss'] > 0)        $parts[] = 'SSS: PHP '.number_format($c['sss'], 2);
        if ($c['philhealth'] > 0) $parts[] = 'PhilHealth: PHP '.number_format($c['philhealth'], 2);
        if ($c['pagibig'] > 0)    $parts[] = 'Pag-IBIG: PHP '.number_format($c['pagibig'], 2);

        $parts[] = 'Tax: PHP '.number_format($c['tax'], 2);

        if ($c['late'] > 0) {
            $parts[] = 'Late ('.$lateDays.' day'.($lateDays === 1 ? '' : 's').'): PHP '
                .number_format($c['late'], 2);
        }

        if ($c['sss'] == 0 && $c['philhealth'] == 0 && $c['pagibig'] == 0) {
            $parts[] = 'contributions fall on the second cutoff';
        }

        return implode(' | ', $parts);
    }
}
