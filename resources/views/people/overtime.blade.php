@unless($hr)
<div class="card">
    <strong>Overtime is filed by your supervisor or team leader.</strong>
    <p class="muted" style="margin:.35rem 0 0">You can view the status here after they file it for you.</p>
</div>
@endunless

<p class="muted">Approved amounts are included in the next generated eligible payslip. Existing payslips are not changed.</p>
@forelse($rows as $row)
<article class="card"><div class="row between"><h2>{{ $row->full_name }}</h2><span class="badge">{{ ['pending' => 'Waiting for supervisor', 'pending_hr' => "Waiting for Ma'am An"][$row->status] ?? $row->status }}</span></div>
<p>{{ $row->starts_at }} → {{ $row->ends_at }} · {{ intdiv((int) $row->minutes, 60) }} hour(s)</p><p class="prose">{{ $row->reason }}</p>
@if($row->approved_amount !== null && (! $hr || \App\Support\PeopleAccess::canSeePay()))<p><strong>Approved pay: ₱{{ number_format($row->approved_amount, 2) }}</strong> · {{ $row->payroll_id ? 'Included in payroll #'.$row->payroll_id : 'Waiting for payroll' }}</p>@endif
@if($row->manager_decision_note ?? null)<p class="muted">Supervisor/leader note: {{ $row->manager_decision_note }}</p>@endif
@if($row->decision_note)<p class="muted">Approval note: {{ $row->decision_note }}</p>@endif
@php($otStage = \App\Services\OvertimeApproval::stage($row))
@php($canFirstReview = $otStage === 'supervisor')
@php($canHrReview = $otStage === 'final')
@if($canFirstReview || $canHrReview || $row->status === 'pending')
    @if($canFirstReview || $canHrReview)
    <form method="POST" action="{{ $base }}/{{ $row->id }}" class="grid divider">@csrf
        @if($canHrReview)
            <x-people.field name="approved_amount" label="Approved overtime pay (PHP)" type="number" min="0.01" step="0.01" :required="false" />
        @endif
        <x-people.field name="decision_note" label="Review note (required for rejection)" :required="false" />
        <div class="row"><button name="action" value="approve">{{ $canFirstReview ? "Send to Ma'am An" : 'Approve' }}</button><button name="action" value="reject" class="danger">Reject</button></div>
    </form>
    @endif
    @if($hr || $row->employee_id == $employeeId)<form method="POST" action="{{ $base }}/{{ $row->id }}" class="divider">@csrf<button name="action" value="cancel" class="secondary">Cancel request</button></form>@endif
@endif</article>
@empty<div class="card muted">No overtime requests yet.</div>@endforelse
