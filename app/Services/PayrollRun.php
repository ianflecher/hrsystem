<?php

namespace App\Services;

use App\Support\PayPeriod;
use App\Support\PayrollCalculator;
use App\Support\Statutory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollRun
{
    /** Lock the employee before reserving overtime and loan installments. */
    public function generate(int $employeeId, PayPeriod $period): ?int
    {
        return DB::transaction(function () use ($employeeId, $period) {
            app(PayrollControlCenter::class)->assertWritable($period);
            $employee = DB::table('employees')->where('employee_id', $employeeId)->lockForUpdate()->first();
            if (! $employee || $employee->status !== 'active' || $employee->salary <= 0) return null;
            if (DB::table('hr_payroll')->where('employee_id', $employeeId)->where('kind', 'regular')->where('period_start', $period->start)->exists()) return null;
            $payBasis = $employee->pay_basis ?? 'monthly';
            $monthlyBase = (float) $employee->salary;
            $time = ['total'=>0.0,'late'=>0.0,'lateDays'=>0,'undertime'=>0.0,'undertimeDays'=>0,'absence'=>0.0,'absentDays'=>0,'unpaidLeave'=>0.0,'unpaidLeaveDays'=>0,'leaveDays'=>0];
            if ($payBasis === 'monthly') {
                $time = (new TimeDeductions)->forPeriod($employee, $period->start, $period->end);
            } else {
                $work = app(WorkTimePayroll::class)->forPeriod($employee, $period->start, $period->end);
                $daily = (float) ($employee->daily_rate ?? 0);
                if ($daily <= 0 && $monthlyBase > 0) $daily = round($monthlyBase / 26, 2);
                $basic = $payBasis === 'hourly' ? round($work['hours'] * ($daily / 8), 2) : round(($work['worked_days'] + $work['paid_leave_days']) * $daily, 2);
                $monthlyBase = $monthlyBase > 0 ? $monthlyBase : round($daily * 26, 2);
                $time['basic_override'] = $basic;
                $time['work_days'] = $work['worked_days'];
                $time['paid_leave_days'] = $work['paid_leave_days'];
                $time['hours'] = $work['hours'];
            }
            // Includes previously approved, unpaid overtime missed by an older cutoff.
            $overtime = DB::table('overtime_requests')->where('employee_id', $employeeId)->where('status', 'approved')->whereNull('payroll_id')
                ->where('ends_at', '<', Carbon::parse($period->end)->addDay())->lockForUpdate()->get();
            // Working a holiday earns a premium on top of the monthly salary,
            // which already covers the holidays nobody works.
            $holiday = (new HolidayPay)->forPeriod($employee, $period->start, $period->end);
            $nsd = (new NightShiftDifferential)->forPeriod($employee, $period->start, $period->end, $period->start);
            // Work immersion ends on a date, not on somebody remembering: the
            // cutoff that starts after it is a regular one.
            $onImmersion = $employee->immersion_until
                && $period->start <= substr((string) $employee->immersion_until, 0, 10);

            $compliance = app(PhilippinePayrollCompliance::class)->assertReady($period->start);
            $ruleSnapshot = Statutory::snapshot($period->start);
            $overtimeAmount=(float) $overtime->sum('approved_amount');
            $statutoryBase = $monthlyBase > 0 ? $monthlyBase : (float) $employee->salary;
            $c = $payBasis === 'monthly'
                ? PayrollCalculator::forCutoff($statutoryBase, $time['total'], $period->isSecondCutoff, $overtimeAmount, $holiday['amount'], ! $onImmersion, $nsd['amount'], $period->start, (bool) ($employee->minimum_wage_earner ?? false))
                : PayrollCalculator::forNonMonthlyCutoff((float) ($time['basic_override'] ?? 0), $statutoryBase, 0, $period->isSecondCutoff, $overtimeAmount, $holiday['amount'], ! $onImmersion, $nsd['amount'], $period->start, (bool) ($employee->minimum_wage_earner ?? false));
            $remainingCents = max(0, (int) round($c['net'] * 100));
            $loans = DB::table('employee_loans')->where('employee_id', $employeeId)->where('status', 'active')->where('starts_on', '<=', $period->start)->orderBy('id')->lockForUpdate()->get();
            $installments = [];
            foreach ($loans as $loan) {
                $reserved = DB::table('loan_installments')->where('loan_id', $loan->id)->sum('amount');
                $balance = max(0, (int) round(((float) $loan->amount - (float) $reserved) * 100));
                $cents = min($balance, (int) round($loan->installment * 100), $remainingCents);
                if ($cents <= 0) continue;
                $installments[$loan->id] = $cents / 100;
                $remainingCents -= $cents;
            }
            $deduction = array_sum($installments);
            $notes = PayrollCalculator::note($c, $time);
            if ($payBasis !== 'monthly') $notes .= ($notes === '' ? '' : ' | ').'Pay basis: '.ucfirst($payBasis).' (ordinary time derived from attendance; review company pay policy before live use).';

            if ($onImmersion) {
                $immersion = 'Work immersion until '.substr((string) $employee->immersion_until, 0, 10)
                    .' - paid in full, no contributions or tax.';
                // Nothing withheld usually means nothing else to say, so the
                // separator only appears when there is something after it.
                $notes = $notes === '' ? $immersion : $immersion.' | '.$notes;
            }
            if ($c['overtime'] > 0) $notes .= ' | Overtime: PHP '.number_format($c['overtime'], 2);
            if ($c['nsd'] > 0) $notes .= ' | NSD: '.number_format($c['nsd'], 2). ' ('.number_format($nsd['hours'], 2).' hours)';
            if ($deduction > 0) $notes .= ' | Loan repayment: PHP '.number_format($deduction, 2);
            $id = DB::table('hr_payroll')->insertGetId([
                'employee_id' => $employeeId, 'period_start' => $period->start, 'period_end' => $period->end,
                'gross_pay' => $c['gross'], 'basic_pay' => $c['basic'], 'deductions' => round($c['deductions'] + $deduction, 2), 'net_pay' => round($c['net'] - $deduction, 2),
                'overtime_pay' => $c['overtime'], 'holiday_pay' => $c['holiday'], 'nsd_pay' => $c['nsd'], 'time_deduction' => $time['total'],
                'sss' => $c['sss'], 'employer_sss' => $c['employer_sss'], 'employer_ec' => $c['employer_ec'], 'philhealth' => $c['philhealth'], 'employer_philhealth' => $c['employer_philhealth'], 'pagibig' => $c['pagibig'], 'employer_pagibig' => $c['employer_pagibig'], 'tax' => $c['tax'], 'taxable_compensation' => $c['taxable'], 'other_taxable_compensation' => $c['other_taxable'], 'mwe_exempt_compensation' => $c['mwe_exempt_compensation'], 'statutory_rule_version' => $ruleSnapshot['version'], 'statutory_snapshot' => json_encode($ruleSnapshot, JSON_THROW_ON_ERROR), 'rules_verified_at' => $compliance['verified_at'], 'employer_total_cost' => round($c['gross'] + $c['employer_sss'] + $c['employer_ec'] + $c['employer_philhealth'] + $c['employer_pagibig'], 2),
                'loan_deduction' => $deduction, 'status' => 'calculated', 'notes' => $notes,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('overtime_requests')->whereIn('id', $overtime->pluck('id'))->update(['payroll_id' => $id, 'updated_at' => now()]);
            foreach ($installments as $loanId => $amount) DB::table('loan_installments')->insert([
                'loan_id' => $loanId, 'payroll_id' => $id, 'amount' => $amount, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $id;
        });
    }

    public function markPaid(string $periodStart): int
    {
        return DB::transaction(function () use ($periodStart) {
            $payrolls = DB::table('hr_payroll')->where('period_start', $periodStart)->where('status', 'approved')->lockForUpdate()->pluck('payroll_id');
            $installments = DB::table('loan_installments')->whereIn('payroll_id', $payrolls)->get();
            DB::table('loan_installments')->whereIn('payroll_id', $payrolls)->whereNull('paid_at')->update(['paid_at' => now(), 'updated_at' => now()]);
            foreach ($installments->pluck('loan_id')->unique()->sort() as $loanId) {
                $loan = DB::table('employee_loans')->where('id', $loanId)->lockForUpdate()->first();
                $paid = DB::table('loan_installments')->where('loan_id', $loanId)->whereNotNull('paid_at')->sum('amount');
                if ($loan && (int) round($paid * 100) >= (int) round($loan->amount * 100)) DB::table('employee_loans')->where('id', $loanId)->update(['status' => 'repaid', 'updated_at' => now()]);
            }
            return DB::table('hr_payroll')->whereIn('payroll_id', $payrolls)->update(['status' => 'paid', 'updated_at' => now()]);
        });
    }
}
