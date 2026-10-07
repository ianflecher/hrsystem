<?php

/**
 * Each company as the agencies know it - printed on the loan billing
 * statements. Fill in what is missing from the agency's own papers.
 */
return [
    'Imprint Cafe' => [
        'name' => 'IMPRINT CAFE',
        'address' => '322, MARCOS HWY, MAYAMOT, 1870 ANTIPOLO CITY, RIZAL',
        'pagibig_id' => env('CAFE_PAGIBIG_EMPLOYER_ID', '210562570009'),
        'sss_id' => env('CAFE_SSS_EMPLOYER_ID', ''),
    ],
    'GKLASAM OPC' => [
        'name' => 'GKLASAM OPC',
        'address' => env('GKLASAM_ADDRESS', ''),
        'pagibig_id' => env('GKLASAM_PAGIBIG_EMPLOYER_ID', ''),
        'sss_id' => env('GKLASAM_SSS_EMPLOYER_ID', ''),
    ],
];
