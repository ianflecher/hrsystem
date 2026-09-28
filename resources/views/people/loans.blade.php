{{-- Government loans are recorded by HR from SSS/Pag-IBIG notices. Employees
     only see the deduction schedule here. --}}
@if($hr)
<details class="card" @if($errors->any()) open @endif><summary>Add a government loan</summary>
<form method="POST" action="{{ $base }}" class="grid divider">@csrf
    <label class="people-field"><span>Employee</span><select name="employee_id" required>
        <option value="">Select employee</option>
        @foreach($employees as $person)
            <option value="{{ $person->employee_id }}" @selected(old('employee_id') == $person->employee_id)>{{ $person->full_name }}</option>
        @endforeach
    </select></label>
    <label class="people-field"><span>Agency</span><select name="type" required>
        <option value="sss" @selected(old('type') === 'sss')>SSS loan</option>
        <option value="pagibig" @selected(old('type') === 'pagibig')>Pag-IBIG loan</option>
        <option value="government" @selected(old('type') === 'government')>Other government loan</option>
    </select></label>
    <x-people.field name="amount" label="Loan balance / amount (PHP)" type="number" min="1" max="1000000" step="0.01" />
    <x-people.field name="installment" label="Deduction per payroll cutoff (PHP)" type="number" min="1" step="0.01" />
    <x-people.field name="starts_on" label="First eligible payroll cutoff start" type="date" />
    <label class="people-field wide"><span>Reference / notes</span><textarea name="reason" required minlength="5" maxlength="3000">{{ old('reason') }}</textarea></label>
    <p class="muted wide">Repayments are deducted automatically from payroll once the cutoff reaches the start date. Deductions are capped at remaining pay and balance.</p>
    <div><button>Add government loan</button></div>
</form></details>
@endif

@forelse($rows as $row)
@php
    $installments = $extra['installments']->get($row->id, collect());
    $repaid = $installments->whereNotNull('paid_at')->sum('amount');
    $reserved = $installments->whereNull('paid_at')->sum('amount');
    $loanType = match ($row->type) {
        'sss' => 'SSS loan',
        'pagibig' => 'Pag-IBIG loan',
        'government' => 'Government loan',
        default => str_replace('_', ' ', ucfirst($row->type)),
    };
@endphp
<article class="card"><div class="row between"><h2>{{ $row->full_name }} · {{ $loanType }}</h2><span class="badge">{{ $row->status }}</span></div>
<div class="grid"><p>Recorded balance <strong>₱{{ number_format($row->amount,2) }}</strong><br>Per cutoff ₱{{ number_format($row->installment,2) }}</p><p>Outstanding <strong>₱{{ number_format($row->amount - $repaid,2) }}</strong><br><span class="muted">₱{{ number_format($reserved,2) }} reserved in unpaid payroll</span></p></div>
<p class="prose">{{ $row->reason }}</p><p class="muted">First eligible cutoff {{ $row->starts_on }} @if($row->decision_note) · {{ $row->decision_note }} @endif</p>
@if($row->status === 'pending')
    @if($hr)<form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid divider">@csrf<x-people.field name="decision_note" label="Review note (required for rejection)" :required="false" /><div class="row"><button name="action" value="approve">Approve</button><button name="action" value="reject" class="danger">Reject</button></div></form>@endif
    @if($hr)<form method="POST" action="{{ $base }}/{{ $row->id }}" class="divider">@csrf<button name="action" value="cancel" class="secondary">Cancel request</button></form>@endif
@endif
@if($hr && $row->status === 'approved')<form method="POST" action="{{ $base }}/{{ $row->id }}" class="divider" onsubmit="return confirm('Confirm that the loan amount has been given to the employee?')">@csrf<button name="action" value="disburse">Confirm funds disbursed</button></form>@endif
@if($installments->isNotEmpty())<details class="divider"><summary>Repayment history</summary><div class="scroll"><table><thead><tr><th>Cutoff</th><th>Amount</th><th>Status</th></tr></thead><tbody>@foreach($installments as $item)<tr><td>{{ $item->period_start }}</td><td>₱{{ number_format($item->amount,2) }}</td><td>{{ $item->paid_at ? 'Repaid' : 'Reserved · '.$item->status }}</td></tr>@endforeach</tbody></table></div></details>@endif
</article>
@empty<div class="card muted">No government loan requests yet.</div>@endforelse
