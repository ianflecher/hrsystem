{{-- Who pays which loan in a month, and whether payroll has taken it yet.
     A loan is due in a month its term covers (the same rule as the billing
     statement); what payroll took comes from the installments of that
     month's cutoffs. --}}
@php
    $pick = preg_match('/^\d{4}-\d{2}$/', (string) request('loan_month')) ? request('loan_month') : now()->format('Y-m');
    $monthStart = \Carbon\Carbon::parse($pick.'-01');
    $monthEnd = $monthStart->copy()->endOfMonth();

    $dueIn = function (\Carbon\Carbon $start, \Carbon\Carbon $end) {
        return \Illuminate\Support\Facades\DB::table('employee_loans as l')
            ->join('employees as e', 'e.employee_id', '=', 'l.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('l.amount', '<=', 0)
            ->whereIn('l.status', ['active', 'stopped', 'repaid'])
            ->where('l.starts_on', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('l.term_to')->orWhere('l.term_to', '>=', $start->toDateString()))
            ->where(fn ($q) => $q->where('l.status', 'active')->orWhere('l.updated_at', '>=', $start->toDateString()))
            ->select('l.*', 'u.full_name', 'e.company')
            ->get();
    };
    $takenIn = function (\Carbon\Carbon $start, \Carbon\Carbon $end) {
        return \Illuminate\Support\Facades\DB::table('loan_installments as i')
            ->join('hr_payroll as p', 'p.payroll_id', '=', 'i.payroll_id')
            ->whereBetween('p.period_start', [$start->toDateString(), $end->toDateString()])
            ->select('i.loan_id', 'i.amount', 'i.paid_at')
            ->get()->groupBy('loan_id');
    };

    $due = $dueIn($monthStart, $monthEnd);
    $taken = $takenIn($monthStart, $monthEnd);
    $lines = $due->map(function ($l) use ($taken) {
        $items = $taken->get($l->id, collect());
        $paid = (float) $items->whereNotNull('paid_at')->sum('amount');
        $pending = (float) $items->whereNull('paid_at')->sum('amount');
        $amount = round((float) $l->installment, 2);
        $state = $paid >= $amount - 0.005 ? 'paid' : ($paid + $pending >= $amount - 0.005 ? 'payroll' : ($paid + $pending > 0 ? 'short' : 'due'));
        $words = explode(' ', trim(preg_replace('/\s+/', ' ', $l->full_name)));
        $last = count($words) > 1 ? array_pop($words) : '';

        return (object) ['name' => $last !== '' ? $last.', '.implode(' ', $words) : $l->full_name, 'company' => $l->company ?: 'GKLASAM OPC',
            'agency' => match ($l->type) { 'pagibig' => 'Pag-IBIG', 'sss' => 'SSS', default => $l->agency ?: 'Other' },
            'type' => $l->loan_type, 'amount' => $amount, 'paid' => $paid, 'pending' => $pending, 'state' => $state];
    })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

    $byAgency = $lines->groupBy('agency')->sortKeys();
    $totalDue = $lines->sum('amount');
    $totalPaid = $lines->sum('paid');
    $totalPending = $lines->sum('pending');
    $unpaid = max(0, $totalDue - $totalPaid - $totalPending);

    // The six months up to the chosen one: due against taken.
    $trend = collect(range(5, 0))->map(function ($back) use ($monthStart, $dueIn, $takenIn) {
        $s = $monthStart->copy()->subMonthsNoOverflow($back);
        $e = $s->copy()->endOfMonth();
        $d = (float) $dueIn($s, $e)->sum('installment');
        $t = (float) $takenIn($s, $e)->flatten()->sum('amount');

        return (object) ['label' => $s->format('M'), 'month' => $s->format('Y-m'), 'due' => $d, 'taken' => $t];
    });
    $peak = max(1, $trend->max('due'), $trend->max('taken'));
    $stateText = ['paid' => 'Paid', 'payroll' => 'In payroll', 'short' => 'Partly taken', 'due' => 'Not yet taken'];
    $stateClass = ['paid' => 'people-status--success', 'payroll' => 'people-status--info', 'short' => 'people-status--warning', 'due' => 'people-status--danger'];
@endphp

<style>
    .loan-month .head {display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:16px}
    .loan-month .head h2 {margin:0}
    .loan-month .head form {display:flex;gap:8px;align-items:center;margin:0}
    .loan-month .tiles {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .loan-month .tile {border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;background:var(--surface-2)}
    .loan-month .tile span {display:block;font-size:12px;color:var(--ink-2)}
    .loan-month .tile strong {display:block;font-size:19px;margin-top:2px}
    .loan-month .tile small {font-size:12px;color:var(--ink-2)}
    .loan-month .meter {height:8px;border-radius:8px;background:var(--surface-2);border:1px solid var(--border);overflow:hidden;display:flex;margin:14px 0 4px}
    .loan-month .meter i {display:block;height:100%}
    .loan-month .legend {display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--ink-2)}
    .loan-month .legend b {display:inline-block;width:8px;height:8px;border-radius:2px;margin-right:5px;vertical-align:middle}
    .loan-month .trend {display:grid;grid-template-columns:repeat(6,1fr);gap:10px;align-items:end;height:110px;margin-top:18px;padding-top:6px;border-top:1px solid var(--border)}
    .loan-month .trend a {display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end;text-decoration:none;color:var(--ink-2);font-size:11px;border-radius:6px;padding:4px 0}
    .loan-month .trend a.on {background:var(--surface-2);color:var(--ink);font-weight:700}
    .loan-month .bars {display:flex;gap:3px;align-items:flex-end;height:70px}
    .loan-month .bars i {width:12px;border-radius:3px 3px 0 0;display:block}
    .loan-month .group {margin-top:20px}
    .loan-month .group h3 {display:flex;justify-content:space-between;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-2);margin:0 0 8px}
    .loan-month td.num, .loan-month th.num {text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
    .loan-month tfoot td {font-weight:700;border-top:2px solid var(--border)}
    .loans .loan-month>summary::after {content:'Show'}
    .loans .loan-month[open]>summary::after {content:'Hide'}
    .loan-month summary {justify-content:flex-start!important}
    .loan-month summary::after {margin-left:auto}
    @media (max-width:760px) { .loan-month .tiles {grid-template-columns:1fr 1fr} }
</style>

<details class="card loan-month add-loan" @if(request('loan_month')) open @endif>
    <summary data-open="Monthly loan payments">Monthly loan payments <span class="muted" style="font-weight:400;margin-left:8px">who pays this month and how much</span></summary>
    <div class="divider">
    <div class="head">
        <div>
            <h2>Monthly loan payments · {{ $monthStart->format('F Y') }}</h2>
            <p class="muted" style="margin:0">Who pays this month, how much, and whether payroll has taken it. Deducted on the 1-15 cutoff.</p>
        </div>
        <form method="GET">
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <a class="button secondary" href="?loan_month={{ $monthStart->copy()->subMonthNoOverflow()->format('Y-m') }}" title="Previous month">‹</a>
            <input type="month" name="loan_month" value="{{ $pick }}" onchange="this.form.submit()" style="width:auto">
            <a class="button secondary" href="?loan_month={{ $monthStart->copy()->addMonthNoOverflow()->format('Y-m') }}" title="Next month">›</a>
        </form>
    </div>

    <div class="tiles">
        <div class="tile"><span>Due this month</span><strong>₱{{ number_format($totalDue, 2) }}</strong><small>{{ $lines->count() }} {{ \Illuminate\Support\Str::plural('loan', $lines->count()) }}</small></div>
        <div class="tile"><span>Paid</span><strong style="color:var(--ok)">₱{{ number_format($totalPaid, 2) }}</strong><small>{{ $lines->where('state', 'paid')->count() }} paid in full</small></div>
        <div class="tile"><span>In payroll, not yet paid</span><strong style="color:var(--accent)">₱{{ number_format($totalPending, 2) }}</strong><small>Payslip not released</small></div>
        <div class="tile"><span>Not yet taken</span><strong style="color:var(--bad)">₱{{ number_format($unpaid, 2) }}</strong><small>{{ $lines->whereIn('state', ['due', 'short'])->count() }} still to deduct</small></div>
    </div>

    @if($totalDue > 0)
        <div class="meter">
            <i style="width:{{ min(100, $totalPaid / $totalDue * 100) }}%;background:var(--ok)"></i>
            <i style="width:{{ min(100, $totalPending / $totalDue * 100) }}%;background:var(--accent)"></i>
        </div>
        <div class="legend"><span><b style="background:var(--ok)"></b>Paid {{ round($totalPaid / $totalDue * 100) }}%</span><span><b style="background:var(--accent)"></b>In payroll {{ round($totalPending / $totalDue * 100) }}%</span><span><b style="background:var(--border-strong)"></b>Not yet taken</span></div>
    @endif

    <div class="trend" aria-label="Last six months">
        @foreach($trend as $t)
            <a href="?loan_month={{ $t->month }}" class="{{ $t->month === $pick ? 'on' : '' }}" title="{{ $t->label }}: due ₱{{ number_format($t->due, 2) }}, taken ₱{{ number_format($t->taken, 2) }}">
                <span class="bars"><i style="height:{{ max(2, $t->due / $peak * 70) }}px;background:var(--border-strong)"></i><i style="height:{{ max(2, $t->taken / $peak * 70) }}px;background:var(--ok)"></i></span>
                {{ $t->label }}
            </a>
        @endforeach
    </div>
    <div class="legend" style="justify-content:center;margin-top:6px"><span><b style="background:var(--border-strong)"></b>Due</span><span><b style="background:var(--ok)"></b>Taken by payroll</span></div>

    @forelse($byAgency as $agency => $group)
        <div class="group">
            <h3><span>{{ $agency }} · {{ $group->count() }}</span><span>₱{{ number_format($group->sum('amount'), 2) }}</span></h3>
            <div class="scroll"><table>
                <thead><tr><th>Employee</th><th>Company</th><th>Loan type</th><th class="num">Amortization</th><th class="num">Taken</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($group as $line)
                        <tr>
                            <td>{{ $line->name }}</td>
                            <td class="muted">{{ $line->company }}</td>
                            <td class="muted">{{ $line->type ?: '—' }}</td>
                            <td class="num">₱{{ number_format($line->amount, 2) }}</td>
                            <td class="num">{{ $line->paid + $line->pending > 0 ? '₱'.number_format($line->paid + $line->pending, 2) : '—' }}</td>
                            <td><span class="badge {{ $stateClass[$line->state] }}"><span class="status-dot"></span>{{ $stateText[$line->state] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td colspan="3">Total {{ $agency }}</td><td class="num">₱{{ number_format($group->sum('amount'), 2) }}</td><td class="num">₱{{ number_format($group->sum('paid') + $group->sum('pending'), 2) }}</td><td></td></tr></tfoot>
            </table></div>
        </div>
    @empty
        <p class="muted" style="text-align:center;padding:20px 0 0">No loan amortizations due in {{ $monthStart->format('F Y') }}.</p>
    @endforelse
    </div>
</details>
