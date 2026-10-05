<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslips · {{ $company }} · {{ $label }}</title>
    <style>
        :root { --ink: #111827; --muted: #4b5563; --line: #111827; --soft: #f8fafc; --brand: #E31B23; --brand-dark: #991b1b; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; background: #f1f5f9; color: var(--ink);
               font: 8.4pt/1.45 Calibri, 'Segoe UI', -apple-system, sans-serif; }
        .actions { max-width: 216mm; margin: 0 auto 14px; display: flex; gap: 10px; justify-content: space-between; align-items: center; font-size: 13px; }
        .actions button, .actions a { font: inherit; font-weight: 600; padding: 9px 16px; border-radius: 8px;
            border: 1px solid #dce1e9; background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
        .actions button { background: var(--brand); border-color: var(--brand); color: #fff; }

        /* A bond paper (short, 8.5 x 11 in): four payslips, two by two, with cut lines. */
        .page { width: 216mm; height: 279mm; margin: 0 auto 14px; background: #fff; padding: 6mm;
                display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
        .slip { padding: 4mm 5mm; overflow: hidden; border: 1px dashed #9ca3af; }

        .pay-header { display: flex; justify-content: space-between; gap: 8px; padding-bottom: 4px; margin-bottom: 5px; border-bottom: 2px solid var(--brand); }
        .eyebrow { margin: 0 0 1px; color: var(--brand-dark); font-size: 6.8pt; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 13pt; line-height: 1; letter-spacing: 0; }
        .period { margin: 2px 0 0; color: var(--muted); font-weight: 700; }
        .pay-id { min-width: 56px; text-align: right; color: var(--muted); font-size: 6.8pt; }
        .pay-id span, .pay-id b { display: block; }
        .pay-id b { margin-top: 1px; color: var(--ink); font-size: 7.6pt; }

        .head { display: grid; grid-template-columns: 1fr 1fr; gap: 1px 8px; margin-bottom: 6px; padding: 4px 5px; border: 1px solid #d1d5db; background: var(--soft); }
        .row { display: grid; grid-template-columns: 5.7em 1fr; gap: 4px; margin: 0; }
        .row dt { color: var(--muted); font-weight: 700; }
        .row dd { margin: 0; overflow-wrap: anywhere; }
        .name { grid-column: 1 / -1; }
        .name dd { font-weight: 700; font-size: 9.6pt; }

        .box { border: 1px solid var(--line); display: grid; grid-template-columns: 1fr 1fr; }
        .box > div { padding: 4px 5px; }
        .box > div + div { border-left: 1px solid var(--line); }
        h2 { margin: 0 0 2px; padding-bottom: 2px; border-bottom: 1px solid #d1d5db; color: var(--brand-dark); font-size: 7pt; letter-spacing: .04em; text-transform: uppercase; }
        .line { display: grid; grid-template-columns: 1fr 2em 5em; gap: 3px; }
        .line.two { grid-template-columns: 1fr 5em; }
        .line span:last-child { text-align: right; font-variant-numeric: tabular-nums; }
        .line .qty { text-align: right; }

        .totals { display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid #d1d5db; }
        .totals .line { font-weight: 700; padding: 4px 5px; }
        .net { display: flex; justify-content: flex-end; gap: 12px; padding: 3px 5px; font-weight: 700; font-size: 9.8pt; }
        .net b { border-bottom: 3px double var(--ink); min-width: 5em; text-align: right; }
        .signature { margin-top: 12px; display: flex; gap: 6px; align-items: flex-end; font-weight: 700; }
        .signature span { display: inline-block; width: 9em; border-bottom: 1px solid var(--ink); }

        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .page { margin: 0; page-break-after: always; break-after: page; }
            .page:last-child { page-break-after: auto; break-after: auto; }
            @page { size: 8.5in 11in; margin: 0; }
        }
    </style>
</head>
<body>

<div class="actions">
    <span><b>{{ $company }}</b> · {{ $label }} · {{ $payslips->count() }} payslip(s) on {{ $payslips->chunk(4)->count() }} page(s)</span>
    <span style="display:flex;gap:10px">
        <a href="{{ url()->previous() }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </span>
</div>

@forelse ($payslips->chunk(4) as $four)
    <div class="page">
        @foreach ($four as $p)
            <div class="slip">@include('partials.payslip-slip', ['p' => $p])</div>
        @endforeach
    </div>
@empty
    <p style="text-align:center;font-size:14px">No payslips for {{ $company }} in {{ $label }} yet.</p>
@endforelse

</body>
</html>
