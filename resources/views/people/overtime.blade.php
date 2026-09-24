{{-- Requested from the employee portal only, like a loan: the hours are the
     employee's claim about their own evening, and HR or the supervisor
     decides. --}}
@unless($hr)
<details class="card" @if($errors->any()) open @endif><summary>Request overtime</summary>
<form method="POST" action="{{ $base }}" class="grid divider">@csrf
    <x-people.field name="starts_at" label="Starts at" type="datetime-local" />
    <x-people.field name="ends_at" label="Ends at" type="datetime-local" />
    <label class="people-field wide"><span>Reason / work performed</span><textarea name="reason" required minlength="5" maxlength="3000">{{ old('reason') }}</textarea></label>
    <div><button>Submit request</button></div>
</form></details>
@endunless

<p class="muted">Approved amounts are included in the next generated eligible payslip. Existing payslips are not changed.</p>
@forelse($rows as $row)
<article class="card"><div class="row between"><h2>{{ $row->full_name }}</h2><span class="badge">{{ $row->status }}</span></div>
<p>{{ $row->starts_at }} → {{ $row->ends_at }} · {{ number_format($row->minutes / 60, 2) }} hours</p><p class="prose">{{ $row->reason }}</p>
@if($row->approved_amount !== null)<p><strong>Approved pay: ₱{{ number_format($row->approved_amount, 2) }}</strong> · {{ $row->payroll_id ? 'Included in payroll #'.$row->payroll_id : 'Waiting for payroll' }}</p>@endif
@if($row->manager_decision_note ?? null)<p class="muted">Supervisor/leader note: {{ $row->manager_decision_note }}</p>@endif
@if($row->decision_note)<p class="muted">HR review note: {{ $row->decision_note }}</p>@endif
@php($canFirstReview = !$hr && in_array(auth()->user()->role, ['supervisor', 'leader'], true) && $row->status === 'pending' && $row->employee_id != $employeeId && \Illuminate\Support\Facades\DB::table('employees')->where('employee_id', $row->employee_id)->value('user_id') != auth()->id())
@php($canHrReview = $hr && $row->status === 'pending_hr')
@if($canFirstReview || $canHrReview || $row->status === 'pending')
    @if($canFirstReview || $canHrReview)
    <form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid divider">@csrf
        @if($canHrReview)
            <x-people.field name="approved_amount" label="Approved overtime pay (PHP)" type="number" min="0.01" step="0.01" :required="false" />
        @endif
        <x-people.field name="decision_note" label="Review note (required for rejection)" :required="false" />
        <div class="row"><button name="action" value="approve">{{ $canFirstReview ? 'Send to HR' : 'Approve' }}</button><button name="action" value="reject" class="danger">Reject</button></div>
    </form>
    @endif
    @if($hr || $row->employee_id == $employeeId)<form method="POST" action="{{ $base }}/{{ $row->id }}" class="divider">@csrf<button name="action" value="cancel" class="secondary">Cancel request</button></form>@endif
@endif</article>
@empty<div class="card muted">No overtime requests yet.</div>@endforelse
