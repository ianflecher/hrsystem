{{-- Requested from the employee portal only, like a loan: the hours are the
     employee's claim about their own evening, and HR or the supervisor
     decides. --}}
@unless($hr)
<details class="card" @if($errors->any()) open @endif><summary>Request overtime</summary>
@if(($extra['scannerSuggestions'] ?? collect())->isNotEmpty())
    <div class="divider">
        <div class="row between" style="align-items:center">
            <div>
                <strong>Possible overtime from scanner</strong>
                <p class="muted" style="margin:.25rem 0 0">These are not filed yet. Click Use details, then submit if the overtime is correct.</p>
            </div>
            <span class="badge">{{ $extra['scannerSuggestions']->count() }} found</span>
        </div>
        <div class="grid" style="margin-top:12px">
            @foreach($extra['scannerSuggestions'] as $suggestion)
                <div class="card" style="box-shadow:none;margin:0">
                    <div class="row between">
                        <strong>{{ \Carbon\Carbon::parse($suggestion->date)->format('M j') }} · {{ $suggestion->hours }} hour(s)</strong>
                        <button type="button" class="secondary" style="padding:4px 12px"
                            data-ot-start="{{ $suggestion->starts_at->format('Y-m-d\TH:i') }}"
                            data-ot-end="{{ $suggestion->ends_at->format('Y-m-d\TH:i') }}"
                            data-ot-reason="{{ $suggestion->reason }}"
                            onclick="fillScannerOvertime(this)">Use details</button>
                    </div>
                    <p class="muted" style="margin:.35rem 0 0">
                        Scanner {{ $suggestion->time_in->format('g:i A') }} → {{ $suggestion->time_out->format('g:i A') }}
                        · suggested {{ $suggestion->starts_at->format('g:i A') }} → {{ $suggestion->ends_at->format('g:i A') }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
@endif
<form method="POST" action="{{ $base }}" class="grid divider" id="overtime-request-form">@csrf
    <x-people.field name="starts_at" label="Starts at" type="datetime-local" step="3600" />
    <x-people.field name="ends_at" label="Ends at" type="datetime-local" step="3600" />
    <label class="people-field wide"><span>Reason / work performed</span><textarea name="reason" required minlength="5" maxlength="3000">{{ old('reason') }}</textarea></label>
    <div><button>Submit request</button></div>
</form></details>
<script>
function fillScannerOvertime(button) {
    const form = document.getElementById('overtime-request-form');
    if (!form) return;
    form.querySelector('[name="starts_at"]').value = button.dataset.otStart || '';
    form.querySelector('[name="ends_at"]').value = button.dataset.otEnd || '';
    form.querySelector('[name="reason"]').value = button.dataset.otReason || '';
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
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
