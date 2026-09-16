<?php

/*
|--------------------------------------------------------------------------
| Statutory contributions
|--------------------------------------------------------------------------
|
| SSS, PhilHealth and Pag-IBIG, in one place, so they can be corrected without
| touching payroll code.
|
| CONFIRM THESE AGAINST THE CURRENT CIRCULARS BEFORE ANYONE IS PAID FROM THEM.
| The rates below are the published figures as understood at the time of
| writing; they change by circular, usually in January, and this file is not
| the authority on them - your accountant is. Every number here is meant to be
| edited.
|
| The figures they replaced were worse than out of date: PhilHealth was fixed
| at 4% when the premium is 5%, SSS was a set of round brackets nobody had
| checked, and Pag-IBIG was a flat 100 with no rate behind it at all.
|
*/

return [

    /*
    | When the monthly contributions come off.
    |
    | 'second_cutoff' takes the whole month on the 16th-to-end payslip, which
    | makes the two payslips different sizes. 'split' halves each contribution
    | across both cutoffs, which most staff find easier to read. Neither is
    | more correct - it is a company decision about timing, not about amount.
    */
    'timing' => env('PAYROLL_CONTRIBUTION_TIMING', 'split'),

    /*
    | SSS - employee share.
    |
    | The contribution is a percentage of the Monthly Salary Credit, which
    | moves in steps rather than following the salary exactly, and is held
    | between a floor and a ceiling. Set 'step' to 0 to use the salary itself.
    */
    'sss' => [
        'employee_rate' => 0.05,
        'msc_floor'     => 5000,
        'msc_ceiling'   => 35000,
        'step'          => 500,
    ],

    /*
    | PhilHealth - employee share.
    |
    | The premium is a percentage of the monthly basic salary, shared equally
    | between employer and employee, and bounded by a floor and a ceiling.
    */
    'philhealth' => [
        'premium_rate'   => 0.05,
        'employee_share' => 0.5,
        'salary_floor'   => 10000,
        'salary_ceiling' => 100000,
    ],

    /*
    | Pag-IBIG - employee share.
    |
    | A percentage of monthly compensation, capped. The cap is what makes the
    | familiar flat 100 appear for anybody earning above it.
    */
    'pagibig' => [
        'employee_rate' => 0.02,
        'salary_cap'    => 5000,
    ],

];
