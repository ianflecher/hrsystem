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
            <h2 class="font-semibold text-slate-950">Team</h2>
            <p class="text-sm text-slate-500">Open a profile to inspect attendance and leave history, or set their shift and rest days.</p>
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
                    <details class="sm:col-span-2" @if(old('schedule_for') == $e->employee_id) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-red-600">
                            Shift &amp; rest days
                            <span class="font-normal text-slate-500">&middot; {{ $e->shift_start ? \Carbon\Carbon::parse($e->shift_start)->format('g:i A').'-'.($e->shift_end ? \Carbon\Carbon::parse($e->shift_end)->format('g:i A') : '?') : 'no shift set' }}</span>
                        </summary>
                        <form method="POST" action="{{ route($teamScheduleRoute, $e->employee_id) }}" class="mt-3 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">@csrf
                            <input type="hidden" name="schedule_for" value="{{ $e->employee_id }}">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Shift starts</span>
                                    <input type="time" name="shift_start" value="{{ $e->shift_start ? substr($e->shift_start, 0, 5) : '' }}" class="form-input">
                                    <span class="mt-1 block text-xs text-slate-500">Lateness is measured from this. Blank means never late.</span></label>
                                <label class="text-sm"><span class="mb-1 block font-medium text-slate-700">Shift ends</span>
                                    <input type="time" name="shift_end" value="{{ $e->shift_end ? substr($e->shift_end, 0, 5) : '' }}" class="form-input"></label>
                            </div>
                            <div>
                                <span class="block text-sm font-medium text-slate-700">Rest days (every week)</span>
                                <div class="mt-1 flex flex-wrap gap-3">
                                    @foreach(\App\Support\WorkWeek::DAYS as $number => $name)
                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="rest_days[]" value="{{ $number }}" @checked(in_array($number, \App\Support\WorkWeek::days($e->rest_days))) class="rounded border-slate-300">
                                            {{ substr($name, 0, 3) }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Their usual rest day, used for every cutoff unless changed below.</p>
                            </div>
                            <div>
                                <span class="block text-sm font-medium text-slate-700">Rest days this cutoff ({{ $cutoff->label() }})</span>
                                @foreach(collect($e->cutoffRest)->groupBy(fn ($rest, $date) => \Carbon\Carbon::parse($date)->startOfWeek()->toDateString(), true) as $monday => $days)
                                    <div class="mt-1 flex flex-wrap items-center gap-3">
                                        <span class="w-24 text-xs font-semibold text-slate-500">{{ \Carbon\Carbon::parse($monday)->format('M j') }}-{{ \Carbon\Carbon::parse($monday)->endOfWeek()->format('M j') }}</span>
                                        @foreach($days as $date => $rest)
                                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" name="cutoff_rest[]" value="{{ $date }}" @checked($rest) class="rounded border-slate-300">
                                                {{ \Carbon\Carbon::parse($date)->format('D j') }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                                <p class="mt-1 text-xs text-slate-500">Change a day here only when this cutoff is different - the weekly default stays as it is.</p>
                            </div>
                            <button class="btn-primary">Save</button>
                        </form>
                    </details>
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
