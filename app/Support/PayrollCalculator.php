<?php

namespace App\Support;

class PayrollCalculator
{
    public static function forCutoff(
        float $monthlySalary,
        float $lateDeduction = 0.0,
        bool $isSecondCutoff = true,
        float $overtimePay = 0.0,
        float $holidayPay = 0.0,
        bool $statutory = true,
        float $nsdPay = 0.0,
        ?string $ruleDate = null,
        bool $minimumWageEarner = false,
        float $otherTaxableCompensation = 0.0,
    ): array {
        $basic = round($monthlySalary / 2, 2);
        $otherTaxableCompensation = round(max(0, $otherTaxableCompensation), 2);
        $gross = round($basic + $overtimePay + $holidayPay + $nsdPay + $otherTaxableCompensation, 2);
        $share = $statutory ? self::monthlyShare($isSecondCutoff, $ruleDate) : 0.0;

        $sss = round(self::sss($monthlySalary, $ruleDate) * $share, 2);
        $philhealth = round(self::philHealth($monthlySalary, $ruleDate) * $share, 2);
        $pagibig = round(self::pagIbig($monthlySalary, $ruleDate) * $share, 2);
        $employer = $statutory ? self::employerContributions($monthlySalary, $ruleDate, $share) : ['sss' => 0, 'ec' => 0, 'philhealth' => 0, 'pagibig' => 0];

        $preTax = max(0, $gross - ($sss + $philhealth + $pagibig) - $lateDeduction);
        $mweExempt = $minimumWageEarner ? round($basic + $overtimePay + $holidayPay + $nsdPay, 2) : 0.0;
        $taxable = max(0, $preTax - $mweExempt);
        if (! $minimumWageEarner) {
            $taxable = $preTax;
        }
        $tax = $statutory ? self::tax($taxable, $ruleDate) : 0.0;
        $deductions = round($sss + $philhealth + $pagibig + $tax + $lateDeduction, 2);

        return [
            'basic' => $basic, 'gross' => $gross, 'overtime' => round($overtimePay, 2),
            'holiday' => round($holidayPay, 2), 'nsd' => round($nsdPay, 2),
            'other_taxable' => $otherTaxableCompensation,
            'sss' => $sss, 'philhealth' => $philhealth, 'pagibig' => $pagibig,
            'employer_sss' => $employer['sss'], 'employer_ec' => $employer['ec'],
            'employer_philhealth' => $employer['philhealth'], 'employer_pagibig' => $employer['pagibig'],
            'tax' => round($tax, 2), 'taxable' => round($taxable, 2),
            'mwe_exempt_compensation' => $mweExempt,
            'late' => round($lateDeduction, 2), 'deductions' => $deductions,
            'net' => round($gross - $deductions, 2), 'rule_version' => Statutory::snapshot($ruleDate)['version'],
        ];
    }

