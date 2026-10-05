{{-- One payslip, laid out as the company's own: used alone and four to a page. --}}
@php


    $isThirteenth = $p->kind === \App\Services\ThirteenthMonth::KIND;
    $money = fn ($amount) => (float) $amount == 0 ? '-' : number_format((float) $amount, 2);

    // Laid out as the company's own payslip is: the name surname first, the
    // day rate and hours beside it, then each line even when it is nothing.
    // From the full name, which already drops a word repeated between first
    // and middle name ("Patricia Ann" + "Ann Prudenciado"): surname first.
    $last = trim((string) ($p->last_name ?? ''));
    $full = trim((string) $p->full_name);
    $given = $last !== '' && str_ends_with(mb_strtolower($full), mb_strtolower($last))
        ? trim(mb_substr($full, 0, mb_strlen($full) - mb_strlen($last)))
        : trim(($p->first_name ?? '').' '.($p->middle_name ?? ''));
    $name = $last !== '' ? mb_strtoupper($last).', '.mb_strtoupper($given) : mb_strtoupper($full);
    $hours = (float) ($p->paid_hours ?? 0);
    $overtimeHours = (float) ($p->overtime_hours ?? 0);
    $lateAmount = (float) ($p->late_deduction ?? 0) > 0 ? (float) $p->late_deduction : (float) $p->time_deduction;
    // Basic is printed before the late penalty, and Late takes it off, as the
    // company's payslip does. (A monthly salary's lateness is a deduction.)
    $basic = round((float) $p->basic_pay - (float) ($p->basic_adjustment ?? 0) + (float) ($p->late_deduction ?? 0), 2);

    // Loans by kind, from what this payslip repaid.
    $loans = \Illuminate\Support\Facades\DB::table('loan_installments as i')
        ->join('employee_loans as l', 'l.id', '=', 'i.loan_id')
        ->where('i.payroll_id', $p->payroll_id)
        ->selectRaw('l.type, SUM(i.amount) as amount')->groupBy('l.type')->pluck('amount', 'type');
    $sssLoan = (float) ($loans['sss'] ?? 0);
    $hdmfLoan = (float) ($loans['pagibig'] ?? 0);
    $otherLoan = round((float) $p->loan_deduction - $sssLoan - $hdmfLoan, 2);

    // Anything the named lines do not cover is shown, so each side adds up.
    $namedEarnings = $basic - (float) ($p->late_deduction ?? 0) + (float) ($p->basic_adjustment ?? 0) + (float) ($p->allowance ?? 0)
        + (float) ($p->legal_holiday_pay ?? 0) + (float) ($p->special_holiday_pay ?? 0) + (float) $p->overtime_pay;
    $otherEarnings = round((float) $p->gross_pay - $namedEarnings, 2);
    $namedDeductions = (float) $p->sss + (float) $p->pagibig + (float) $p->philhealth + (float) $p->tax
        + (float) $p->time_deduction + (float) $p->loan_deduction;
    $otherDeductions = round((float) $p->deductions - $namedDeductions, 2);
@endphp
    <div class="pay-header">
        <div>
            <p class="eyebrow">{{ strtoupper($p->company ?: 'GKLASAM OPC') }}</p>
            <h1>Payslip</h1>
            <p class="period">
                @if ($isThirteenth)
                    13th Month Pay {{ \Illuminate\Support\Carbon::parse($p->period_start)->format('Y') }}
                @else
                    {{ \Illuminate\Support\Carbon::parse($p->period_start)->format('M j') }}-{{ \Illuminate\Support\Carbon::parse($p->period_end)->format('j, Y') }}
                @endif
            </p>
        </div>
        <div class="pay-id">
            <span>Payslip #{{ $p->payroll_id }}</span>
            <b>{{ \Illuminate\Support\Carbon::parse($p->updated_at)->format('M j, Y') }}</b>
        </div>
    </div>

    <div class="head">
        <dl class="row name"><dt>Employee</dt><dd>{{ $name }}</dd></dl>
        <dl class="row"><dt>Employee ID</dt><dd>{{ $p->employee_no ?: '—' }}</dd></dl>
        <dl class="row"><dt>Job Title</dt><dd>{{ strtoupper($p->job_title ?: '—') }}</dd></dl>
        <dl class="row"><dt>Department</dt><dd>{{ strtoupper($p->department_name ?: '—') }}</dd></dl>
        <dl class="row"><dt>Daily Rate</dt><dd>{{ number_format((float) ($p->daily_rate ?? 0), 2) }}</dd></dl>
        <dl class="row"><dt>Total Hours</dt><dd>@unless ($isThirteenth){{ rtrim(rtrim(number_format($hours, 2), '0'), '.') ?: '0' }}@else — @endunless</dd></dl>
    </div>

    <div class="box">
        <div>
            <h2>Earnings</h2>
            @if ($isThirteenth)
                <div class="line two"><span>13th Month Pay:</span><span>{{ $money($p->basic_pay) }}</span></div>
            @else
                <div class="line"><span>Basic Pay:</span><span></span><span>{{ $money($basic) }}</span></div>
                <div class="line"><span>Late:</span><span></span><span>{{ $lateAmount > 0 ? '('.number_format($lateAmount, 2).')' : '-' }}</span></div>
                <div class="line"><span>Basic Pay Adj.:</span><span></span><span>{{ $money($p->basic_adjustment ?? 0) }}</span></div>
                <div class="line"><span>Allowances:</span><span></span><span>{{ $money($p->allowance ?? 0) }}</span></div>
                <div class="line"><span>Legal Holiday:</span><span></span><span>{{ $money($p->legal_holiday_pay ?? 0) }}</span></div>
                <div class="line"><span>Special Holiday:</span><span></span><span>{{ $money($p->special_holiday_pay ?? 0) }}</span></div>
                <div class="line"><span>Total OT:</span><span class="qty">{{ rtrim(rtrim(number_format($overtimeHours, 2), '0'), '.') ?: '0' }}</span><span>{{ $money($p->overtime_pay) }}</span></div>
                @if ($otherEarnings != 0)
                    <div class="line"><span>Other Earnings:</span><span></span><span>{{ $money($otherEarnings) }}</span></div>
                @endif
            @endif
        </div>
        <div>
            <h2>Deductions</h2>
            <div class="line two"><span>SSS Premium:</span><span>{{ $money($p->sss) }}</span></div>
            <div class="line two"><span>HDMF Premium:</span><span>{{ $money($p->pagibig) }}</span></div>
            <div class="line two"><span>PHIC Premium:</span><span>{{ $money($p->philhealth) }}</span></div>
            <div class="line two"><span>SSS Loan:</span><span>{{ $money($sssLoan) }}</span></div>
            <div class="line two"><span>HDMF Loan:</span><span>{{ $money($hdmfLoan) }}</span></div>
            @if ((float) $p->tax != 0)
                <div class="line two"><span>Withholding Tax:</span><span>{{ $money($p->tax) }}</span></div>
            @endif
            @if ($otherLoan != 0)
                <div class="line two"><span>Other Loan:</span><span>{{ $money($otherLoan) }}</span></div>
            @endif
            @if ($otherDeductions != 0)
                <div class="line two"><span>Other Deductions:</span><span>{{ $money($otherDeductions) }}</span></div>
            @endif
        </div>
    </div>

    <div class="totals">
        <div class="line two"><span>GROSS PAY</span><span>{{ number_format((float) $p->gross_pay, 2) }}</span></div>
        <div class="line two"><span>TOTAL DEDUCTION</span><span>{{ number_format((float) $p->deductions, 2) }}</span></div>
    </div>
    <div class="net"><span>NET PAY</span><b>{{ number_format((float) $p->net_pay, 2) }}</b></div>

    <div class="signature">EMPLOYEE SIGNATURE: <span></span></div>
