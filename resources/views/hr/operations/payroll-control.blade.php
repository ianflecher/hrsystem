<x-layouts.humanresource title="Payroll Control Center">
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div><h1 class="text-2xl font-semibold">Payroll Control Center</h1><p class="text-sm opacity-70">Validate, review and resolve payroll issues before approval.</p></div>
        <form method="get"><select name="period" onchange="this.form.submit()" class="rounded-lg border px-3 py-2 bg-white text-black">@foreach(\App\Support\PayPeriod::recent(12) as $p)<option value="{{ $p->start }}" @selected($p->start === $period->start)>{{ $p->label() }}</option>@endforeach</select></form>
    </div>
    @if(session('success'))<div class="rounded-lg border p-3">{{ session('success') }}</div>@endif
    <div class="grid gap-4 md:grid-cols-5">
        @foreach([['Employees',$totals->employees],['Gross','₱'.number_format($totals->gross,2)],['Deductions','₱'.number_format($totals->deductions,2)],['Net','₱'.number_format($totals->net,2)],['Employer cost','₱'.number_format($totals->employer_cost,2)]] as $card)<div class="rounded-xl border p-4"><div class="text-xs uppercase opacity-60">{{ $card[0] }}</div><div class="mt-2 text-xl font-semibold">{{ $card[1] }}</div></div>@endforeach
    </div>
    <div class="rounded-xl border overflow-hidden">
        <div class="p-4 border-b"><h2 class="font-semibold">Payroll anomalies ({{ $anomalies->count() }})</h2></div>
        @forelse($anomalies as $a)<div class="p-4 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3"><div><div class="font-medium">{{ $a->full_name ?: 'Employee #'.$a->employee_id }}</div><div class="text-sm opacity-70">{{ $a->message }}</div></div><form method="post" action="{{ route('hr.operations.anomaly.resolve',$a->id) }}">@csrf<button class="rounded-lg border px-3 py-2 text-sm">Mark resolved</button></form></div>@empty<div class="p-6 text-sm opacity-70">No unresolved payroll anomalies.</div>@endforelse
    </div>
    <div class="rounded-xl border overflow-hidden">
        <div class="p-4 border-b"><h2 class="font-semibold">Open exceptions ({{ $exceptions->count() }})</h2></div>
        @forelse($exceptions as $x)<div class="p-4 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3"><div><div class="font-medium">{{ $x->full_name ?: 'Employee #'.$x->employee_id }}</div><div class="text-sm opacity-70">{{ $x->message }}</div></div><form method="post" action="{{ route('hr.operations.exception.resolve',$x->id) }}">@csrf<button class="rounded-lg border px-3 py-2 text-sm">Mark resolved</button></form></div>@empty<div class="p-6 text-sm opacity-70">No open exceptions for this period.</div>@endforelse
    </div>
</div>
</x-layouts.humanresource>