    public static function forNonMonthlyCutoff(
        float $basicPay,
        float $monthlyStatutoryBase,
        float $lateDeduction = 0.0,
        bool $isSecondCutoff = true,
        float $overtimePay = 0.0,
        float $holidayPay = 0.0,
        bool $statutory = true,
        float $nsdPay = 0.0,
        ?string $ruleDate = null,
        bool $minimumWageEarner = false,
        float $otherTaxableCompensation = 0.0,
    ): array {
        $basicPay=round(max(0,$basicPay),2); $monthlyStatutoryBase=round(max(0,$monthlyStatutoryBase),2);
        $gross=round($basicPay+$overtimePay+$holidayPay+$nsdPay+$otherTaxableCompensation,2);
        $share=$statutory ? self::monthlyShare($isSecondCutoff,$ruleDate) : 0.0;
        $sss=round(self::sss($monthlyStatutoryBase,$ruleDate)*$share,2);
        $philhealth=round(self::philHealth($monthlyStatutoryBase,$ruleDate)*$share,2);
        $pagibig=round(self::pagIbig($monthlyStatutoryBase,$ruleDate)*$share,2);
        $employer=$statutory?self::employerContributions($monthlyStatutoryBase,$ruleDate,$share):['sss'=>0,'ec'=>0,'philhealth'=>0,'pagibig'=>0];
        $preTax=max(0,$gross-($sss+$philhealth+$pagibig)-$lateDeduction);
        $mweExempt=$minimumWageEarner?round($basicPay+$overtimePay+$holidayPay+$nsdPay,2):0.0;
        $taxable=$minimumWageEarner?max(0,$preTax-$mweExempt):$preTax;
        $tax=$statutory?self::tax($taxable,$ruleDate):0.0; $deductions=round($sss+$philhealth+$pagibig+$tax+$lateDeduction,2);
        return ['basic'=>$basicPay,'gross'=>$gross,'overtime'=>round($overtimePay,2),'holiday'=>round($holidayPay,2),'nsd'=>round($nsdPay,2),'other_taxable'=>round($otherTaxableCompensation,2),'sss'=>$sss,'philhealth'=>$philhealth,'pagibig'=>$pagibig,'employer_sss'=>$employer['sss'],'employer_ec'=>$employer['ec'],'employer_philhealth'=>$employer['philhealth'],'employer_pagibig'=>$employer['pagibig'],'tax'=>round($tax,2),'taxable'=>round($taxable,2),'mwe_exempt_compensation'=>$mweExempt,'late'=>round($lateDeduction,2),'deductions'=>$deductions,'net'=>round($gross-$deductions,2),'rule_version'=>Statutory::snapshot($ruleDate)['version']];
    }

    /**
     * How much of the month's contributions this payslip carries.
     *
     * Timing is read from the company's own setting, not from the versioned
     * rule snapshot. Whether contributions are halved across the two cutoffs
     * or all taken on the second is a decision this company makes; it is not
     * something SSS or Pag-IBIG legislate, so it does not belong to a dated
     * statutory version. Reading it from the snapshot meant the stored rules
     * silently overrode the setting, and note() - which reads the setting -
     * then described a payslip that had been worked out the other way.
     */
    public static function monthlyShare(bool $isSecondCutoff, ?string $ruleDate = null): float
    {
        if (Statutory::timing() === 'split') return 0.5;
        return $isSecondCutoff ? 1.0 : 0.0;
    }

    public static function sss(float $monthlySalary, ?string $ruleDate = null): float
    {
        $c = Statutory::tableForDate('sss', $ruleDate);
        $credit = min(max($monthlySalary, $c['msc_floor']), $c['msc_ceiling']);
        if (($c['step'] ?? 0) > 0) $credit = min(ceil($credit / $c['step']) * $c['step'], $c['msc_ceiling']);
        return round($credit * $c['employee_rate'], 2);
    }

    public static function philHealth(float $monthlySalary, ?string $ruleDate = null): float
    {
        $c = Statutory::tableForDate('philhealth', $ruleDate);
        $base = min(max($monthlySalary, $c['salary_floor']), $c['salary_ceiling']);
        return round($base * $c['premium_rate'] * $c['employee_share'], 2);
    }

    public static function pagIbig(float $monthlySalary, ?string $ruleDate = null): float
    {
        $c = Statutory::tableForDate('pagibig', $ruleDate);
        $rate = $monthlySalary <= $c['rate_threshold'] ? $c['employee_rate_low'] : $c['employee_rate'];
        return round(min($monthlySalary, $c['salary_cap']) * $rate, 2);
    }

