<?php

return [
    'services' => [
        'hris' => [
            'name' => 'HRIS',
            'description' => 'People, attendance, leave, overtime and payroll.',
            'url' => env('CENTRAL_ADMIN_HRIS_URL', env('APP_URL', 'http://localhost')),
            'health_url' => env('CENTRAL_ADMIN_HRIS_HEALTH_URL', env('APP_URL', 'http://localhost')),
            'icon' => 'fa-users-gear',
            'accent' => 'red',
        ],
        'production' => [
            'name' => 'Imprint Production',
            'description' => 'Production orders and shop-floor operations.',
            'url' => env('CENTRAL_ADMIN_PRODUCTION_URL', 'http://127.0.0.1:30081'),
            'health_url' => env('CENTRAL_ADMIN_PRODUCTION_HEALTH_URL', env('CENTRAL_ADMIN_PRODUCTION_URL', 'http://127.0.0.1:30081')),
            'icon' => 'fa-industry',
            'accent' => 'blue',
        ],
        'marketing' => [
            'name' => 'Imprint Marketing',
            'description' => 'Marketing content, campaigns and customer work.',
            'url' => env('CENTRAL_ADMIN_MARKETING_URL', 'http://127.0.0.1:30082'),
            'health_url' => env('CENTRAL_ADMIN_MARKETING_HEALTH_URL', env('CENTRAL_ADMIN_MARKETING_URL', 'http://127.0.0.1:30082')),
            'icon' => 'fa-bullhorn',
            'accent' => 'amber',
        ],
    ],
];
