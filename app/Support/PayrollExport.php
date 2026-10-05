<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The payroll sheet in the company's own layout: two header rows (RATE split
 * into per hour / per day, SSS into ER / EC / EE, HDMF and PHIC into ER / EE),
 * one row per payslip, surname first and sorted by surname.
 */
class PayrollExport
{
    public static function header(): array
    {
        $top = ['', 'LAST NAME', 'FIRST NAME', 'ALLOWANCE PER DAY', 'RATE', '', 'BASIC PAY', 'TOTAL ALLOWANCES',
            'TOTAL HOURS RENDERED', 'LATE', 'UNDERTIME', 'OT RENDERED', 'TOTAL HOLIDAY', 'INCENTIVE', 'ADJUSTMENT',
            'LODGING/SALARY DEDUCTION', 'TOTAL SALARY', 'SSS', '', '', 'HDMF', '', 'PHIC', '', 'SSS LOANS', 'HDMF LOANS',
            'TOTAL GOV CON', '13TH MONTH PAY', 'LEGAL HOLIDAY AMOUNT', 'LEGAL HOLIDAY', 'SPECIAL HOLIDAY AMOUNT',
            'SPECIAL HOLIDAY', 'OT HRS RENDERED', 'UNDERTIME MNS', 'LATE MNS', 'NET PAY'];
        $sub = array_fill(0, count($top), '');
        [$sub[4], $sub[5]] = ['PER HOUR', 'PER DAY'];
        [$sub[17], $sub[18], $sub[19]] = ['ER', 'EC', 'EE'];
        [$sub[20], $sub[21]] = ['ER', 'EE'];
        [$sub[22], $sub[23]] = ['ER', 'EE'];

        // Every column spans both rows except the four grouped ones.
        $merges = ['E1:F1', 'R1:T1', 'U1:V1', 'W1:X1'];
        foreach ($top as $i => $h) {
            if (! in_array($i, [4, 5, 17, 18, 19, 20, 21, 22, 23], true)) {
                $col = self::column($i);
                $merges[] = "{$col}1:{$col}2";
            }
        }

        return ['rows' => [$top, $sub], 'merges' => $merges];
    }

    /** @return list<array<int, mixed>> */
    public static function rows(string $periodStart, string $company): array
    {
        $payslips = DB::table('hr_payroll as p')
            ->join('employees as e', 'e.employee_id', '=', 'p.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('p.period_start', $periodStart)->where('p.kind', 'regular')
            ->whereRaw("COALESCE(NULLIF(e.company, ''), 'GKLASAM OPC') = ?", [$company])
            ->orderByRaw("COALESCE(NULLIF(u.last_name, ''), u.full_name)")->orderBy('u.first_name')
            ->get(['p.*', 'e.daily_rate', 'e.allowance as allowance_per_day', 'u.full_name', 'u.last_name', 'u.first_name', 'u.middle_name']);

        $loans = DB::table('loan_installments as i')->join('employee_loans as l', 'l.id', '=', 'i.loan_id')
            ->whereIn('i.payroll_id', $payslips->pluck('payroll_id'))
            ->selectRaw('i.payroll_id, l.type, SUM(i.amount) as amount')->groupBy('i.payroll_id', 'l.type')->get()
            ->groupBy('payroll_id');
        $adjustments = DB::table('payroll_adjustments')->where('status', 'approved')
            ->whereIn('employee_id', $payslips->pluck('employee_id'))
            ->whereBetween('effective_date', [$periodStart, $payslips->first()->period_end ?? $periodStart])
            ->get()->groupBy('employee_id');

        $n = 0;
        $out = [];
        foreach ($payslips as $p) {
            $m = fn ($v) => round((float) $v, 2);
            $daily = (float) $p->daily_rate;
            $hourly = $daily / 8;
            $late = (float) $p->late_deduction;
            $undertime = (float) $p->time_deduction;
            $basic = (float) $p->basic_pay - (float) $p->basic_adjustment + $late;
            $adj = $adjustments->get($p->employee_id, collect());
            $incentive = (float) $adj->where('type', 'addition')->sum('amount');
            $lodging = (float) $adj->where('type', 'deduction')->sum('amount');
            $byType = $loans->get($p->payroll_id, collect())->pluck('amount', 'type');
            $sssLoan = (float) ($byType['sss'] ?? 0);
            $hdmfLoan = (float) ($byType['pagibig'] ?? 0);
            $gov = (float) $p->sss + (float) $p->pagibig + (float) $p->philhealth + $sssLoan + $hdmfLoan;
            $holidays = (new \App\Services\HolidayPay)->forPeriod(
                DB::table('employees')->where('employee_id', $p->employee_id)->first(), $p->period_start, $p->period_end);

            $last = trim((string) $p->last_name);
            $full = trim((string) $p->full_name);
            $given = $last !== '' && str_ends_with(mb_strtolower($full), mb_strtolower($last))
                ? trim(mb_substr($full, 0, mb_strlen($full) - mb_strlen($last)))
                : trim($p->first_name.' '.$p->middle_name);

            $out[] = [
                ++$n, mb_strtoupper($last ?: $full), mb_strtoupper($given),
                $m($p->allowance_per_day), $m($hourly), $m($daily),
                $m($basic), $m($p->allowance), $m($p->paid_hours),
                $m($late), $m($undertime), $m($p->overtime_pay),
                $m((float) $p->legal_holiday_pay + (float) $p->special_holiday_pay),
                $m($incentive), $m($p->basic_adjustment), $m($lodging),
                $m($p->gross_pay),
                $m($p->employer_sss), $m($p->employer_ec), $m($p->sss),
                $m($p->employer_pagibig), $m($p->pagibig),
                $m($p->employer_philhealth), $m($p->philhealth),
                $m($sssLoan), $m($hdmfLoan), $m($gov),
                // This cutoff's share of the 13th month: basic earned / 12.
                $m(((float) $p->basic_pay) / 12),
                $m($p->legal_holiday_pay), (int) ($holidays['regularDays'] ?? 0),
                $m($p->special_holiday_pay), (int) (($holidays['specialDays'] ?? 0) + ($holidays['specialWorkingDays'] ?? 0)),
                $m($p->overtime_hours),
                $hourly > 0 ? (int) round($undertime / $hourly * 60) : 0,
                (int) $p->late_minutes,
                $m($p->net_pay),
            ];
        }

        return $out;
    }

    private static function column(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }
}
