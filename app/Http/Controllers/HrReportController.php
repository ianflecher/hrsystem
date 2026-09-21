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
        // Leave is filed before it is taken, so a window ending today hides
        // every upcoming request - which is most of what a leave report is
        // for. The other reports are records of what has already happened.
        $to = $data['to'] ?? ($report === 'leave'
            ? now()->addYear()->toDateString()
            : now()->toDateString());

        return response()->streamDownload(function () use ($report, $from, $to, $data) {
            $out = fopen('php://output', 'w');
            $write = fn (array $row) => fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row));

            // Written before the query runs, so a report with nothing in it
            // still downloads as a readable file. It used to come out zero
            // bytes, which is indistinguishable from a failed download.
            $write($this->columns($report));

            foreach ($this->rows($report, $from, $to, $data['department_id'] ?? null) as $row) {
                $write((array) $row);
            }
            fclose($out);
        }, 'hr-'.$report.'-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * The header row, in the order the matching select in rows() returns it.
     *
     * Kept here rather than read off the first row, so that a report with no
     * rows still has headers. ReportDownloadTest walks every report and checks
     * these against the real column names, so the two cannot drift apart.
     *
     * @return array<int, string>
     */
    private function columns(string $report): array
    {
        return match ($report) {
            'attendance' => ['date', 'full_name', 'department_name', 'time_in', 'time_out', 'status'],
            'leave' => ['start_date', 'end_date', 'full_name', 'department_name', 'leave_type', 'status'],
            'payroll' => ['period_start', 'period_end', 'full_name', 'department_name', 'gross_pay',
                          'overtime_pay', 'loan_deduction', 'sss', 'philhealth', 'pagibig', 'tax',
                          'deductions', 'net_pay', 'status'],
            'sss', 'philhealth', 'pagibig', 'bir' => ['period_start', 'period_end', 'full_name',
                          'department_name', 'gross_pay', 'sss', 'employer_sss', 'employer_ec',
                          'philhealth', 'employer_philhealth', 'pagibig', 'employer_pagibig',
                          'tax', 'net_pay'],
            default => ['full_name', 'department_name', 'job_title', 'status', 'hire_date', 'salary'],
        };
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
