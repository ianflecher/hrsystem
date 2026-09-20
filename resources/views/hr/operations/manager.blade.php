<x-layouts.humanresource title="Manager Operations">
<div class="space-y-6">
<h1 class="text-2xl font-semibold">Manager Operations</h1>
<p class="text-sm opacity-70">Team-level attendance and approval visibility. Payroll details remain restricted.</p>
<div class="grid gap-4 md:grid-cols-3">
<div class="rounded-xl border p-4"><div class="text-xs uppercase opacity-60">Team members</div><div class="text-2xl font-semibold mt-2">{{ $employees->count() }}</div></div>
<div class="rounded-xl border p-4"><div class="text-xs uppercase opacity-60">Pending leave</div><div class="text-2xl font-semibold mt-2">{{ $pendingLeave->count() }}</div></div>
<div class="rounded-xl border p-4"><div class="text-xs uppercase opacity-60">Pending overtime</div><div class="text-2xl font-semibold mt-2">{{ $pendingOt->count() }}</div></div>
</div>
<div class="rounded-xl border overflow-hidden"><div class="p-4 border-b font-semibold">Team</div>@forelse($employees as $e)<div class="p-4 border-b flex justify-between"><span>{{ $e->full_name }}</span><a class="underline" href="{{ route('hr.operations.employee',$e->employee_id) }}">View</a></div>@empty<div class="p-6 opacity-70">No team members found.</div>@endforelse</div>
<div class="rounded-xl border overflow-hidden"><div class="p-4 border-b font-semibold">Pending leave</div>@forelse($pendingLeave as $r)<div class="p-4 border-b flex justify-between gap-4"><span>{{ $r->full_name }} — {{ $r->leave_type }} ({{ $r->start_date }} to {{ $r->end_date }})</span><a class="underline" href="{{ route('hr.operations.inbox') }}">Review</a></div>@empty<div class="p-6 opacity-70">No pending leave.</div>@endforelse</div>
<div class="rounded-xl border overflow-hidden"><div class="p-4 border-b font-semibold">Pending overtime</div>@forelse($pendingOt as $r)<div class="p-4 border-b flex justify-between gap-4"><span>{{ $r->full_name }} — {{ $r->starts_at }} to {{ $r->ends_at }}</span><a class="underline" href="{{ route('hr.operations.inbox') }}">Review</a></div>@empty<div class="p-6 opacity-70">No pending overtime.</div>@endforelse</div>
</div>
</x-layouts.humanresource>
