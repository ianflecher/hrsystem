<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Final-pay calculator and deadline helper for Philippine separations.
 * Separation pay itself remains an HR/legal determination and must be supplied
 * after the separation reason is reviewed.
 */
class PhilippineFinalPay
{
    public function calculate(array $values): array
    {
        $salary = round(max(0, (float) ($values['unpaid_salary'] ?? 0)), 2);
        $thirteenth = round(max(0, (float) ($values['prorated_13th_month'] ?? 0)), 2);
        $leave = round(max(0, (float) ($values['leave_conversion'] ?? 0)), 2);
        $separation = round(max(0, (float) ($values['separation_pay'] ?? 0)), 2);
        $otherEarnings = round(max(0, (float) ($values['other_earnings'] ?? 0)), 2);
        $deductions = round(max(0, (float) ($values['deductions'] ?? 0)), 2);
        $gross = round($salary + $thirteenth + $leave + $separation + $otherEarnings, 2);
        $net = round($gross - $deductions, 2);

        $separatedOn = ! empty($values['separated_on']) ? Carbon::parse($values['separated_on'])->toDateString() : null;

        return [
            'unpaid_salary' => $salary,
            'prorated_13th_month' => $thirteenth,
            'leave_conversion' => $leave,
            'separation_pay' => $separation,
            'other_earnings' => $otherEarnings,
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $net,
            'employee_receivable' => round(max(0, -$net), 2),
            'final_pay_due_on' => $separatedOn ? Carbon::parse($separatedOn)->addDays(30)->toDateString() : null,
        ];
    }
}
