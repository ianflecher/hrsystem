<?php

return [
    'version' => env('PH_PAYROLL_RULE_VERSION', 'PH-STAT-2026-09'),
    'timing' => env('PAYROLL_CONTRIBUTION_TIMING', 'split'),
    'sources' => [
        'SSS' => 'https://www.sss.gov.ph/pay-contribution/',
        'PhilHealth' => 'https://www.philhealth.gov.ph/',
        'Pag-IBIG' => 'https://www.pagibigfund.gov.ph/',
        'BIR' => 'https://www.bir.gov.ph/',
        'DOLE' => 'https://dole.gov.ph/',
    ],

    // Philippine statutory defaults. Keep changes versioned and verify against
    // the latest agency issuances before production use.
    'sss' => [
        'employee_rate' => 0.05,
        'employer_rate' => 0.10,
        'msc_floor' => 5000,
        'msc_ceiling' => 35000,
        'step' => 500,
        'ec_below_15000' => 10,
        'ec_15000_and_above' => 30,
    ],
    'philhealth' => [
        'premium_rate' => 0.05,
        'employee_share' => 0.5,
        'employer_share' => 0.5,
        'salary_floor' => 10000,
        'salary_ceiling' => 100000,
    ],
    'pagibig' => [
        'employee_rate_low' => 0.01,
        'employee_rate' => 0.02,
        'employer_rate' => 0.02,
        'rate_threshold' => 1500,
        'salary_cap' => 10000,
    ],
    'nsd' => ['rate' => 0.10, 'start' => '22:00', 'end' => '06:00'],
    'bir' => [
        'frequency' => 'semi_monthly',
        'tax_free_to' => 10417.00,
        'brackets' => [
            ['to' => 16666.00, 'fixed' => 0, 'rate' => 0.15, 'over' => 10417.00],
            ['to' => 33332.00, 'fixed' => 937.50, 'rate' => 0.20, 'over' => 16667.00],
            ['to' => 83332.00, 'fixed' => 4270.70, 'rate' => 0.25, 'over' => 33333.00],
            ['to' => 333332.00, 'fixed' => 16770.70, 'rate' => 0.30, 'over' => 83333.00],
            ['to' => 999999999.00, 'fixed' => 91770.70, 'rate' => 0.35, 'over' => 333333.00],
        ],
    ],
    'holiday' => [
        'regular' => 2.00,
        'regular_rest' => 2.60,
        'special_non_working' => 1.30,
        'special_non_working_rest' => 1.50,
        'special_working' => 1.00,
            'special_non_working_no_work_no_pay' => true,
    ],
    'overtime' => ['ordinary' => 1.25, 'rest_or_special' => 1.30, 'regular_holiday' => 1.30],
];
