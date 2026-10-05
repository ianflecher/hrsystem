<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Illuminate\Support\Facades\DB;

/**
 * One payslip, laid out to be printed or saved as PDF from the browser.
 *
 * Printing goes through the browser rather than a PDF library: it needs no
 * dependency, it saves to PDF on every platform the staff actually use, and
 * what appears on paper is the same page they were just looking at.
 */
class PayslipController extends Controller
{
    /** All of a cutoff's payslips for one company, four to a bond paper. */
    public function batch(\Illuminate\Http\Request $request)
    {
        PeopleAccess::pay();
        $data = $request->validate([
            'period' => 'required|date_format:Y-m-d',
            'company' => 'required|in:GKLASAM OPC,Imprint Cafe',
        ]);
        $period = \App\Support\PayPeriod::fromStart($data['period']);

        $payslips = DB::table('hr_payroll as p')
            ->join('employees as e', 'e.employee_id', '=', 'p.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->where('p.period_start', $period->start)
            ->where('p.kind', 'regular')
            ->whereRaw("COALESCE(NULLIF(e.company, ''), 'GKLASAM OPC') = ?", [$data['company']])
            ->select('p.*', 'u.full_name', 'u.first_name', 'u.middle_name', 'u.last_name', 'u.username', 'e.job_title', 'e.employee_id', 'e.employee_no', 'e.company', 'e.daily_rate', 'd.department_name')
            ->orderByRaw("COALESCE(NULLIF(u.last_name, ''), u.full_name)")
            ->orderByRaw("COALESCE(NULLIF(u.first_name, ''), u.full_name)")
            ->get();

        return view('payslips-batch', ['payslips' => $payslips, 'company' => $data['company'], 'label' => $period->label()]);
    }

    public function __invoke(int $id)
    {
        $payslip = DB::table('hr_payroll as p')
            ->join('employees as e', 'e.employee_id', '=', 'p.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->where('p.payroll_id', $id)
            ->select('p.*', 'u.full_name', 'u.first_name', 'u.middle_name', 'u.last_name', 'u.username', 'e.job_title', 'e.employee_id', 'e.employee_no', 'e.company', 'e.daily_rate', 'd.department_name')
            ->first();

        abort_unless($payslip, 404);

        // An employee may print their own; HR may print anybody's.
        PeopleAccess::ownOrHr((int) $payslip->employee_id);
        abort_unless(PeopleAccess::canSeePay() || PeopleAccess::employeeId() === (int) $payslip->employee_id, 403);
        // An employee sees their payslip once HR has approved it.
        abort_unless(PeopleAccess::canSeePay() || in_array($payslip->status, ['approved', 'paid'], true), 404);

        return view('payslip', ['p' => $payslip]);
    }
}
