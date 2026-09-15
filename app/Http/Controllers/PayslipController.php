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
    public function __invoke(int $id)
    {
        $payslip = DB::table('hr_payroll as p')
            ->join('employees as e', 'e.employee_id', '=', 'p.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->where('p.payroll_id', $id)
            ->select('p.*', 'u.full_name', 'u.username', 'e.job_title', 'e.employee_id', 'd.department_name')
            ->first();

        abort_unless($payslip, 404);

        // An employee may print their own; HR may print anybody's.
        PeopleAccess::ownOrHr((int) $payslip->employee_id);

        return view('payslip', ['p' => $payslip]);
    }
}
