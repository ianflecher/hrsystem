<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrReportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        PeopleAccess::hr();
        $data = $request->validate([
            'report' => 'required|in:headcount,attendance,leave,payroll,sss,philhealth,pagibig,bir',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'department_id' => 'nullable|integer|exists:departments,department_id',
        ]);

        $report = $data['report'];
        $from = $data['from'] ?? '1900-01-01';
        $to = $data['to'] ?? now()->toDateString();

        return response()->streamDownload(function () use ($report, $from, $to, $data) {
            $out = fopen('php://output', 'w');
            $write = fn (array $row) => fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row));

            foreach ($this->rows($report, $from, $to, $data['department_id'] ?? null) as $i => $row) {
                if ($i === 0) $write(array_keys((array) $row));
                $write((array) $row);
            }
            fclose($out);
        }, 'hr-'.$report.'-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function rows(string $report, string $from, string $to, ?int $departmentId)
    {
        return match ($report) {
            'attendance' => DB::table('hr_attendance as a')->join('employees as e', 'e.employee_id', '=', 'a.employee_id')->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
                ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
                ->whereBetween('a.date', [$from, $to])->orderBy('a.date')->select('a.date', 'u.full_name', 'd.department_name', 'a.time_in', 'a.time_out', 'a.status')->cursor(),
            'leave' => DB::table('leaves as l')->join('employees as e', 'e.employee_id', '=', 'l.employee_id')->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
                ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
                ->where('l.start_date', '<=', $to)->where('l.end_date', '>=', $from)->orderBy('l.start_date')->select('l.start_date', 'l.end_date', 'u.full_name', 'd.department_name', 'l.leave_type', 'l.status')->cursor(),
            'payroll' => DB::table('hr_payroll as p')->join('employees as e', 'e.employee_id', '=', 'p.employee_id')->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
                ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
                ->whereBetween('p.period_start', [$from, $to])->orderBy('p.period_start')->select('p.period_start', 'p.period_end', 'u.full_name', 'd.department_name', 'p.gross_pay', 'p.overtime_pay', 'p.loan_deduction', 'p.sss', 'p.philhealth', 'p.pagibig', 'p.tax', 'p.deductions', 'p.net_pay', 'p.status')->cursor(),
            'sss', 'philhealth', 'pagibig', 'bir' => DB::table('hr_payroll as p')->join('employees as e', 'e.employee_id', '=', 'p.employee_id')->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
                ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
                ->whereBetween('p.period_start', [$from, $to])->orderBy('p.period_start')
                ->select('p.period_start', 'p.period_end', 'u.full_name', 'd.department_name', 'p.gross_pay', 'p.sss', 'p.employer_sss', 'p.employer_ec', 'p.philhealth', 'p.employer_philhealth', 'p.pagibig', 'p.employer_pagibig', 'p.tax', 'p.net_pay')->cursor(),
            default => DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
                ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
                ->where('e.hire_date', '<=', $to)->orderBy('u.full_name')->select('u.full_name', 'd.department_name', 'e.job_title', 'e.status', 'e.hire_date', 'e.salary')->cursor(),
        };
    }
}
