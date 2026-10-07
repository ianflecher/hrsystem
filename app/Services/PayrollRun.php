<?php

namespace App\Services;

use App\Support\PayPeriod;
use App\Support\PayrollCalculator;
use App\Support\ShiftSchedule;
use App\Support\Statutory;
use App\Support\WorkWeek;
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
            // Days and hours actually worked, printed on the payslip whatever the pay basis.
            $lateMinutes = 0;
            foreach (DB::table('hr_attendance')->where('employee_id', $employeeId)->whereBetween('date', [$period->start, $period->end])->whereNotNull('time_in')->get(['date', 'time_in']) as $day) {
                $shift = ShiftSchedule::forEmployeeDate($employee, substr((string) $day->date, 0, 10));
                if (! $shift['rest'] && $shift['start']) {
                    $lateMinutes += (int) (\App\Support\Tardiness::minutesLate(Carbon::parse($day->time_in), $shift['start']) ?? 0);
                }
            }
            $rendered = app(WorkTimePayroll::class)->forPeriod($employee, $period->start, $period->end);
            if ($payBasis === 'monthly') {
                $time = (new TimeDeductions)->forPeriod($employee, $period->start, $period->end);
            } else {
                $work = app(WorkTimePayroll::class)->forPeriod($employee, $period->start, $period->end);
                $daily = (float) ($employee->daily_rate ?? 0);
                if ($daily <= 0 && $monthlyBase > 0) $daily = round($monthlyBase / 26, 2);
                // Paid by the hour rendered, as the cafe's payslips are: 88 hours
                // at PHP 508 a day is 88 x 508 / 8. A late or short day pays
                // the hours worked. Paid leave is a whole day. A guard's rate is
                // for the whole duty, so guards stay paid by the day.
                $basic = ! ShiftSchedule::isGuard($employee)
                    ? round($work['hours'] * ($daily / 8) + $work['paid_leave_days'] * $daily, 2)
                    : round(($work['worked_days'] + $work['paid_leave_days']) * $daily, 2);
                $monthlyBase = $monthlyBase > 0 ? $monthlyBase : round($daily * 26, 2);
                $time['basic_override'] = $basic;
                $time['work_days'] = $work['worked_days'];
                $time['paid_leave_days'] = $work['paid_leave_days'];
                $time['hours'] = $work['hours'];
                $time['late_penalty_hours'] = $work['late_penalty_hours'] ?? 0;
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

            // HR's plus and minus rows for this cutoff. A taxable plus is pay
            // like any other; a non-taxable plus and every minus land after tax.
            $adjustments = DB::table('payroll_adjustments')->where('employee_id', $employeeId)->where('status', 'approved')
                ->whereBetween('effective_date', [$period->start, $period->end])->get();
            $plusTaxable = (float) $adjustments->where('type', 'addition')->where('taxable', true)->sum('amount');
            $plusExempt = (float) $adjustments->where('type', 'addition')->where('taxable', false)->sum('amount');
            $minus = (float) $adjustments->where('type', 'deduction')->sum('amount');
            // A basic pay adjustment (a correction to an earlier cutoff's basic) is basic pay.
            $basicAdjustment = (float) $adjustments->where('type', 'basic')->sum('amount');
            if ($payBasis !== 'monthly') {
                $time['basic_override'] = round((float) ($time['basic_override'] ?? 0) + $basicAdjustment, 2);
            } else {
                $plusTaxable += $basicAdjustment;
            }

            $compliance = app(PhilippinePayrollCompliance::class)->assertReady($period->start);
            $ruleSnapshot = Statutory::snapshot($period->start);
            // Only the part of each overtime outside the day's shift is paid - a
            // schedule changed after approval can put it inside, already paid as basic.
            $otPayable = $overtime->map(fn ($o) => \App\Support\OvertimePayable::for($employee, $o));
            $overtimeAmount = round((float) $otPayable->sum('amount'), 2);
            $otTrimmed = (int) $otPayable->sum('trimmed');
            $statutoryBase = $monthlyBase > 0 ? $monthlyBase : (float) $employee->salary;

            // Split across the two cutoffs like basic pay, so a monthly
            // allowance arrives as the month goes rather than all at once.
            // Nobody on immersion is paid one: they are paid in full anyway.
            // Day-rated staff get theirs for each day paid, like their basic.
            if ($payBasis === 'monthly') {
                $monthlyAllowance = $onImmersion ? 0.0 : (float) ($employee->allowance ?? 0);
                $allowance = round($monthlyAllowance / 2, 2);
            } else {
                $perDay = $onImmersion ? 0.0 : (float) ($employee->allowance ?? 0);
                // A half day earns half the allowance: days counted from the hours
                // worked, in half days (44 h is 5.5). A late penalty comes off basic
                // only, so its hours count here. A guard's allowance is per duty.
                $allowanceDays = ShiftSchedule::isGuard($employee)
                    ? (float) ($time['work_days'] ?? 0)
                    : min((float) ($time['work_days'] ?? 0), floor((($time['hours'] ?? 0) + ($time['late_penalty_hours'] ?? 0)) / 8 * 2 + 0.0001) / 2);
                $allowance = round($perDay * ($allowanceDays + ($time['paid_leave_days'] ?? 0)), 2);
                $monthlyAllowance = round($perDay * 26, 2);
            }

            // Total monthly compensation, which only Pag-IBIG reads - and it
            // caps the base at 10,000, so in practice the allowance changes
            // nothing there either. SSS and PhilHealth read the basic salary,
            // and tax picks the allowance up through gross.
            $monthlyCompensation = round($statutoryBase + $monthlyAllowance, 2);
            $c = $payBasis === 'monthly'
                ? PayrollCalculator::forCutoff($statutoryBase, $time['total'], $period->isSecondCutoff, $overtimeAmount, $holiday['amount'], ! $onImmersion, $nsd['amount'], $period->start, (bool) ($employee->minimum_wage_earner ?? false), $plusTaxable, $allowance, $monthlyCompensation)
                : PayrollCalculator::forNonMonthlyCutoff((float) ($time['basic_override'] ?? 0), $statutoryBase, 0, $period->isSecondCutoff, $overtimeAmount, $holiday['amount'], ! $onImmersion, $nsd['amount'], $period->start, (bool) ($employee->minimum_wage_earner ?? false), $plusTaxable, $allowance, $monthlyCompensation);
            if ($plusExempt > 0 || $minus > 0) {
                $c['gross'] = round($c['gross'] + $plusExempt, 2);
                $c['deductions'] = round($c['deductions'] + $minus, 2);
                $c['net'] = round($c['net'] + $plusExempt - $minus, 2);
            }
            $remainingCents = max(0, (int) round($c['net'] * 100));
            $loans = DB::table('employee_loans')->where('employee_id', $employeeId)->where('status', 'active')->where('starts_on', '<=', $period->start)
                // Nothing more after the last month of the loan's term.
                ->where(fn ($q) => $q->whereNull('term_to')->orWhere('term_to', '>=', $period->start))->orderBy('id')->lockForUpdate()->get();
            $installments = [];
            foreach ($loans as $loan) {
                // A monthly amortization recorded without a balance comes off
                // the 1-15 cutoff in full, and nothing on the second; it runs
                // until HR stops it.
                if ((float) $loan->amount <= 0) {
                    if ($period->isSecondCutoff) continue;
                    $cents = min((int) round($loan->installment * 100), $remainingCents);
                    if ($cents <= 0) continue;
                    $installments[$loan->id] = $cents / 100;
                    $remainingCents -= $cents;
                    continue;
                }
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
            if (($c['allowance'] ?? 0) > 0) $notes .= ' | Allowance: PHP '.number_format($c['allowance'], 2).' (taxable; SSS and PhilHealth are on the basic salary)';
            if ($c['overtime'] > 0) $notes .= ' | Overtime: PHP '.number_format($c['overtime'], 2);
            if ($otTrimmed > 0) $notes .= ' | Overtime inside the shift not paid: '.round($otTrimmed / 60, 2).' h';
            if ($c['nsd'] > 0) $notes .= ' | NSD: '.number_format($c['nsd'], 2). ' ('.number_format($nsd['hours'], 2).' hours)';
            if ($deduction > 0) $notes .= ' | Loan repayment: PHP '.number_format($deduction, 2);
            foreach ($adjustments as $a) {
                $notes .= ' | '.($a->type === 'deduction' ? '-' : '+').' PHP '.number_format((float) $a->amount, 2).' '.$a->reason;
            }
            $id = DB::table('hr_payroll')->insertGetId([
                'employee_id' => $employeeId, 'period_start' => $period->start, 'period_end' => $period->end,
                'gross_pay' => $c['gross'], 'basic_pay' => $c['basic'], 'allowance' => $c['allowance'] ?? 0, 'deductions' => round($c['deductions'] + $deduction, 2), 'net_pay' => round($c['net'] - $deduction, 2),
                'overtime_pay' => $c['overtime'], 'holiday_pay' => $c['holiday'], 'nsd_pay' => $c['nsd'], 'time_deduction' => $time['total'],
                'sss' => $c['sss'], 'employer_sss' => $c['employer_sss'], 'employer_ec' => $c['employer_ec'], 'philhealth' => $c['philhealth'], 'employer_philhealth' => $c['employer_philhealth'], 'pagibig' => $c['pagibig'], 'employer_pagibig' => $c['employer_pagibig'], 'tax' => $c['tax'], 'taxable_compensation' => $c['taxable'], 'other_taxable_compensation' => $c['other_taxable'], 'mwe_exempt_compensation' => $c['mwe_exempt_compensation'], 'statutory_rule_version' => $ruleSnapshot['version'], 'statutory_snapshot' => json_encode($ruleSnapshot, JSON_THROW_ON_ERROR), 'rules_verified_at' => $compliance['verified_at'], 'employer_total_cost' => round($c['gross'] + $c['employer_sss'] + $c['employer_ec'] + $c['employer_philhealth'] + $c['employer_pagibig'], 2),
                'paid_days' => $payBasis === 'monthly'
                    ? $this->monthlyPaidDays($employee, $period)
                    : $rendered['worked_days'] + $rendered['paid_leave_days'],
                'paid_hours' => $payBasis === 'monthly'
                    ? $this->monthlyPaidDays($employee, $period) * (ShiftSchedule::dailyCapMinutes($employee) / 60)
                    : $rendered['hours'] + ($rendered['paid_leave_days'] * (ShiftSchedule::dailyCapMinutes($employee) / 60)),
                'basic_adjustment' => $basicAdjustment,
                'legal_holiday_pay' => $holiday['legal'] ?? 0, 'special_holiday_pay' => $holiday['special'] ?? 0,
                'overtime_hours' => round($otPayable->sum('minutes') / 60, 2),
                'late_minutes' => $lateMinutes,
                // Day-rated staff: the late penalty already taken out of basic, for the payslip to show.
                'late_deduction' => $payBasis === 'monthly' || ShiftSchedule::isGuard($employee) ? 0
                    : round(($rendered['late_penalty_hours'] ?? 0) * ((float) ($employee->daily_rate ?? 0) / 8), 2),
                'loan_deduction' => $deduction, 'adjustments' => round($plusTaxable + $plusExempt - $minus, 2), 'status' => 'calculated', 'notes' => $notes,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('overtime_requests')->whereIn('id', $overtime->pluck('id'))->update(['payroll_id' => $id, 'updated_at' => now()]);
            foreach ($installments as $loanId => $amount) DB::table('loan_installments')->insert([
                'loan_id' => $loanId, 'payroll_id' => $id, 'amount' => $amount, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $id;
        });
    }

    private function monthlyPaidDays(object $employee, PayPeriod $period): int
    {
        $start = Carbon::parse($period->start);
        $end = Carbon::parse($period->end);

        // Weekend-off monthly staff follow the actual calendar. The other
        // monthly supervisor policy is two rest days per cutoff: 13 days for a
        // 15-day cutoff, and 14 days for the 16-31 cutoff.
        if (WorkWeek::days($employee->rest_days ?? null) === [6, 7]) {
            $days = 0;
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if (! WorkWeek::restsOn($employee->rest_days, $day)) {
                    $days++;
                }
            }

            return $days;
        }

        return max(0, $start->diffInDays($end) + 1 - 2);
    }

    public function markPaid(string $periodStart, ?string $company = null): int
    {
        return DB::transaction(function () use ($periodStart, $company) {
            $payrolls = DB::table('hr_payroll')->where('period_start', $periodStart)->where('status', 'approved')
                ->when($company, fn ($q) => $q->whereIn('employee_id', DB::table('employees')->whereRaw("COALESCE(NULLIF(company, ''), 'GKLASAM OPC') = ?", [$company])->select('employee_id')))
                ->lockForUpdate()->pluck('payroll_id');
            $installments = DB::table('loan_installments')->whereIn('payroll_id', $payrolls)->get();
            DB::table('loan_installments')->whereIn('payroll_id', $payrolls)->whereNull('paid_at')->update(['paid_at' => now(), 'updated_at' => now()]);
            foreach ($installments->pluck('loan_id')->unique()->sort() as $loanId) {
                $loan = DB::table('employee_loans')->where('id', $loanId)->lockForUpdate()->first();
                $paid = DB::table('loan_installments')->where('loan_id', $loanId)->whereNotNull('paid_at')->sum('amount');
                // Only a loan with a balance can be finished by payments.
                if ($loan && (float) $loan->amount > 0 && (int) round($paid * 100) >= (int) round($loan->amount * 100)) DB::table('employee_loans')->where('id', $loanId)->update(['status' => 'repaid', 'updated_at' => now()]);
            }
            return DB::table('hr_payroll')->whereIn('payroll_id', $payrolls)->update(['status' => 'paid', 'updated_at' => now()]);
        });
    }

    /**
     * Throws away a payslip that is only calculated and works it out again,
     * so a change made after generating (a plus or minus row) is included.
     */
    /**
     * Works out again every payslip of one person that is still only
     * calculated - after a loan is added, stopped or changed, so the
     * deduction appears without anybody having to remember to regenerate.
     */
    public function recalculateOpen(int $employeeId): int
    {
        $n = 0;
        foreach (DB::table('hr_payroll')->where('employee_id', $employeeId)->where('kind', 'regular')
                     ->where('status', 'calculated')->pluck('period_start') as $start) {
            $this->recalculate($employeeId, PayPeriod::fromStart(substr((string) $start, 0, 10)));
            $n++;
        }

        return $n;
    }

    public function recalculate(int $employeeId, PayPeriod $period): ?int
    {
        return DB::transaction(function () use ($employeeId, $period) {
            $payslip = DB::table('hr_payroll')->where('employee_id', $employeeId)->where('kind', 'regular')
                ->where('period_start', $period->start)->lockForUpdate()->first();
            if ($payslip && $payslip->status !== 'calculated') {
                throw new \RuntimeException('This payslip is already '.$payslip->status.' - it can no longer change.');
            }
            if ($payslip) {
                DB::table('loan_installments')->where('payroll_id', $payslip->payroll_id)->delete();
                DB::table('overtime_requests')->where('payroll_id', $payslip->payroll_id)->update(['payroll_id' => null, 'updated_at' => now()]);
                DB::table('hr_payroll')->where('payroll_id', $payslip->payroll_id)->delete();
            }

            return $this->generate($employeeId, $period);
        });
    }
}
