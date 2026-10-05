@php
    $isHr = \App\Support\PeopleAccess::isHr();
    $layout = $isHr ? 'layouts.humanresource' : 'layouts.app.employeeland';
    $teamEmployeeRoute = $isHr ? 'hr.operations.employee' : 'employee.team.employee';
    $teamLeaveRoute = $isHr ? 'hr.operations.manager.leave' : 'employee.team.leave';
    $teamOvertimeRoute = $isHr ? 'hr.operations.manager.overtime' : 'employee.team.overtime';
    $teamAttendanceRoute = $isHr ? 'hr.operations.manager.attendance' : 'employee.team.attendance';
    $teamScheduleRoute = $isHr ? 'hr.operations.manager.schedule' : 'employee.team.schedule';
    $teamObRoute = $isHr ? 'hr.operations.manager.ob' : 'employee.team.ob';
    $teamTimeLogRoute = $isHr ? 'hr.operations.manager.timelog' : 'employee.team.timelog';
    $showWorkedFromScanner = strcasecmp(auth()->user()->full_name ?? '', 'Emadeth Togonon Comanda') === 0;
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

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    {{-- People the scanner cannot record yet: their days are entered here, or
         sent in by them and approved here. --}}
    @if($noScanner->isNotEmpty() || $pendingTimeLogs->isNotEmpty())
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold text-slate-950">No scanner yet</h2>
            <p class="text-sm text-slate-500">For team members without a scanner ID. Enter their shift and times for a day, or approve the times they sent in. They drop off this list once they have a scanner ID.</p>
        </div>

        @if($pendingTimeLogs->isNotEmpty())
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-slate-900">Sent in by them - waiting for you</h3>
                <div class="mt-3 space-y-3">
                    @foreach($pendingTimeLogs as $log)
                        <form method="POST" action="{{ route($teamTimeLogRoute, $log->id) }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">@csrf
                            <div class="min-w-[12rem] flex-1 text-sm">
                                <div class="font-semibold text-slate-950">{{ $log->full_name }} · {{ \Carbon\Carbon::parse($log->date)->format('D, M j') }}</div>
                                <div class="text-slate-700">In {{ \Carbon\Carbon::parse($log->time_in)->format('g:i A') }} · Out {{ $log->time_out ? \Carbon\Carbon::parse($log->time_out)->format('g:i A') : '—' }}@if($log->note) · "{{ $log->note }}"@endif</div>
                            </div>
                            <input type="text" name="review_note" maxlength="255" placeholder="Note (optional)" class="form-input w-44 text-sm">
                            <button name="action" value="approve" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                            <button name="action" value="reject" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reject</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        @if($noScanner->isNotEmpty())
            <form method="POST" action="{{ route($teamAttendanceRoute) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-7 lg:items-end">@csrf
                <label class="lg:col-span-2 text-sm"><span class="mb-1 block font-medium text-slate-700">Employee</span>
                    <select name="employee_id" class="form-input" required>
                        <option value="">Choose…</option>
                        @foreach($noScanner as $person)
                            <option value="{{ $person->employee_id }}" @selected(old('employee_id') == $person->employee_id)>{{ $person->full_name }}{{ $person->employee_no ? ' ('.$person->employee_no.')' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Date</span>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-input" required></label>
                <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Shift start</span>
                    <input type="time" name="shift_start" value="{{ old('shift_start', '08:00') }}" class="form-input" required></label>
                <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Shift end</span>
                    <input type="time" name="shift_end" value="{{ old('shift_end', '17:00') }}" class="form-input" required></label>
                <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Time in</span>
                    <input type="time" name="time_in" value="{{ old('time_in') }}" class="form-input"></label>
                <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Time out</span>
                    <input type="time" name="time_out" value="{{ old('time_out') }}" class="form-input"></label>
                <div class="sm:col-span-2 lg:col-span-7 flex items-center gap-3">
                    <button class="btn-primary">Save day</button>
                    <span class="text-xs text-slate-500">Leave the times blank to set only the shift. Marked as entered by you.</span>
                </div>
            </form>
        @endif

        @if($recentManual->isNotEmpty())
            <div class="border-t border-slate-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-slate-900">Recent days</h3>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-1 pr-4">Date</th><th class="pr-4">Employee</th><th class="pr-4">In</th><th class="pr-4">Out</th><th>Entered</th></tr></thead>
                        <tbody>
                            @foreach($recentManual as $day)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1.5 pr-4">{{ \Carbon\Carbon::parse($day->date)->format('D, M j') }}</td>
                                    <td class="pr-4">{{ $day->full_name }}</td>
                                    <td class="pr-4">{{ $day->time_in ? \Carbon\Carbon::parse($day->time_in)->format('g:i A') : '—' }}</td>
                                    <td class="pr-4">{{ $day->time_out ? \Carbon\Carbon::parse($day->time_out)->format('g:i A') : '—' }}</td>
                                    <td class="text-slate-500">{{ $day->notes }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
    @endif

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

    @if($employees->isNotEmpty())
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold text-slate-950">Official business</h2>
            <p class="text-sm text-slate-500">For people working away from the office, like at an event. Those days count as full working days - not absent, late or short - and the scanner sync will not change them.</p>
        </div>
        <form method="POST" action="{{ route($teamObRoute) }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1.5fr_auto] lg:items-end">@csrf
            <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Employee</span>
                <select name="ob_employee" class="form-input" required>
                    <option value="">Choose employee...</option>
                    @foreach($employees as $person)
                        <option value="{{ $person->employee_id }}" @selected(old('ob_employee') == $person->employee_id)>{{ $person->full_name }}</option>
                    @endforeach
                </select></label>
            <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">From</span>
                <input type="date" name="ob_from" value="{{ old('ob_from') }}" class="form-input" required></label>
            <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">To</span>
                <input type="date" name="ob_to" value="{{ old('ob_to') }}" class="form-input"></label>
            <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Where</span>
                <input type="text" name="ob_note" value="{{ old('ob_note') }}" maxlength="120" placeholder="e.g. MMDA event" class="form-input" required></label>
            <button class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"><i class="fas fa-briefcase"></i> Mark official business</button>
        </form>
        @if($errors->hasAny(['ob_employee', 'ob_from', 'ob_to', 'ob_note']))
            <p class="px-5 pb-4 text-sm text-red-600">{{ $errors->first('ob_employee') ?: $errors->first('ob_from') ?: $errors->first('ob_to') ?: $errors->first('ob_note') }}</p>
        @endif
    </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <div class="grid gap-3 lg:grid-cols-[1fr_22rem] lg:items-end">
                <div>
                    <h2 class="font-semibold text-slate-950">Team</h2>
                    <p class="text-sm text-slate-500">Open a profile to inspect attendance and leave history.</p>
                </div>
                <label class="text-sm">
                    <span class="mb-1 block font-medium text-slate-700">Search team</span>
                    <div class="relative">
                        <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                        <input type="search" data-team-search placeholder="Name, ID, position, department"
                               class="form-input w-full pl-9">
                    </div>
                </label>
            </div>
        </div>
        <div class="divide-y divide-slate-100" data-team-list>
            @forelse($employees as $e)
                <div class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_auto] sm:items-center"
                     data-team-member="{{ \Illuminate\Support\Str::lower(trim($e->full_name.' '.$e->employee_no.' '.$e->job_title.' '.$e->department_name)) }}">
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-slate-950">{{ $e->full_name }}</div>
                        <div class="mt-1 text-sm text-slate-500">{{ $e->job_title }} · {{ $e->department_name ?: 'No department' }}@if($e->employee_no) · {{ $e->employee_no }}@endif</div>
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
            <div data-team-empty class="hidden px-5 py-10 text-center text-sm text-slate-500">No team members match your search.</div>
        </div>
    </section>

    @if($showWorkedFromScanner && $employees->isNotEmpty())
        @php
            $periodStart = \Carbon\Carbon::parse($cutoff->start);
            $periodEnd = \Carbon\Carbon::parse($cutoff->end);
            $clock = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('g:i') : '?';
            $leavePaid = fn ($l) => $l->leave_type !== 'unpaid' && ($l->pay_status === null || $l->pay_status === 'paid');
            $lateTotals = [];
        @endphp
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Worked · from the scanner · {{ $cutoff->label() }}</h2>
                <p class="text-sm text-slate-500">Each day's first in and final out for this team only. Red means late, undertime, missing out, or absent.</p>
            </div>
            <div class="overflow-x-auto px-5 py-4">
                <table class="min-w-full border-separate border-spacing-0 text-xs">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 min-w-56 border border-slate-200 bg-slate-50 px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600">Name</th>
                            @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                                <th class="min-w-16 border-y border-r border-slate-200 px-2 py-2 text-center font-semibold {{ $day->isWeekend() ? 'bg-red-50 text-red-700' : 'bg-slate-50 text-slate-700' }}" title="{{ $day->format('l, M j, Y') }}">
                                    <span class="block text-[10px] uppercase text-slate-500">{{ substr($day->format('D'), 0, 2) }}</span>
                                    {{ $day->day }}
                                </th>
                            @endfor
                            <th class="min-w-20 border-y border-r border-slate-200 bg-slate-50 px-2 py-2 text-center font-semibold uppercase tracking-wide text-slate-600">Late</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employees->groupBy(fn ($p) => $p->department_name ?: 'No department')->sortKeys() as $department => $people)
                            @if($employees->pluck('department_name')->unique()->count() > 1)
                                <tr>
                                    <td colspan="{{ $periodStart->diffInDays($periodEnd) + 3 }}" class="border-x border-b border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $department }}</td>
                                </tr>
                            @endif
                            @foreach($people as $person)
                                <tr>
                                    <td class="sticky left-0 z-10 border-x border-b border-slate-200 bg-white px-3 py-2 font-semibold text-slate-950">{{ $person->full_name }}</td>
                                    @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                                        @php($date = $day->toDateString())
                                        @php($a = $teamWorked[$person->employee_id.'|'.$date] ?? null)
                                        @php($leave = $teamLeaves[$person->employee_id.'|'.$date] ?? null)
                                        @php($plan = \App\Support\ShiftSchedule::forEmployeeDate($person, $date))
                                        @if($a && $a->time_in)
                                            @php($effectiveOut = \App\Support\WorkDay::effectiveOut($a))
                                            @php($usedFallbackOut = ! $a->time_out && $effectiveOut)
                                            @php($lateMin = ! $plan['rest'] && $plan['start'] ? (int) (\App\Support\Tardiness::minutesLate(\Carbon\Carbon::parse($a->time_in), $plan['start']) ?? 0) : 0)
                                            @php($missingFinalOut = ! $plan['rest'] && $plan['end'] && ! $effectiveOut)
                                            @php($shortMin = $missingFinalOut ? 15 : (! $plan['rest'] && $plan['end'] && $effectiveOut ? (int) (\App\Support\Undertime::minutesShort($effectiveOut, $plan['end']) ?? 0) : 0))
                                            @php($late = $lateMin > 5)
                                            @php($short = $shortMin > 5)
                                            @php($late ? $lateTotals[$person->employee_id][0] = ($lateTotals[$person->employee_id][0] ?? 0) + 1 : null)
                                            @php($late ? $lateTotals[$person->employee_id][1] = ($lateTotals[$person->employee_id][1] ?? 0) + $lateMin : null)
                                            @php($cellClass = ! $effectiveOut ? 'border-red-400 bg-red-50 text-red-700' : ($late || $short ? 'border-orange-400 bg-orange-50 text-orange-800' : 'border-emerald-400 bg-emerald-50 text-emerald-800'))
                                            <td class="border-b border-r border-l-4 border-slate-200 px-2 py-2 text-center leading-tight {{ $cellClass }}" title="In {{ $clock($a->time_in) }}, out {{ $effectiveOut ? $clock($effectiveOut).($usedFallbackOut ? ' last punch' : '') : 'no out' }}">
                                                <span class="{{ $late ? 'font-bold text-red-700' : '' }}">{{ $clock($a->time_in) }}</span>
                                                @if($late)<span class="block text-[10px] text-red-700">{{ $lateMin }}m late</span>@endif
                                                <span class="{{ $short || ! $effectiveOut ? 'font-bold text-red-700' : '' }}">{{ $effectiveOut ? $clock($effectiveOut) : 'no out' }}</span>
                                                @if($usedFallbackOut)<span class="block text-[10px] text-slate-500">last punch</span>@endif
                                                @if($short)<span class="block text-[10px] text-red-700">{{ $missingFinalOut ? 'undertime' : $shortMin.'m early' }}</span>@endif
                                            </td>
                                        @elseif($leave)
                                            @php($isPaid = $leavePaid($leave))
                                            <td class="border-b border-r border-slate-200 px-2 py-2 text-center font-bold {{ $isPaid ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700' }}" title="{{ ucwords(str_replace('_', ' ', (string) $leave->leave_type)) }} - {{ $isPaid ? 'paid' : 'unpaid' }}">{{ $isPaid ? 'PAID' : 'UNPAID' }}</td>
                                        @elseif($a && $a->status === 'official_business')
                                            <td class="border-b border-r border-slate-200 bg-cyan-100 px-2 py-2 text-center font-bold text-cyan-700">OB</td>
                                        @elseif($a && $a->notes === 'Suspension')
                                            <td class="border-b border-r border-slate-200 bg-slate-600 px-2 py-2 text-center font-bold text-white">S</td>
                                        @elseif($day->lt(\Carbon\Carbon::today()) && ! $plan['rest'] && ! $leave)
                                            <td class="border-b border-r border-slate-200 bg-red-600 px-2 py-2 text-center font-bold text-white" title="Absent">ABS</td>
                                        @elseif($plan['rest'])
                                            <td class="border-b border-r border-slate-200 bg-red-600 px-2 py-2 text-center font-bold tracking-wide text-white">RD</td>
                                        @else
                                            <td class="border-b border-r border-slate-200 px-2 py-2 text-center text-slate-300">{{ $day->lt(\Carbon\Carbon::today()) ? '—' : '' }}</td>
                                        @endif
                                    @endfor
                                    <td class="border-b border-r border-slate-200 px-2 py-2 text-center font-semibold">
                                        @if($total = $lateTotals[$person->employee_id] ?? null)
                                            <span class="text-red-700">{{ $total[0] }}× · {{ $total[1] }}m</span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Pending leave</h2>
                <p class="text-sm text-slate-500">{{ $isHr ? "For your information: the supervisor approves, then Ma'am An. HR does not approve leave." : (\App\Support\PeopleAccess::isOperationsSupervisor() ? 'Your approval is final.' : "Review first, then send to Ma'am An.") }}</p>
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
                                {{ $r->total_days }} day(s) · {{ $r->status === 'pending_hr' ? "Waiting for Ma'am An" : 'Waiting for supervisor' }}
                            </span>
                        </div>
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $r->reason }}</p>
                        @if($r->paid_entitled !== null)
                            <div class="grid gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 sm:grid-cols-4">
                                <div>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-emerald-700">Paid leave</span>
                                    <strong>{{ rtrim(rtrim(number_format((float) $r->paid_remaining, 1), '0'), '.') }}</strong> left
                                </div>
                                <div>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-emerald-700">Used</span>
                                    {{ rtrim(rtrim(number_format((float) $r->paid_used, 1), '0'), '.') }} day(s)
                                </div>
                                <div>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-emerald-700">Pending</span>
                                    {{ rtrim(rtrim(number_format((float) $r->paid_pending, 1), '0'), '.') }} day(s)
                                </div>
                                <div>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-emerald-700">Year limit</span>
                                    {{ rtrim(rtrim(number_format((float) $r->paid_entitled, 1), '0'), '.') }} day(s)
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                No yearly paid-leave limit is set for this type.
                            </div>
                        @endif
                        @unless($isHr)
                            <form method="POST" action="{{ route($teamLeaveRoute,$r->leave_id) }}" class="grid gap-2 md:grid-cols-[1fr_auto_auto]">
                                @csrf
                                <input name="note" class="rounded-lg border-gray-300 text-sm" placeholder="Review note">
                                <button name="action" value="approve" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ \App\Support\PeopleAccess::isOperationsSupervisor() ? 'Approve' : "Send to Ma'am An" }}</button>
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
                <p class="text-sm text-slate-500">{{ $isHr ? "For your information: the supervisor approves, then Ma'am An. Approved overtime goes to payroll." : (\App\Services\OvertimeApproval::isFinalApprover() ? 'Approved overtime goes straight to payroll.' : "Review first, then send to Ma'am An.") }}</p>
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
                                {{ intdiv((int) $r->minutes, 60) }} hour(s) · {{ $r->status === 'pending_hr' ? "Waiting for Ma'am An" : 'Waiting for supervisor' }}
                            </span>
                        </div>
                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $r->reason }}</p>
                        @if($otStage = \App\Services\OvertimeApproval::stage($r))
                            <form method="POST" action="{{ route($teamOvertimeRoute,$r->id) }}" class="grid gap-2 md:grid-cols-[1fr_auto_auto]">
                                @csrf
                                <input name="note" class="rounded-lg border-gray-300 text-sm" placeholder="Review note">
                                <button name="action" value="approve" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ $otStage === 'final' ? 'Approve' : "Send to Ma'am An" }}</button>
                                <button name="action" value="reject" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Reject</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No pending overtime.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
<script>
    (() => {
        const input = document.querySelector('[data-team-search]');
        const rows = Array.from(document.querySelectorAll('[data-team-member]'));
        const empty = document.querySelector('[data-team-empty]');

        if (!input || rows.length === 0) return;

        const filter = () => {
            const query = input.value.trim().toLowerCase();
            let shown = 0;

            rows.forEach(row => {
                const match = !query || row.dataset.teamMember.includes(query);
                row.hidden = !match;
                if (match) shown++;
            });

            if (empty) empty.hidden = shown !== 0;
        };

        input.addEventListener('input', filter);
        filter();
    })();
</script>
</x-dynamic-component>
