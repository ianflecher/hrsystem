{{-- Government loans are recorded by HR from SSS/Pag-IBIG notices. Employees
     only see the deduction schedule here. --}}
<style>
    .loans .loan-stats {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}
    .loans .loan-stat {background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;box-shadow:var(--shadow)}
    .loans .loan-stat span {display:block;color:var(--ink-2);font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
    .loans .loan-stat strong {display:block;font-size:22px;font-weight:700;letter-spacing:-.4px;margin-top:4px}
    .loans .loan-toolbar {display:flex;gap:18px;align-items:flex-end;flex-wrap:wrap}
    .loans .loan-toolbar form {display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin:0}
    .loans .loan-toolbar .search {flex:1 1 320px}
    .loans .loan-toolbar .search .people-field {flex:1}
    .loans .loan-toolbar .billing {padding-left:18px;border-left:1px solid var(--border)}
    .loans .add-loan>summary {display:flex;align-items:center;justify-content:space-between;font-weight:700;font-size:15px;cursor:pointer;list-style:none}
    .loans .add-loan>summary::-webkit-details-marker {display:none}
    .loans .add-loan>summary::after {content:'+ New loan';background:var(--brand);color:#fff;border-radius:var(--radius-sm);padding:8px 14px;font-size:13px;font-weight:600}
    .loans .add-loan[open]>summary::after {content:'Close';background:var(--surface-2);color:var(--ink)}
    .loans fieldset {border:0;padding:0;margin:0 0 6px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
    .loans legend {grid-column:1/-1;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-2);padding:14px 0 10px;margin-bottom:4px;border-bottom:1px solid var(--border);width:100%}
    .loans .form-foot {display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)}
    .loans .link-button {background:none!important;border:0!important;color:var(--accent)!important;padding:0!important;min-height:0!important;font-weight:600}
    .loans .loan-head {display:flex;gap:14px;align-items:center}
    .loans .avatar {width:42px;height:42px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:14px;flex:none;background:var(--accent-soft);color:var(--accent)}
    .loans .avatar.sss {background:var(--ok-soft);color:var(--ok)}
    .loans .loan-head h2 {margin:0;font-size:16px}
    .loans .loan-head .muted {margin:2px 0 0}
    .loans .loan-figures {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;background:var(--border);border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;margin:18px 0 0}
    .loans .loan-figures div {background:var(--surface-2);padding:12px 14px}
    .loans .loan-figures span {display:block;color:var(--ink-2);font-size:12px}
    .loans .loan-figures strong {display:block;font-size:15px;margin-top:2px}
    .loans .loan-figures small {display:block;color:var(--ink-2);font-size:12px;margin-top:2px}
    .loans .term-bar {margin-top:14px}
    .loans .term-bar .row {font-size:12px;color:var(--ink-2);margin-bottom:6px}
    .loans .term-bar .track {height:6px;border-radius:6px;background:var(--surface-2);border:1px solid var(--border);overflow:hidden}
    .loans .term-bar .fill {height:100%;background:var(--accent);border-radius:6px}
    .loans .loan-foot {display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid var(--border)}
    .loans .loan-foot details summary {font-size:13px;font-weight:600}
    .loans [hidden] {display:none!important}
    .loans .other-agency {display:flex;gap:6px;align-items:center}
    .loans .other-agency .link-button {font-size:20px;line-height:1;color:var(--ink-2)!important}
    .loans .chip {display:inline-block;font-size:12px;color:var(--ink-2);background:var(--surface-2);border:1px solid var(--border);border-radius:6px;padding:2px 8px;margin:6px 6px 0 0}
    @media (max-width:900px) {
        .loans .loan-figures {grid-template-columns:repeat(2,minmax(0,1fr))}
        .loans fieldset {grid-template-columns:1fr 1fr}
        .loans .loan-toolbar .billing {padding-left:0;border-left:0}
    }
    @media (max-width:560px) {
        .loans .loan-stats, .loans fieldset {grid-template-columns:1fr}
    }
</style>

@php
    $agencyName = fn ($row) => match ($row->type) { 'sss' => 'SSS', 'pagibig' => 'Pag-IBIG', 'government' => $row->agency ?: 'Government', default => ucfirst((string) $row->type) };
    $active = collect($rows instanceof \Illuminate\Contracts\Pagination\Paginator ? $rows->items() : $rows)->where('status', 'active');
    $statusClass = fn ($s) => match ($s) { 'active' => 'people-status--success', 'pending', 'approved' => 'people-status--warning', 'rejected', 'cancelled' => 'people-status--danger', default => '' };
    $loanTypes = ['(12 Months)', '(24 Months)', '(36 Months)', 'Salary Loan', 'Calamity Loan', 'Housing Loan'];
@endphp
<datalist id="loan-types">@foreach($loanTypes as $t)<option value="{{ $t }}">@endforeach</datalist>

<div class="loans">

@if($hr)
    <div class="loan-stats">
        <div class="loan-stat"><span>Active loans</span><strong>{{ $active->count() }}</strong></div>
        <div class="loan-stat"><span>Pag-IBIG monthly</span><strong>₱{{ number_format($active->where('type', 'pagibig')->sum('installment'), 2) }}</strong></div>
        <div class="loan-stat"><span>SSS monthly</span><strong>₱{{ number_format($active->where('type', 'sss')->sum('installment'), 2) }}</strong></div>
    </div>

    <details class="card add-loan" @if($errors->any() && ! old('edit_loan')) open @endif>
        <summary>Add a government loan</summary>
        <form method="POST" action="{{ $base }}">@csrf
            <fieldset>
                <legend>Borrower</legend>
                <label class="people-field"><span>Employee</span><select name="employee_id" required>
                    <option value="">Select employee</option>
                    @foreach($employees as $person)
                        <option value="{{ $person->employee_id }}" @selected(old('employee_id') == $person->employee_id)>{{ $person->full_name }}</option>
                    @endforeach
                </select></label>
                @php $isOther = old('type', 'pagibig') === 'government'; @endphp
                <label class="people-field agency-field"><span>Agency</span>
                    <select name="type" required @if($isOther) hidden @endif onchange="loanAgency(this)">
                        <option value="pagibig" @selected(old('type', 'pagibig') === 'pagibig')>Pag-IBIG loan</option>
                        <option value="sss" @selected(old('type', 'pagibig') === 'sss')>SSS loan</option>
                        <option value="government" @selected($isOther)>Other - type the agency…</option>
                    </select>
                    <span class="other-agency" @unless($isOther) hidden @endunless><input name="agency" maxlength="60" value="{{ old('agency') }}" placeholder="Type the agency, e.g. GSIS" @if($isOther) required @endif><button type="button" class="link-button" title="Back to the list" onclick="loanAgency(this, true)">×</button></span>
                </label>
                <x-people.field name="monthly" label="Monthly amortization (PHP)" type="number" min="1" max="1000000" step="0.01" />
            </fieldset>
            <fieldset>
                <legend>From the agency's notice</legend>
                <x-people.field name="application_no" label="Application No." :required="false" />
                <label class="people-field"><span>Loan type</span><input name="loan_type" list="loan-types" maxlength="40" value="{{ old('loan_type') }}" placeholder="(12 Months)"></label>
                <x-people.field name="loan_value" label="Loan value (PHP)" type="number" min="0" max="10000000" step="0.01" :required="false" />
                <x-people.field name="check_no" label="DV / Check No." :required="false" />
                <x-people.field name="check_date" label="DV date" type="date" :required="false" />
            </fieldset>
            <fieldset>
                <legend>Loan term</legend>
                <x-people.field name="term_from" label="First amortization" type="date" />
                <x-people.field name="term_to" label="Last amortization" type="date" :required="false" />
                <div class="people-field" @if(old('paid_before')) hidden @endif><span>&nbsp;</span><button type="button" class="link-button" style="justify-content:flex-start;min-height:42px!important" onclick="this.parentElement.nextElementSibling.hidden = false; this.parentElement.remove()">+ Already deducted before this system</button></div>
                <div @unless(old('paid_before')) hidden @endunless>
                    <x-people.field name="paid_before" label="Already deducted before this system (PHP)" type="number" min="0" max="10000000" step="0.01" :required="false" />
                </div>
                <label class="people-field" style="grid-column:1/-1"><span>Reference / notes</span><textarea name="reason" required minlength="5" maxlength="3000" style="min-height:70px">{{ old('reason') }}</textarea></label>
            </fieldset>
            <div class="form-foot">
                <p class="muted" style="margin:0;max-width:70ch">The last amortization fills in from the loan type. Deducted in full on the 1-15 payroll every month of the term, never more than the pay left that cutoff.</p>
                <button>Add government loan</button>
            </div>
        </form>
    </details>

    <div class="card loan-toolbar">
        <form method="GET" class="search">
            <label class="people-field"><span>Find a loan</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Employee name or reference"></label>
            <button class="secondary">Search</button>
            @if(request('search'))<a class="button secondary" href="{{ $base }}">Show all</a>@endif
        </form>
        <form method="GET" action="{{ route('people.loans.billing') }}" class="billing">
            <label class="people-field"><span>Billing statement</span><select name="type">
                <option value="pagibig">Pag-IBIG (STL)</option>
                <option value="sss">SSS</option>
            </select></label>
            <label class="people-field"><span>Month</span><input type="month" name="month" value="{{ now()->format('Y-m') }}" required></label>
            <button>Download Excel</button>
        </form>
    </div>
@endif

@forelse($rows as $row)
@php
    $installments = $extra['installments']->get($row->id, collect());
    $repaid = $installments->whereNotNull('paid_at')->sum('amount');
    $reserved = $installments->whereNull('paid_at')->sum('amount');
    $initials = collect(explode(' ', trim($row->full_name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->pipe(fn ($c) => $c->first().$c->last());
    $from = $row->term_from ? \Carbon\Carbon::parse($row->term_from) : null;
    $to = $row->term_to ? \Carbon\Carbon::parse($row->term_to) : null;
    $months = $from && $to ? $from->copy()->startOfMonth()->diffInMonths($to->copy()->startOfMonth()) + 1 : null;
    $done = $months ? min($months, (int) floor(((float) $repaid + (float) $row->paid_before) / max(0.01, (float) $row->installment) + 0.001)) : null;
@endphp
<article class="card">
    <div class="row between">
        <div class="loan-head">
            <div class="avatar {{ $row->type === 'sss' ? 'sss' : '' }}">{{ mb_strtoupper($initials) }}</div>
            <div>
                <h2>{{ $row->full_name }}</h2>
                <p class="muted">{{ $agencyName($row) }} loan @if($row->loan_type)· {{ $row->loan_type }}@endif @if($row->application_no)· App. {{ $row->application_no }}@endif</p>
            </div>
        </div>
        <span class="row" style="gap:8px">
            <span class="badge {{ $statusClass($row->status) }}"><span class="status-dot"></span>{{ $row->status }}</span>
            @if($hr && $row->status === 'active')<button type="button" class="secondary" style="min-height:34px;padding:6px 12px" onclick="document.getElementById('loan-edit-{{ $row->id }}').toggleAttribute('hidden')">Edit</button>@endif
        </span>
    </div>

    @if((float) $row->amount <= 0)
        <div class="loan-figures">
            <div><span>Monthly amortization</span><strong>₱{{ number_format($row->installment, 2) }}</strong><small>1-15 cutoff</small></div>
            <div><span>Deducted so far</span><strong>₱{{ number_format($repaid + (float) $row->paid_before, 2) }}</strong><small>@if($reserved > 0)₱{{ number_format($reserved, 2) }} not yet paid @elseif((float) $row->paid_before > 0)₱{{ number_format($row->paid_before, 2) }} before this system @else&nbsp;@endif</small></div>
            <div><span>Loan value</span><strong>{{ $row->loan_value ? '₱'.number_format($row->loan_value, 2) : '—' }}</strong><small>@if($row->check_no)Check {{ $row->check_no }}@if($row->check_date) · {{ \Carbon\Carbon::parse($row->check_date)->format('M j, Y') }}@endif @else&nbsp;@endif</small></div>
            <div><span>Term</span><strong>@if($from){{ $from->format('M Y') }} – {{ $to ? $to->format('M Y') : '…' }}@else From {{ \Carbon\Carbon::parse($row->starts_on)->format('M Y') }}@endif</strong><small>{{ $months ? $months.' months' : 'Until stopped' }}</small></div>
        </div>
        @if($months)
            <div class="term-bar">
                <div class="row between"><span>{{ $done }} of {{ $months }} amortizations</span><span>{{ $months - $done }} left</span></div>
                <div class="track"><div class="fill" style="width:{{ round($done / $months * 100) }}%"></div></div>
            </div>
        @endif

        @if($hr && $row->status === 'active')
            <div id="loan-edit-{{ $row->id }}" class="divider" @unless(old('edit_loan') == $row->id) hidden @endunless>
                <form method="POST" action="{{ $base }}/{{ $row->id }}">@csrf
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_loan" value="{{ $row->id }}">
                    <fieldset>
                        <legend>Edit loan</legend>
                        @php $isOther = $row->type === 'government'; @endphp
                        <label class="people-field agency-field"><span>Agency</span>
                            <select name="type" required @if($isOther) hidden @endif onchange="loanAgency(this)">
                                <option value="pagibig" @selected($row->type === 'pagibig')>Pag-IBIG loan</option>
                                <option value="sss" @selected($row->type === 'sss')>SSS loan</option>
                                <option value="government" @selected($isOther)>Other - type the agency…</option>
                            </select>
                            <span class="other-agency" @unless($isOther) hidden @endunless><input name="agency" maxlength="60" value="{{ $row->agency }}" placeholder="Type the agency, e.g. GSIS" @if($isOther) required @endif><button type="button" class="link-button" title="Back to the list" onclick="loanAgency(this, true)">×</button></span>
                        </label>
                        <label class="people-field"><span>Monthly amortization (PHP)</span><input type="number" name="monthly" min="1" max="1000000" step="0.01" value="{{ $row->installment }}" required></label>
                        @foreach(['application_no' => ['Application No.', 'text'], 'loan_type' => ['Loan type', 'text'], 'loan_value' => ['Loan value (PHP)', 'number'], 'check_no' => ['DV / Check No.', 'text'], 'check_date' => ['DV date', 'date'], 'term_from' => ['First amortization', 'date'], 'term_to' => ['Last amortization', 'date']] as $field => [$text, $kind])
                            <label class="people-field"><span>{{ $text }}</span><input type="{{ $kind }}" name="{{ $field }}" @if($field === 'loan_type') list="loan-types" @endif @if($kind === 'number') min="0" step="0.01" @endif value="{{ $kind === 'date' && $row->$field ? substr((string) $row->$field, 0, 10) : $row->$field }}"></label>
                        @endforeach
                        <div class="people-field" @if((float) $row->paid_before > 0) hidden @endif><span>&nbsp;</span><button type="button" class="link-button" style="justify-content:flex-start;min-height:42px!important" onclick="this.parentElement.nextElementSibling.hidden = false; this.parentElement.remove()">+ Already deducted before this system</button></div>
                        <label class="people-field" @unless((float) $row->paid_before > 0) hidden @endunless><span>Already deducted before this system (PHP)</span><input type="number" name="paid_before" min="0" max="10000000" step="0.01" value="{{ $row->paid_before }}"></label>
                        <label class="people-field" style="grid-column:1/-1"><span>Reference / notes</span><textarea name="reason" required minlength="5" maxlength="3000" style="min-height:70px">{{ $row->reason }}</textarea></label>
                    </fieldset>
                    <div class="form-foot"><button type="button" class="secondary" onclick="document.getElementById('loan-edit-{{ $row->id }}').hidden = true">Cancel</button><button>Save changes</button></div>
                </form>
            </div>
        @endif
    @else
        <div class="loan-figures" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div><span>Recorded balance</span><strong>₱{{ number_format($row->amount, 2) }}</strong><small>Per cutoff ₱{{ number_format($row->installment, 2) }}</small></div>
            <div><span>Outstanding</span><strong>₱{{ number_format($row->amount - $repaid, 2) }}</strong><small>₱{{ number_format($reserved, 2) }} reserved in unpaid payroll</small></div>
        </div>
    @endif

    <div class="loan-foot">
        <div style="flex:1;min-width:240px">
            <p class="muted" style="margin:0">{{ $row->reason }}@if($row->decision_note) · {{ $row->decision_note }}@endif</p>
            @if($installments->isNotEmpty())
                <details style="margin-top:8px"><summary>Repayment history ({{ $installments->count() }})</summary>
                    <div class="scroll" style="margin-top:8px"><table><thead><tr><th>Cutoff</th><th>Amount</th><th>Status</th></tr></thead><tbody>@foreach($installments as $item)<tr><td>{{ \Carbon\Carbon::parse($item->period_start)->format('M j, Y') }}</td><td>₱{{ number_format($item->amount, 2) }}</td><td>{{ $item->paid_at ? 'Repaid' : 'Reserved · '.$item->status }}</td></tr>@endforeach</tbody></table></div>
                </details>
            @endif
        </div>
        <div class="row" style="gap:8px">
            @if($hr && $row->status === 'pending')
                <form method="POST" action="{{ $base }}/{{ $row->id }}" class="row" style="gap:8px">@csrf<input name="decision_note" placeholder="Review note (needed to reject)" style="min-width:220px"><button name="action" value="approve">Approve</button><button name="action" value="reject" class="danger">Reject</button><button name="action" value="cancel" class="secondary">Cancel</button></form>
            @endif
            @if($hr && $row->status === 'approved')<form method="POST" action="{{ $base }}/{{ $row->id }}" onsubmit="return confirm('Confirm that the loan amount has been given to the employee?')">@csrf<button name="action" value="disburse">Confirm funds disbursed</button></form>@endif
            @if($hr && $row->status === 'active')<form method="POST" action="{{ $base }}/{{ $row->id }}" onsubmit="return confirm('Stop deducting this loan from payroll? Use this when the agency says it is fully paid.')">@csrf<button name="action" value="stop" class="danger" style="min-height:34px;padding:6px 12px">Stop deductions</button></form>@endif
        </div>
    </div>
</article>
@empty
    <div class="card muted" style="text-align:center;padding:40px">No government loans yet.</div>
@endforelse

</div>

{{-- The last amortization follows from the loan type and the first one:
     "(12 Months)" from Dec 15, 2026 ends Nov 15, 2027. --}}
<script>
// "Other" turns the agency list into a box to type the agency in; × goes back.
function loanAgency(el, back) {
    const field = el.closest('.agency-field'), select = field.querySelector('select'), other = field.querySelector('.other-agency'), input = other.querySelector('input');
    if (back) select.value = 'pagibig';
    const typing = select.value === 'government';
    select.hidden = typing; other.hidden = ! typing; input.required = typing;
    if (typing) input.focus(); else input.value = '';
}
document.addEventListener('input', function (e) {
    if (! ['loan_type', 'term_from'].includes(e.target.name)) return;
    const form = e.target.form, months = parseInt((form.loan_type?.value.match(/(\d+)\s*month/i) || [])[1], 10);
    const from = form.term_from?.value;
    if (! months || ! from) return;
    const [y, m, d] = from.split('-').map(Number);
    const end = new Date(y, m - 1 + months - 1, 1);
    const last = new Date(end.getFullYear(), end.getMonth() + 1, 0).getDate();
    form.term_to.value = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(Math.min(d, last)).padStart(2, '0');
});
</script>
