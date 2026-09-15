<?php

namespace App\Services;

use App\Support\PayPeriod;
use App\Support\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollRun
{
    /** Lock the employee before reserving overtime and loan installments. */
    public function generate(int $employeeId, PayPeriod $period): ?int
    {
        return DB::transaction(function () use ($employeeId, $period) {
            $employee = DB::table('employees')->where('employee_id', $employeeId)->lockForUpdate()->first();
            if (! $employee || $employee->status !== 'active' || $employee->salary <= 0) return null;
            if (DB::table('hr_payroll')->where('employee_id', $employeeId)->where('kind', 'regular')->where('period_start', $period->start)->exists()) return null;
            // Lateness and undertime, day by day - see TimeDeductions.
            $time = (new TimeDeductions)->forPeriod($employee, $period->start, $period->end);
            // Includes previously approved, unpaid overtime missed by an older cutoff.
            $overtime = DB::table('overtime_requests')->where('employee_id', $employeeId)->where('status', 'approved')->whereNull('payroll_id')
                ->where('ends_at', '<', Carbon::parse($period->end)->addDay())->lockForUpdate()->get();
            // Working a holiday earns a premium on top of the monthly salary,
            // which already covers the holidays nobody works.
            $holiday = (new HolidayPay)->forPeriod($employee, $period->start, $period->end);
            $c = PayrollCalculator::forCutoff((float) $employee->salary, $time['total'], $period->isSecondCutoff,
                (float) $overtime->sum('approved_amount'), $holiday['amount']);
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
            if ($c['overtime'] > 0) $notes .= ' | Overtime: PHP '.number_format($c['overtime'], 2);
            if ($deduction > 0) $notes .= ' | Loan repayment: PHP '.number_format($deduction, 2);
            $id = DB::table('hr_payroll')->insertGetId([
                'employee_id' => $employeeId, 'period_start' => $period->start, 'period_end' => $period->end,
                'gross_pay' => $c['gross'], 'deductions' => round($c['deductions'] + $deduction, 2), 'net_pay' => round($c['net'] - $deduction, 2),
                'overtime_pay' => $c['overtime'], 'holiday_pay' => $c['holiday'], 'time_deduction' => $time['total'],
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
