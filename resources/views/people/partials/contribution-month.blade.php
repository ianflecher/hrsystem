{{-- The month's SSS, PhilHealth and Pag-IBIG contributions, from the
     payslips of both cutoffs: what each person pays and what the company adds. --}}
@php
    $cpick = preg_match('/^\d{4}-\d{2}$/', (string) request('contrib_month')) ? request('contrib_month') : now()->format('Y-m');
    $cStart = \Carbon\Carbon::parse($cpick.'-01');
    $cEnd = $cStart->copy()->endOfMonth();

    $contrib = \App\Support\MonthlyContributions::for($cStart);

    $agencies = [
        'SSS' => ['ee' => $contrib->sum('sss'), 'er' => $contrib->sum('employer_sss') + $contrib->sum('employer_ec'), 'color' => 'var(--ok)'],
        'PhilHealth' => ['ee' => $contrib->sum('philhealth'), 'er' => $contrib->sum('employer_philhealth'), 'color' => 'var(--accent)'],
        'Pag-IBIG' => ['ee' => $contrib->sum('pagibig'), 'er' => $contrib->sum('employer_pagibig'), 'color' => 'var(--warn)'],
    ];
    $grand = $contrib->sum('total');
    $byCompany = $contrib->groupBy('company')->sortKeys();
    $peso = fn ($v) => $v > 0 ? '₱'.number_format($v, 2) : '—';
@endphp

<style>
    .loans .contrib-month>summary::after {content:'Show'}
    .loans .contrib-month[open]>summary::after {content:'Hide'}
    .contrib-month summary {justify-content:flex-start!important}
    .contrib-month summary::after {margin-left:auto}
    .contrib-month .head {display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px}
    .contrib-month .head form {display:flex;gap:8px;align-items:center;margin:0}
    .contrib-month .tiles {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .contrib-month .tile {border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;background:var(--surface-2);border-top:3px solid var(--c, var(--ink))}
    .contrib-month .tile span {display:block;font-size:12px;color:var(--ink-2)}
    .contrib-month .tile strong {display:block;font-size:19px;margin-top:2px}
    .contrib-month .tile small {display:block;font-size:12px;color:var(--ink-2);margin-top:2px}
    .contrib-month .group {margin-top:20px}
    .contrib-month .group h3 {display:flex;justify-content:space-between;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-2);margin:0 0 8px}
    .contrib-month td.num, .contrib-month th.num {text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
    .contrib-month th.band {text-align:center;border-bottom:1px solid var(--border)}
    .contrib-month tfoot td {font-weight:700;border-top:2px solid var(--border)}
    @media (max-width:760px) { .contrib-month .tiles {grid-template-columns:1fr 1fr} }
</style>

<details class="card add-loan contrib-month" @if(request('contrib_month')) open @endif>
    <summary>Monthly contributions <span class="muted" style="font-weight:400;margin-left:8px">SSS, PhilHealth and Pag-IBIG from the payslips</span></summary>
    <div class="divider">
        <div class="head">
            <p class="muted" style="margin:0">Both cutoffs of {{ $cStart->format('F Y') }}: the employee share deducted from pay and the employer share the company adds.</p>
            <form method="GET">
                <a class="button secondary" href="?contrib_month={{ $cStart->copy()->subMonthNoOverflow()->format('Y-m') }}" title="Previous month">‹</a>
                <input type="month" name="contrib_month" value="{{ $cpick }}" onchange="this.form.submit()" style="width:auto">
                <a class="button secondary" href="?contrib_month={{ $cStart->copy()->addMonthNoOverflow()->format('Y-m') }}" title="Next month">›</a>
                <a class="button" href="{{ route('people.contributions.download', ['month' => $cpick]) }}">Download Excel</a>
            </form>
        </div>

        <div class="tiles">
            <div class="tile"><span>Total remittance</span><strong>₱{{ number_format($grand, 2) }}</strong><small>{{ $contrib->count() }} {{ \Illuminate\Support\Str::plural('employee', $contrib->count()) }}</small></div>
            @foreach($agencies as $name => $a)
                <div class="tile" style="--c:{{ $a['color'] }}"><span>{{ $name }}</span><strong>₱{{ number_format($a['ee'] + $a['er'], 2) }}</strong><small>Employee ₱{{ number_format($a['ee'], 2) }} · Employer ₱{{ number_format($a['er'], 2) }}</small></div>
            @endforeach
        </div>

        @forelse($byCompany as $company => $group)
            <div class="group">
                <h3><span>{{ $company }} · {{ $group->count() }}</span><span>₱{{ number_format($group->sum('total'), 2) }}</span></h3>
                <div class="scroll"><table>
                    <thead>
                        <tr><th rowspan="2">Employee</th><th colspan="3" class="band">SSS</th><th colspan="2" class="band">PhilHealth</th><th colspan="2" class="band">Pag-IBIG</th><th rowspan="2" class="num">Total</th><th rowspan="2">Payslips</th></tr>
                        <tr><th class="num">EE</th><th class="num">ER</th><th class="num">EC</th><th class="num">EE</th><th class="num">ER</th><th class="num">EE</th><th class="num">ER</th></tr>
                    </thead>
                    <tbody>
                        @foreach($group as $r)
                            <tr>
                                <td>{{ $r->name }}</td>
                                <td class="num">{{ $peso($r->sss) }}</td><td class="num">{{ $peso($r->employer_sss) }}</td><td class="num">{{ $peso($r->employer_ec) }}</td>
                                <td class="num">{{ $peso($r->philhealth) }}</td><td class="num">{{ $peso($r->employer_philhealth) }}</td>
                                <td class="num">{{ $peso($r->pagibig) }}</td><td class="num">{{ $peso($r->employer_pagibig) }}</td>
                                <td class="num"><strong>₱{{ number_format($r->total, 2) }}</strong></td>
                                <td>@if($r->paid_slips == $r->slips)<span class="badge people-status--success"><span class="status-dot"></span>Paid</span>@else<span class="badge people-status--info"><span class="status-dot"></span>{{ $r->paid_slips }}/{{ $r->slips }} paid</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr>
                        <td>Total</td>
                        @foreach(['sss', 'employer_sss', 'employer_ec', 'philhealth', 'employer_philhealth', 'pagibig', 'employer_pagibig', 'total'] as $k)
                            <td class="num">₱{{ number_format($group->sum($k), 2) }}</td>
                        @endforeach
                        <td></td>
                    </tr></tfoot>
                </table></div>
            </div>
        @empty
            <p class="muted" style="text-align:center;padding:20px 0 0">No payslips with contributions in {{ $cStart->format('F Y') }} yet.</p>
        @endforelse
    </div>
</details>
