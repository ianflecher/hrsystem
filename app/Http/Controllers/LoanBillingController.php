<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The agency's loan billing statement (Pag-IBIG HQP-SLF-049 layout, the same
 * for SSS): every loan amortized that month, one sheet per company.
 */
class LoanBillingController extends Controller
{
    public function __invoke(Request $request)
    {
        PeopleAccess::hr();
        $data = $request->validate([
            'type' => 'required|in:pagibig,sss,government',
            'month' => 'required|date_format:Y-m',
        ]);

        $month = Carbon::parse($data['month'].'-01');
        $end = $month->copy()->endOfMonth();
        $agency = match ($data['type']) { 'pagibig' => 'Pag-IBIG', 'sss' => 'SSS', default => 'Other government' };
        $sheets = [];

        foreach (config('employers') as $company => $employer) {
            $loans = DB::table('employee_loans as l')->join('employees as e', 'e.employee_id', '=', 'l.employee_id')
                ->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->where('l.type', $data['type'])->whereIn('l.status', ['active', 'repaid', 'stopped'])
                ->whereRaw("COALESCE(NULLIF(e.company, ''), 'GKLASAM OPC') = ?", [$company])
                ->where(fn ($q) => $q->where('l.starts_on', '<=', $end->toDateString())->orWhere('l.term_from', '<=', $end->toDateString()))
                ->where(fn ($q) => $q->whereNull('l.term_to')->orWhere('l.term_to', '>=', $month->toDateString()))
                // A stopped loan is billed only up to the month it was stopped.
                ->where(fn ($q) => $q->where('l.status', 'active')->orWhere('l.updated_at', '>=', $month->toDateString()))
                ->orderBy('u.full_name')
                ->get(['l.*', 'u.full_name', 'e.pagibig_number', 'e.sss_number', 'e.philhealth_number']);

            $rows = [];
            $total = 0;
            foreach ($loans as $l) {
                [$last, $first, $middle] = $this->nameParts($l->full_name);
                $amount = round((float) $l->installment, 2);
                $total += $amount;
                $rows[] = [
                    (string) match ($data['type']) { 'pagibig' => $l->pagibig_number, 'sss' => $l->sss_number, default => $l->agency === 'PhilHealth' ? $l->philhealth_number : '' },
                    (string) $l->application_no,
                    $last, $first, $middle,
                    $this->loanType($l),
                    (string) $l->check_no,
                    $l->check_date ? Carbon::parse($l->check_date)->format('n/j/Y') : '',
                    $l->loan_value !== null ? number_format((float) $l->loan_value, 2, '.', '') : '',
                    $l->term_from ? Carbon::parse($l->term_from)->format('n/j/Y') : '',
                    $l->term_to ? Carbon::parse($l->term_to)->format('n/j/Y') : '',
                    number_format($amount, 2, '.', ''),
                ];
            }
            if (! $rows) continue;
            $rows[] = [];
            $rows[] = ['Total', '', '', '', '', '', '', '', '', '', '', number_format($total, 2, '.', '')];

            $title = match ($data['type']) { 'pagibig' => 'SHORT-TERM LOAN (STL) BILLING STATEMENT', 'sss' => 'SSS LOAN BILLING STATEMENT', default => 'OTHER GOVERNMENT LOANS' };
            $headers = [
                'rows' => [
                    [$title, '', '', '', '', '', '', '', '', '', '', $data['type'] === 'pagibig' ? 'HQP-SLF-049' : ''],
                    ['Employer', $employer['name'], '', '', '', '', '', $agency.' Employer ID No.', '', ($employer[$data['type'].'_id'] ?? '')],
                    ['Address', $employer['address'], '', '', '', '', '', 'Statement Date', '', $end->format('F j, Y')],
                    ['MID No.', 'Application No.', 'Last Name', 'First Name', 'Middle Name', 'Loan Type', 'DV/Check No.', 'DV Date', 'Loan Value', 'Loan Term From', 'Loan Term To', 'Amortization Amount'],
                ],
                'merges' => ['A1:K1', 'B2:G2', 'B3:G3', 'H2:I2', 'H3:I3', 'J2:L2', 'J3:L3'],
            ];
            $sheets[] = [$company.' '.$agency, $headers, $rows];
        }

        if (! $sheets) {
            return back()->with('status', "No {$agency} loans to bill for ".$month->format('F Y').'.');
        }

        return \App\Support\SpreadsheetWriter::downloadSheets(
            strtolower(str_replace('-', '', $agency)).'-loan-billing-'.$month->format('Y-m').'.xlsx', $sheets);
    }

    /** "Pintang, Fatima Jantoc" or "Fatima Jantoc Pintang" -> [PINTANG, FATIMA, JANTOC]. */
    private function nameParts(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if (str_contains($name, ',')) {
            [$last, $rest] = array_map('trim', explode(',', $name, 2));
            $parts = explode(' ', $rest);
            $middle = count($parts) > 1 ? array_pop($parts) : '';

            return [mb_strtoupper($last), mb_strtoupper(implode(' ', $parts)), mb_strtoupper($middle)];
        }
        $parts = explode(' ', $name);
        $last = count($parts) > 1 ? array_pop($parts) : '';
        $middle = count($parts) > 1 ? array_pop($parts) : '';

        return [mb_strtoupper($last), mb_strtoupper(implode(' ', $parts)), mb_strtoupper($middle)];
    }

    /** "(12 Months)" from the term, as the agency prints it. */
    private function loanType(object $l): string
    {
        $prefix = $l->type === 'government' && $l->agency ? $l->agency.' ' : '';
        if ($l->loan_type) return $prefix.$l->loan_type;
        if ($l->term_from && $l->term_to) {
            $months = Carbon::parse($l->term_from)->diffInMonths(Carbon::parse($l->term_to)) + 1;

            return $prefix.'('.(int) round($months).' Months)';
        }

        return match ($l->type) { 'pagibig' => 'STL', 'sss' => 'Salary Loan', default => trim($prefix) ?: 'Loan' };
    }
}
