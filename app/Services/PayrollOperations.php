<?php

namespace App\Services;

use App\Support\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollOperations
{
    public function refreshExceptions(PayPeriod $period): array
    {
        DB::table('payroll_exceptions')->where('period_start', $period->start)->where('period_end', $period->end)->delete();

        $rows = [];
        $employees = DB::table('employees')->whereIn('status', ['active', 'on_leave'])->get();
        foreach ($employees as $employee) {
            $id = (int) $employee->employee_id;
            if (($employee->pay_basis ?? 'monthly') === 'monthly' ? (float)$employee->salary <= 0 : (float)($employee->daily_rate ?? 0) <= 0) $rows[] = $this->exception($id, $period, 'missing_salary', 'high', 'Employee has no positive salary/rate for the configured pay basis.');
            if (empty($employee->pay_basis)) $rows[] = $this->exception($id, $period, 'missing_pay_basis', 'high', 'Pay basis is not configured.');
            if (($employee->pay_basis ?? 'monthly') !== 'monthly' && (float) ($employee->daily_rate ?? 0) <= 0) $rows[] = $this->exception($id, $period, 'missing_daily_rate', 'high', 'Daily/hourly employee is missing a daily rate.');
            if (empty($employee->work_region)) $rows[] = $this->exception($id, $period, 'missing_region', 'medium', 'Work region is not configured for wage-order validation.');
            if (empty($employee->sss_number) || empty($employee->philhealth_number) || empty($employee->pagibig_number) || empty($employee->tin)) {
                $rows[] = $this->exception($id, $period, 'missing_government_id', 'medium', 'One or more government identifiers are missing.');
            }
        }

        $pendingOt = DB::table('overtime_requests')->where('status', 'pending')->where('starts_at', '<', Carbon::parse($period->end)->addDay())->where('ends_at', '>=', $period->start)->get();
        foreach ($pendingOt as $ot) $rows[] = $this->exception((int) $ot->employee_id, $period, 'pending_ot', 'medium', 'Overtime request is still awaiting approval.');

        foreach ($rows as $row) DB::table('payroll_exceptions')->insert($row);
        return $rows;
    }

    public function variance(PayPeriod $period): array
    {
        $previous = PayPeriod::fromStart(Carbon::parse($period->start)->subMonthNoOverflow()->toDateString());
        $current = DB::table('hr_payroll')->where('period_start', $period->start)->get()->keyBy('employee_id');
        $prior = DB::table('hr_payroll')->where('period_start', $previous->start)->get()->keyBy('employee_id');
        $ids = collect($current->keys())->merge($prior->keys())->unique();
        return $ids->map(function ($id) use ($current, $prior) {
            $c = $current[$id] ?? null; $p = $prior[$id] ?? null;
            return ['employee_id' => (int) $id, 'current' => (float) ($c->net_pay ?? 0), 'previous' => (float) ($p->net_pay ?? 0), 'difference' => round((float) ($c->net_pay ?? 0) - (float) ($p->net_pay ?? 0), 2)];
        })->values()->all();
    }

    private function exception(int $employeeId, PayPeriod $period, string $code, string $severity, string $message): array
    {
        return ['employee_id' => $employeeId, 'period_start' => $period->start, 'period_end' => $period->end, 'code' => $code, 'severity' => $severity, 'message' => $message, 'resolved' => false, 'created_at' => now(), 'updated_at' => now()];
    }
}
