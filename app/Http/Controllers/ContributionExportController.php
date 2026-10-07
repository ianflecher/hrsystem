<?php

namespace App\Http\Controllers;

use App\Support\MonthlyContributions;
use App\Support\PeopleAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** The month's contributions as an Excel workbook, one sheet per company. */
class ContributionExportController extends Controller
{
    public function __invoke(Request $request)
    {
        PeopleAccess::hr();
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $month = Carbon::parse($data['month'].'-01');
        $n = fn ($v) => number_format((float) $v, 2, '.', '');

        $sheets = [];
        foreach (MonthlyContributions::for($month)->groupBy('company')->sortKeys() as $company => $people) {
            $employer = config('employers.'.$company, ['name' => $company, 'address' => '']);
            $rows = [];
            foreach ($people as $r) {
                $rows[] = [$r->last, $r->first, $r->middle, (string) $r->sss_number, (string) $r->philhealth_number, (string) $r->pagibig_number,
                    $n($r->sss), $n($r->employer_sss), $n($r->employer_ec), $n($r->sss + $r->employer_sss + $r->employer_ec),
                    $n($r->philhealth), $n($r->employer_philhealth), $n($r->philhealth + $r->employer_philhealth),
                    $n($r->pagibig), $n($r->employer_pagibig), $n($r->pagibig + $r->employer_pagibig),
                    $n($r->total)];
            }
            $sum = fn ($k) => $n($people->sum($k));
            $rows[] = [];
            $rows[] = ['Total', '', '', '', '', '',
                $sum('sss'), $sum('employer_sss'), $sum('employer_ec'), $n($people->sum('sss') + $people->sum('employer_sss') + $people->sum('employer_ec')),
                $sum('philhealth'), $sum('employer_philhealth'), $n($people->sum('philhealth') + $people->sum('employer_philhealth')),
                $sum('pagibig'), $sum('employer_pagibig'), $n($people->sum('pagibig') + $people->sum('employer_pagibig')),
                $sum('total')];

            $sheets[] = [$company.' '.$month->format('M Y'), ['rows' => [
                ['MONTHLY CONTRIBUTIONS - '.mb_strtoupper($month->format('F Y'))],
                ['Employer', $employer['name'], '', '', 'Address', $employer['address']],
                ['', '', '', '', '', '', 'SSS', '', '', '', 'PhilHealth', '', '', 'Pag-IBIG', '', '', ''],
                ['Last Name', 'First Name', 'Middle Name', 'SSS No.', 'PhilHealth No.', 'Pag-IBIG No.',
                    'EE', 'ER', 'EC', 'SSS Total', 'EE', 'ER', 'PhilHealth Total', 'EE', 'ER', 'Pag-IBIG Total', 'Grand Total'],
            ], 'merges' => ['A1:Q1', 'B2:D2', 'F2:Q2', 'G3:J3', 'K3:M3', 'N3:P3']], $rows];
        }

        if (! $sheets) {
            return back()->with('status', 'No payslips with contributions in '.$month->format('F Y').' yet.');
        }

        return \App\Support\SpreadsheetWriter::downloadSheets('contributions-'.$month->format('Y-m').'.xlsx', $sheets);
    }
}