    public static function employerContributions(float $monthlySalary, ?string $ruleDate = null, float $share = 1.0): array
    {
        $sssTable = Statutory::tableForDate('sss', $ruleDate);
        $credit = min(max($monthlySalary, $sssTable['msc_floor']), $sssTable['msc_ceiling']);
        if (($sssTable['step'] ?? 0) > 0) $credit = min(ceil($credit / $sssTable['step']) * $sssTable['step'], $sssTable['msc_ceiling']);
        $share = min(1.0, max(0.0, $share));
        $sss = round($credit * $sssTable['employer_rate'] * $share, 2);
        $ec = ($credit >= 15000 ? $sssTable['ec_15000_and_above'] : $sssTable['ec_below_15000']) * $share;
        // The employer's half, worked out from the table rather than by
        // doubling the employee's. philHealth() already returns half the
        // premium, so doubling it gave the whole premium and charged the
        // company twice what it owes.
        $phTable = Statutory::tableForDate('philhealth', $ruleDate);
        $phBase = min(max($monthlySalary, $phTable['salary_floor']), $phTable['salary_ceiling']);
        $ph = round($phBase * $phTable['premium_rate'] * $phTable['employer_share'] * $share, 2);
        $p = Statutory::tableForDate('pagibig', $ruleDate);
        $pagibig = round(min($monthlySalary, $p['salary_cap']) * $p['employer_rate'] * $share, 2);
        return ['sss' => $sss, 'ec' => round((float) $ec, 2), 'philhealth' => $ph, 'pagibig' => $pagibig];
    }

    public static function tax(float $semiMonthlyTaxable, ?string $ruleDate = null): float
    {
        $bir = Statutory::tableForDate('bir', $ruleDate);
        $semiMonthlyTaxable = round(max(0, $semiMonthlyTaxable), 2);
        if ($semiMonthlyTaxable <= ($bir['tax_free_to'] ?? 10417.0)) return 0.0;
        foreach ($bir['brackets'] as $bracket) {
            if ($semiMonthlyTaxable <= $bracket['to']) {
                return round($bracket['fixed'] + (($semiMonthlyTaxable - $bracket['over']) * $bracket['rate']), 2);
            }
        }
        return 0.0;
    }

    public static function note(array $c, array $time = []): string
    {
        $parts = [];
        foreach ([['sss','SSS'],['philhealth','PhilHealth'],['pagibig','Pag-IBIG'],['tax','Tax'],['nsd','Night shift differential']] as [$key,$label]) {
            if (($c[$key] ?? 0) > 0) $parts[] = $label.': PHP '.number_format($c[$key], 2);
        }
        if (($c['late'] ?? 0) > 0) {
            $named = 0.0;
            foreach ([
                'absentDays' => ['Absent', 'absence'], 'unpaidLeaveDays' => ['Unpaid leave', 'unpaidLeave'],
                'lateDays' => ['Late', 'late'], 'undertimeDays' => ['Undertime', 'undertime'],
            ] as $dayKey => [$label, $amountKey]) {
                $days = (int) ($time[$dayKey] ?? 0); if ($days === 0) continue;
                $amount = (float) ($time[$amountKey] ?? 0); $named += $amount;
                $parts[] = $label.' ('.$days.' day'.($days === 1 ? '' : 's').'): PHP '.number_format($amount, 2);
            }
            if (round($named, 2) < $c['late']) $parts[] = 'Other time deductions: PHP '.number_format($c['late'] - round($named, 2), 2);
        }
        if (($c['holiday'] ?? 0) > 0) $parts[] = 'Holiday premium: PHP '.number_format($c['holiday'], 2);
        if (($time['leaveDays'] ?? 0) > 0) $parts[] = 'Paid leave: '.$time['leaveDays'].' day'.($time['leaveDays'] === 1 ? '' : 's');
        if (($c['other_taxable'] ?? 0) > 0) $parts[] = 'Other taxable compensation: PHP '.number_format($c['other_taxable'], 2);
        if (($c['sss'] ?? 0) == 0 && ($c['philhealth'] ?? 0) == 0 && ($c['pagibig'] ?? 0) == 0 && Statutory::timing() === 'second_cutoff') $parts[] = 'contributions fall on the second cutoff';
        return implode(' | ', $parts);
    }
}
