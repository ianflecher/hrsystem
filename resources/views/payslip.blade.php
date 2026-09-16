@php
    use App\Services\ThirteenthMonth;

    $isThirteenth = $p->kind === ThirteenthMonth::KIND;
    $basic = round((float) $p->gross_pay - (float) $p->overtime_pay - (float) $p->holiday_pay, 2);

    $earnings = array_filter([
        ($isThirteenth ? '13th month pay' : 'Basic pay for the period') => $basic,
        'Overtime'         => (float) $p->overtime_pay,
        'Holiday premium'  => (float) $p->holiday_pay,
    ], fn ($amount) => $amount != 0);

    $deductions = array_filter([
        'SSS'                        => (float) $p->sss,
        'PhilHealth'                 => (float) $p->philhealth,
        'Pag-IBIG'                   => (float) $p->pagibig,
        'Tax'                        => (float) $p->tax,
        'Late, undertime and absence'=> (float) $p->time_deduction,
        'Loan repayment'             => (float) $p->loan_deduction,
    ], fn ($amount) => $amount != 0);

    // Anything the columns do not account for, so the payslip always adds up.
    $accounted = round(array_sum($deductions), 2);
    $unaccounted = round((float) $p->deductions - $accounted, 2);

    if ($unaccounted != 0) {
        $deductions['Other deductions'] = $unaccounted;
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip · {{ $p->full_name }} · {{ $p->period_start }}</title>
    <style>
        :root { --ink: #17233a; --muted: #64748b; --line: #dce1e9; --brand: #E31B23; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 32px 20px;
            background: #f1f5f9;
            color: var(--ink);
            font: 14px/1.6 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .sheet {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 36px 40px;
        }

        .actions { max-width: 760px; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; }

        .actions button, .actions a {
            font: inherit; font-weight: 600; font-size: 13px;
            padding: 9px 16px; border-radius: 8px; border: 1px solid var(--line);
            background: #fff; color: var(--ink); text-decoration: none; cursor: pointer;
        }

        .actions button { background: var(--brand); border-color: var(--brand); color: #fff; }

        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px;
                 border-bottom: 2px solid var(--ink); padding-bottom: 18px; }

        .company { font-size: 18px; font-weight: 750; letter-spacing: -.3px; }
        .company span { display: block; font-size: 12px; font-weight: 500; color: var(--muted); letter-spacing: .06em; text-transform: uppercase; }

        h1 { margin: 0; font-size: 15px; font-weight: 700; text-align: right; text-transform: uppercase; letter-spacing: .08em; }
        h1 span { display: block; font-size: 13px; font-weight: 500; text-transform: none; letter-spacing: 0; color: var(--muted); margin-top: 4px; }

        .who { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 32px; margin: 22px 0 26px; }
        .who div { font-size: 13px; }
        .who dt { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .06em; }
        .who dd { margin: 2px 0 0; font-weight: 600; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        caption { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase;
                  letter-spacing: .08em; color: var(--muted); padding-bottom: 8px; }
        td { padding: 7px 0; border-bottom: 1px solid var(--line); }
        td + td { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tfoot td { font-weight: 700; border-bottom: 2px solid var(--ink); }

        .net { display: flex; justify-content: space-between; align-items: baseline;
               border: 2px solid var(--ink); border-radius: 10px; padding: 16px 20px; margin-top: 4px; }
        .net strong { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
        .net b { font-size: 24px; font-variant-numeric: tabular-nums; }

        .note { margin-top: 20px; font-size: 12px; color: var(--muted); }
        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; background: #eef2f6;
                  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }

        footer { margin-top: 28px; padding-top: 16px; border-top: 1px solid var(--line);
                 font-size: 11px; color: var(--muted); display: flex; justify-content: space-between; gap: 16px; }

        .signature { margin-top: 44px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 40px; }
        .signature div { border-top: 1px solid var(--ink); padding-top: 6px; font-size: 11px; color: var(--muted); }

        @media (max-width: 640px) {
            body { padding: 16px; }
            .sheet { padding: 24px 20px; }
            header { flex-direction: column; }
            h1 { text-align: left; }
            .who, .signature { grid-template-columns: 1fr; }
        }

        /* What actually goes on paper: the page itself, nothing around it. */
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .sheet { max-width: none; border: 0; border-radius: 0; padding: 0; }
            table, .net, .signature { page-break-inside: avoid; }
            @page { margin: 16mm; }
        }
    </style>
</head>
<body>

<div class="actions">
    <a href="{{ url()->previous() }}">Back</a>
    <button type="button" onclick="window.print()">Print or save as PDF</button>
</div>

<div class="sheet">
    <header>
        <div class="company">
            Imprint Customs PH
            <span>Payslip</span>
        </div>
        <h1>
            {{ $isThirteenth ? '13th month pay' : 'Pay period' }}
            <span>
                @if ($isThirteenth)
                    {{ \Illuminate\Support\Carbon::parse($p->period_start)->format('Y') }}
                @else
                    {{ \Illuminate\Support\Carbon::parse($p->period_start)->format('j M Y') }}
                    &ndash;
                    {{ \Illuminate\Support\Carbon::parse($p->period_end)->format('j M Y') }}
                @endif
            </span>
        </h1>
    </header>

    <dl class="who">
        <div><dt>Employee</dt><dd>{{ $p->full_name }}</dd></div>
        <div><dt>Position</dt><dd>{{ $p->job_title ?: '—' }}</dd></div>
        <div><dt>Department</dt><dd>{{ $p->department_name ?: '—' }}</dd></div>
        <div><dt>Status</dt><dd><span class="status">{{ $p->status }}</span></dd></div>
    </dl>

    <table>
        <caption>Earnings</caption>
        <tbody>
            @foreach ($earnings as $label => $amount)
                <tr><td>{{ $label }}</td><td>{{ number_format($amount, 2) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td>Gross pay</td><td>{{ number_format((float) $p->gross_pay, 2) }}</td></tr>
        </tfoot>
    </table>

    <table>
        <caption>Deductions</caption>
        <tbody>
            @forelse ($deductions as $label => $amount)
                <tr><td>{{ $label }}</td><td>{{ number_format($amount, 2) }}</td></tr>
            @empty
                <tr><td>None</td><td>0.00</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td>Total deductions</td><td>{{ number_format((float) $p->deductions, 2) }}</td></tr>
        </tfoot>
    </table>

    <div class="net">
        <strong>Net pay</strong>
        <b>PHP {{ number_format((float) $p->net_pay, 2) }}</b>
    </div>

    @if ($p->notes)
        <p class="note">{{ $p->notes }}</p>
    @endif

    <div class="signature">
        <div>Prepared by</div>
        <div>Received by &mdash; {{ $p->full_name }}</div>
    </div>

    <footer>
        <span>Payslip #{{ $p->payroll_id }} · {{ $p->username }}</span>
        <span>Printed {{ now()->format('j M Y, g:i A') }}</span>
    </footer>
</div>

</body>
</html>
