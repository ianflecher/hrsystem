<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A month's SSS, PhilHealth and Pag-IBIG contributions per employee, from the
 * payslips of both cutoffs - the page and its Excel download read the same.
 */
class MonthlyContributions
{
    public const AMOUNTS = ['sss', 'employer_sss', 'employer_ec', 'philhealth', 'employer_philhealth', 'pagibig', 'employer_pagibig'];

    public static function for(Carbon $month): Collection
    {
        return DB::table('hr_payroll as p')
            ->join('employees as e', 'e.employee_id', '=', 'p.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->whereBetween('p.period_start', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->groupBy('e.employee_id', 'u.full_name', 'e.company', 'e.sss_number', 'e.philhealth_number', 'e.pagibig_number')
            ->selectRaw("u.full_name, COALESCE(NULLIF(e.company, ''), 'GKLASAM OPC') company,
                e.sss_number, e.philhealth_number, e.pagibig_number,
                SUM(p.sss) sss, SUM(p.employer_sss) employer_sss, SUM(p.employer_ec) employer_ec,
                SUM(p.philhealth) philhealth, SUM(p.employer_philhealth) employer_philhealth,
                SUM(p.pagibig) pagibig, SUM(p.employer_pagibig) employer_pagibig,
                COUNT(*) slips, SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) paid_slips")
            ->get()
            ->map(function ($r) {
                foreach (self::AMOUNTS as $k) $r->$k = round((float) $r->$k, 2);
                $w = explode(' ', trim(preg_replace('/\s+/', ' ', $r->full_name)));
                $r->last = count($w) > 1 ? array_pop($w) : '';
                $r->middle = count($w) > 1 ? array_pop($w) : '';
                $r->first = implode(' ', $w);
                $r->name = $r->last !== '' ? $r->last.', '.trim($r->first.' '.$r->middle) : $r->full_name;
                $r->total = round(array_sum(array_map(fn ($k) => $r->$k, self::AMOUNTS)), 2);

                return $r;
            })
            ->filter(fn ($r) => $r->total > 0)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
}
