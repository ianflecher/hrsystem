<?php

namespace App\Services;

use App\Support\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollPreview
{
    public function period(PayPeriod $period): array
    {
        $employees = DB::table('employees')->where('status', 'active')->get();
        $rows = [];
        $totals = ['employees'=>0,'gross'=>0,'deductions'=>0,'net'=>0,'employer_cost'=>0];
        $exceptions = [];

        foreach ($employees as $employee) {
            $warnings = app(PhilippinePayrollCompliance::class)->profileWarnings($employee);
            if ($warnings) $exceptions[$employee->employee_id] = $warnings;
            try {
                $calc = app(PayrollRun::class)->preview($employee, $period);
                $rows[] = array_merge(['employee_id'=>$employee->employee_id], $calc);
                $totals['employees']++;
                foreach (['gross','deductions','net','employer_cost'] as $key) $totals[$key] += (float)($calc[$key] ?? 0);
            } catch (\Throwable $e) {
                $exceptions[$employee->employee_id][] = $e->getMessage();
            }
        }
        foreach (['gross','deductions','net','employer_cost'] as $key) $totals[$key] = round($totals[$key], 2);
        return compact('rows','totals','exceptions');
    }

    public function rebuildExceptions(PayPeriod $period): int
    {
        $count = 0;
        foreach ($this->period($period)['exceptions'] as $employeeId => $messages) {
            foreach (array_unique($messages) as $message) {
                $type = str_contains(strtolower($message), 'attendance') ? 'attendance' : (str_contains(strtolower($message), 'wage') ? 'wage_compliance' : 'payroll_profile');
                $date = $period->start;
                $exists = DB::table('attendance_exceptions')->where('employee_id',$employeeId)->where('work_date',$date)->where('type',$type)->exists();
                if (!$exists) {
                    DB::table('attendance_exceptions')->insert(['employee_id'=>$employeeId,'work_date'=>$date,'type'=>$type,'severity'=>'high','details'=>$message,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);
                    $count++;
                }
            }
        }
        return $count;
    }
}
