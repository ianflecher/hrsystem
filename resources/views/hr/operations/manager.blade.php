@php
    $isHr = \App\Support\PeopleAccess::isHr();
    $layout = $isHr ? 'layouts.humanresource' : 'layouts.app.employeeland';
    $teamEmployeeRoute = $isHr ? 'hr.operations.employee' : 'employee.team.employee';
    $teamLeaveRoute = $isHr ? 'hr.operations.manager.leave' : 'employee.team.leave';
    $teamOvertimeRoute = $isHr ? 'hr.operations.manager.overtime' : 'employee.team.overtime';
@endphp
<x-dynamic-component :component="$layout" title="Team Operations">

<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-red-600">Team operations</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Attendance and approvals</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">
                    Review your team attendance, send leave and overtime to HR, and prepare shift assignments for final approval.
                </p>
            </div>
        </div>
    </section>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach([
            ['label' => 'Team members', 'value' => $employees->count(), 'icon' => 'users', 'tone' => 'text-slate-700 bg-slate-100'],
            ['label' => 'Pending leave', 'value' => $pendingLeave->count(), 'icon' => 'umbrella-beach', 'tone' => 'text-amber-700 bg-amber-50'],
            ['label' => 'Pending overtime', 'value' => $pendingOt->count(), 'icon' => 'stopwatch', 'tone' => 'text-blue-700 bg-blue-50'],
        ] as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-slate-500">{{ $card['label'] }}</div>
                        <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $card['value'] }}</div>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $card['tone'] }}">
                        <i class="fas fa-{{ $card['icon'] }}"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold text-slate-950">Team</h2>
            <p class="text-sm text-slate-500">Open a profile to inspect attendance and leave history.</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($employees as $e)
                <div class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-slate-950">{{ $e->full_name }}</div>
                        <div class="mt-1 text-sm text-slate-500">{{ $e->job_title }} · {{ $e->department_name ?: 'No department' }}</div>
                    </div>
                    <a class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                       href="{{ route($teamEmployeeRoute,$e->employee_id) }}">
                        View
                        <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500">No team members found.</div>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Pending leave</h2>
                <p class="text-sm text-slate-500">{{ $isHr ? 'Waiting for HR final decision.' : 'Review first, then send to HR.' }}</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($pendingLeave as $r)
                    <div class="space-y-3 px-5 py-4">
                        <div class="flex flex-wrap justify-between gap-4">
                            <div>
                                <div class="font-semibold text-slate-950">{{ $r->full_name }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ ucfirst(str_replace('_', ' ', $r->leave_type)) }} · {{ $r->start_date }} to {{ $r->end_date }}</div>
                            </div>
                            <span class="h-fit rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                                {{ $r->total_days }} day(s)
                            </span>
                        </div>
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $r->reason }}</p>
                        @unless($isHr)
                            <form method="POST" action="{{ route($teamLeaveRoute,$r->leave_id) }}" class="grid gap-2 md:grid-cols-[1fr_auto_auto]">
                                @csrf
                                <input name="note" class="rounded-lg border-gray-300 text-sm" placeholder="Review note">
                                <button name="action" value="approve" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Send to HR</button>
                                <button name="action" value="reject" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Reject</button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No pending leave.</div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Pending overtime</h2>
                <p class="text-sm text-slate-500">{{ $isHr ? 'Waiting for HR final decision.' : 'Review first, then send to HR.' }}</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($pendingOt as $r)
                    <div class="space-y-3 px-5 py-4">
                        <div class="flex flex-wrap justify-between gap-4">
                            <div>
                                <div class="font-semibold text-slate-950">{{ $r->full_name }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $r->starts_at }} to {{ $r->ends_at }}</div>
                            </div>
                            <span class="h-fit rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                {{ intdiv((int) $r->minutes, 60) }} hour(s)
                            </span>
                        </div>
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $r->reason }}</p>
                        @unless($isHr)
                            <form method="POST" action="{{ route($teamOvertimeRoute,$r->id) }}" class="grid gap-2 md:grid-cols-[1fr_auto_auto]">
                                @csrf
                                <input name="note" class="rounded-lg border-gray-300 text-sm" placeholder="Review note">
                                <button name="action" value="approve" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Send to HR</button>
                                <button name="action" value="reject" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Reject</button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No pending overtime.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
</x-dynamic-component>
