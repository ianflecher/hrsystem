<?php

/*
 * The company's leave policy.
 *
 * Paid leave comes from a yearly allowance; once it is used up, any further
 * leave is unpaid. Employees share one allowance across sick, vacation and
 * emergency leave; supervisors have one per type.
 */
return [

    /** The leave types the paid allowance covers. */
    'paid_types' => ['sick', 'vacation', 'emergency'],

    /** Employees: one allowance, in days a year, shared by the paid types. */
    'employee_days_per_year' => (float) env('LEAVE_EMPLOYEE_DAYS', 7),

    /** Supervisors: days a year for each paid type. */
    'supervisor_days_per_year' => [
        'sick' => 4,
        'vacation' => 4,
        'emergency' => 1,
    ],

    /**
     * Leave paid in full each time it happens, whatever was taken before:
     * days per occasion. More days than this in one request are unpaid.
     */
    'per_occasion' => [
        'bereavement' => 3,
    ],

    /**
     * Who decides supervisors' leave before HR - a user_id. Her own leave
     * goes straight to HR. Set LEAVE_SUPERVISOR_APPROVER to change it.
     */
    'supervisor_approver_user_id' => (int) env('LEAVE_SUPERVISOR_APPROVER', 28149),
];
