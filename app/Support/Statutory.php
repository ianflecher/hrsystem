<?php

namespace App\Support;

/**
 * The contribution rates, wherever they are being read from.
 *
 * config/statutory.php is where they are edited. This reads that when there is
 * an application to read it from, and falls back to the same figures when
 * there is not - so the payroll arithmetic stays testable on its own, and a
 * missing or partial config file can never silently zero somebody's
 * contributions.
 *
 * CONFIRM THE FIGURES AGAINST THE CURRENT CIRCULARS. They change by circular,
 * usually in January, and neither this file nor the config is the authority on
 * them.
 */
class Statutory
{
    public const DEFAULTS = [
        'timing' => 'split',

        'sss' => [
            'employee_rate' => 0.05,
            'msc_floor'     => 5000,
            'msc_ceiling'   => 35000,
            'step'          => 500,
        ],

        'philhealth' => [
            'premium_rate'   => 0.05,
            'employee_share' => 0.5,
            'salary_floor'   => 10000,
            'salary_ceiling' => 100000,
        ],

        'pagibig' => [
            'employee_rate' => 0.02,
            'salary_cap'    => 5000,
        ],
    ];

    /** @return array<string, mixed> the configured table, over the defaults */
    public static function table(string $name): array
    {
        $defaults = self::DEFAULTS[$name] ?? [];
        $configured = self::read($name);

        return is_array($configured) ? array_merge($defaults, $configured) : $defaults;
    }

    public static function timing(): string
    {
        $configured = self::read('timing');

        return is_string($configured) && $configured !== '' ? $configured : self::DEFAULTS['timing'];
    }

    /**
     * Reads config when there is an application; returns null when there is
     * not, rather than throwing - the arithmetic has to work in a plain test.
     */
    private static function read(string $key)
    {
        try {
            return function_exists('config') ? config('statutory.'.$key) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
