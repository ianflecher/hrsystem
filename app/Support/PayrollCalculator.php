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
 * The contribution rates live in config/statutory.php, so they can be
 * corrected without touching this file. They still need checking against the
 * current circulars before anyone is paid - see the warning there.
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
        bool $statutory = true,
    ): array {
        $gross = round($monthlySalary / 2 + $overtimePay + $holidayPay, 2);

        // Contributions are monthly amounts. Whether they come off whole on
        // the second cutoff or half on each is a company decision about
        // timing, so it is a setting rather than a rule baked in here.
        // Somebody on work immersion is not a regular employee: nothing is
        // withheld, so they receive the whole of what they earned.
        $share = $statutory ? self::monthlyShare($isSecondCutoff) : 0.0;

        $sss        = round(self::sss($monthlySalary) * $share, 2);
        $philhealth = round(self::philHealth($monthlySalary) * $share, 2);
        $pagibig    = round(self::pagIbig($monthlySalary) * $share, 2);

        // Tax follows what is actually taxable: the half-month gross, less the
        // contributions that fall in this cutoff, less time not worked - which
        // was never earned, so was never taxable.
        $taxable = max(0, $gross - ($sss + $philhealth + $pagibig) - $lateDeduction);
        $tax = $statutory ? self::tax($taxable) : 0.0;

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

    /**
     * How much of a monthly contribution belongs to this cutoff.
     */
    public static function monthlyShare(bool $isSecondCutoff): float
    {
        if (Statutory::timing() === 'split') {
            return 0.5;
        }

        return $isSecondCutoff ? 1.0 : 0.0;
    }

    /**
     * Monthly SSS employee share.
     *
     * A percentage of the Monthly Salary Credit, which steps rather than
     * following the salary exactly, and is held between a floor and a ceiling.
     */
    public static function sss(float $monthlySalary): float
    {
        $c = Statutory::table('sss');

        $credit = min(max($monthlySalary, $c['msc_floor']), $c['msc_ceiling']);

        if (($c['step'] ?? 0) > 0) {
            // The salary credit is a step, not the salary: 24,300 contributes
            // at 24,500, the same as everybody else in that step.
            $credit = min(ceil($credit / $c['step']) * $c['step'], $c['msc_ceiling']);
        }

        return round($credit * $c['employee_rate'], 2);
    }

    /**
     * Monthly PhilHealth employee share: the premium on the monthly salary,
     * bounded by the floor and ceiling, split with the employer.
     */
    public static function philHealth(float $monthlySalary): float
    {
        $c = Statutory::table('philhealth');

        $base = min(max($monthlySalary, $c['salary_floor']), $c['salary_ceiling']);

        return round($base * $c['premium_rate'] * $c['employee_share'], 2);
    }

    /**
     * Monthly Pag-IBIG employee share: a rate on compensation, capped - which
     * is where the familiar flat amount comes from for anybody above the cap.
     */
    public static function pagIbig(float $monthlySalary): float
    {
        $c = Statutory::table('pagibig');

        return round(min($monthlySalary, $c['salary_cap']) * $c['employee_rate'], 2);
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

        if ($c['tax'] > 0) {
            $parts[] = 'Tax: PHP '.number_format($c['tax'], 2);
        }

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

        // Only worth saying when the timing is what put nothing here. Under
        // the split timing an empty cutoff means something else entirely -
        // work immersion, say - and the payslip should not claim otherwise.
        if ($c['sss'] == 0 && $c['philhealth'] == 0 && $c['pagibig'] == 0
            && Statutory::timing() === 'second_cutoff') {
            $parts[] = 'contributions fall on the second cutoff';
        }

        return implode(' | ', $parts);
    }
}
