@php
    $isThirteenth = $p->kind === \App\Services\ThirteenthMonth::KIND;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip · {{ $p->full_name }} · {{ $p->period_start }}</title>
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #cbd5e1; --soft: #f8fafc; --brand: #E31B23; --brand-dark: #991b1b; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 20px; background: #eef2f7; color: var(--ink);
               font: 13px/1.5 Calibri, 'Segoe UI', -apple-system, sans-serif; }
        .sheet { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #dbe4ef; border-radius: 14px; padding: 32px 36px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .08); }
        .actions { max-width: 820px; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; }
        .actions button, .actions a { font: inherit; font-weight: 600; padding: 9px 16px; border-radius: 8px;
            border: 1px solid #dce1e9; background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
        .actions button { background: var(--brand); border-color: var(--brand); color: #fff; }

        .pay-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding-bottom: 18px; margin-bottom: 18px;
            border-bottom: 3px solid var(--brand); }
        .eyebrow { margin: 0 0 4px; color: var(--brand-dark); font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 30px; line-height: 1.05; letter-spacing: 0; }
        .period { margin: 6px 0 0; color: var(--muted); font-weight: 700; }
        .pay-id { min-width: 160px; text-align: right; color: var(--muted); font-size: 12px; }
        .pay-id span, .pay-id b { display: block; }
        .pay-id b { margin-top: 4px; color: var(--ink); font-size: 16px; }

        .head { display: grid; grid-template-columns: 1.3fr 1fr; gap: 10px 18px; margin-bottom: 18px; padding: 14px;
            background: var(--soft); border: 1px solid #e2e8f0; border-radius: 10px; }
        .row { display: grid; grid-template-columns: 8.25rem 1fr; gap: 8px; margin: 0; }
        .row dt { color: var(--muted); font-weight: 700; }
        .row dd { margin: 0; font-weight: 700; }
        .name dd { font-size: 15px; }

        .box { border: 1px solid var(--line); border-radius: 10px; overflow: hidden; display: grid; grid-template-columns: 1fr 1fr; }
        .box > div { padding: 12px 14px; }
        .box > div + div { border-left: 1px solid var(--line); background: #fbfdff; }
        h2 { margin: 0 0 9px; padding-bottom: 7px; border-bottom: 1px solid #e2e8f0; color: var(--brand-dark);
            font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        .line { display: grid; grid-template-columns: 1fr 3rem 6.5rem; gap: 8px; padding: 3px 0; }
        .line.two { grid-template-columns: 1fr 6.5rem; }
        .line span:last-child { text-align: right; font-variant-numeric: tabular-nums; }
        .line .qty { text-align: right; font-variant-numeric: tabular-nums; }

        .totals { display: grid; grid-template-columns: 1fr 1fr; margin-top: 10px; border-top: 1px solid #e2e8f0; }
        .totals .line { font-weight: 800; padding: 12px 14px; }
        .net { margin-top: 10px; margin-left: auto; width: min(330px, 100%); display: flex; align-items: center; justify-content: space-between;
            gap: 24px; padding: 14px 16px; border-radius: 10px; background: #0f172a; color: #fff; font-weight: 800; font-size: 15px; }
        .net b { min-width: 7rem; text-align: right; font-variant-numeric: tabular-nums; font-size: 18px; }

        .signature { margin-top: 34px; display: flex; gap: 12px; align-items: flex-end; font-weight: 700; }
        .signature span { display: inline-block; width: 16rem; border-bottom: 1px solid var(--ink); }
        footer { margin-top: 24px; font-size: 11px; color: var(--muted); display: flex; justify-content: space-between; gap: 16px; }

        @media (max-width: 640px) {
            body { padding: 16px; }
            .sheet { padding: 20px 16px; }
            .pay-header { display: block; }
            .pay-id { margin-top: 12px; text-align: left; }
            .head, .box, .totals { grid-template-columns: 1fr; }
            .box > div + div { border-left: 0; border-top: 1px solid var(--line); }
        }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .sheet { max-width: none; border: 0; border-radius: 0; padding: 0; box-shadow: none; }
            .net { background: #fff; color: var(--ink); border: 1px solid var(--ink); }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body>

<div class="actions">
    <a href="{{ url()->previous() }}">Back</a>
    <button type="button" onclick="window.print()">Print or save as PDF</button>
</div>

<div class="sheet">
    @include('partials.payslip-slip', ['p' => $p])

    <footer>
        <span>Payslip #{{ $p->payroll_id }}</span>
        <span>Printed {{ now()->format('j M Y, g:i A') }}</span>
    </footer>
</div>

</body>
</html>
