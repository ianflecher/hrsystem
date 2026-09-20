<?php

namespace App\Support;

class PayslipBreakdown
{
    /**
     * @return array{earnings: array<int, array{name: string, amount: float, type: string}>,
     *               deductions: array<int, array{name: string, amount: float, type: string}>,
     *               basic: float, overtime: float, holiday: float, nsd: float, other_taxable: float, time: float, loan: float,
     *               other_deductions: float, total_deductions: float}
     */
    public static function fromPayroll(object $p): array
    {
        $gross = (float) ($p->gross_pay ?? 0);
        $overtime = (float) ($p->overtime_pay ?? 0);
        $holiday = (float) ($p->holiday_pay ?? 0);
        $nsd = (float) ($p->nsd_pay ?? 0);
        $otherTaxable = (float) ($p->other_taxable_compensation ?? 0);
        $basic = round($gross - $overtime - $holiday - $nsd - $otherTaxable, 2);

        $earnings = [];
        self::add($earnings, 'Basic pay for the period', $basic, 'salary');
        self::add($earnings, 'Overtime', $overtime, 'overtime');
        self::add($earnings, 'Holiday premium', $holiday, 'holiday');
        self::add($earnings, 'Night shift differential', $nsd, 'nsd');
        self::add($earnings, 'Other taxable compensation', $otherTaxable, 'other');

        $deductions = [];
        self::add($deductions, 'SSS Contribution', (float) ($p->sss ?? 0), 'government');
        self::add($deductions, 'PhilHealth Contribution', (float) ($p->philhealth ?? 0), 'government');
        self::add($deductions, 'Pag-IBIG Contribution', (float) ($p->pagibig ?? 0), 'government');
        self::add($deductions, 'Tax Withheld', (float) ($p->tax ?? 0), 'tax');
        self::add($deductions, 'Late, undertime and absence', (float) ($p->time_deduction ?? 0), 'time');
        self::add($deductions, 'Loan repayment', (float) ($p->loan_deduction ?? 0), 'loan');

        $named = round(array_sum(array_column($deductions, 'amount')), 2);
        $other = round((float) ($p->deductions ?? 0) - $named, 2);

        if ($other != 0.0) {
            self::add($deductions, 'Other deductions', $other, 'other');
        }

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'basic' => $basic,
            'overtime' => $overtime,
            'holiday' => $holiday,
            'nsd' => $nsd,
            'other_taxable' => $otherTaxable,
            'time' => (float) ($p->time_deduction ?? 0),
            'loan' => (float) ($p->loan_deduction ?? 0),
            'other_deductions' => $other,
            'total_deductions' => (float) ($p->deductions ?? 0),
        ];
    }

    private static function add(array &$rows, string $name, float $amount, string $type): void
    {
        if (round($amount, 2) == 0.0) {
            return;
        }

        $rows[] = ['name' => $name, 'amount' => round($amount, 2), 'type' => $type];
    }
}
