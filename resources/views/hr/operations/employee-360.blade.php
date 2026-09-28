@php
    $hr = \App\Support\PeopleAccess::isHr();
    $layout = $hr ? 'layouts.humanresource' : 'layouts.app.employeeland';
    $teamRoute = $hr ? 'hr.operations.manager' : 'employee.team';
    $statusTone = [
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'present' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'late' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'pending_hr' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'absent' => 'bg-red-50 text-red-700 ring-red-200',
        'rejected' => 'bg-red-50 text-red-700 ring-red-200',
        'terminated' => 'bg-red-50 text-red-700 ring-red-200',
        'awol' => 'bg-red-50 text-red-700 ring-red-200',
        'inactive' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $tone = fn ($status) => $statusTone[$status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    $attendanceCounts = collect($attendance)->countBy('status');
    $attendanceByDate = collect($attendance)->keyBy('date');
    $calendarAnchor = $attendanceByDate->keys()->first()
        ? \Carbon\Carbon::parse($attendanceByDate->keys()->first())->startOfMonth()
        : now()->startOfMonth();
    $calendarStart = $calendarAnchor->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $calendarEnd = $calendarAnchor->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
    $calendarDays = [];
    for ($day = $calendarStart->copy(); $day->lte($calendarEnd); $day->addDay()) {
        $calendarDays[] = $day->copy();
    }
    $dayTone = [
        'present' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'late' => 'border-amber-200 bg-amber-50 text-amber-900',
        'absent' => 'border-red-200 bg-red-50 text-red-900',
        'on_leave' => 'border-blue-200 bg-blue-50 text-blue-900',
        'half_day' => 'border-yellow-200 bg-yellow-50 text-yellow-900',
    ];
@endphp
<x-dynamic-component :component="$layout" title="Employee 360">

<div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-5">
            <a href="{{ route($teamRoute) }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-900">
                <i class="fas fa-arrow-left text-xs"></i>
                Team dashboard
            </a>
        </div>
        <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1fr_auto] lg:items-center">
            <div class="flex min-w-0 items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-lg font-semibold text-white">
                    {{ collect(explode(' ', $employee->full_name))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('') }}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-950">{{ $employee->full_name }}</h1>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $tone($employee->status) }}">
                            {{ ucfirst(str_replace('_', ' ', $employee->status)) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">{{ $employee->job_title }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $employee->department_name ?: 'No department assigned' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl border border-slate-200 px-4 py-3">
                    <div class="text-2xl font-semibold text-slate-950">{{ $attendanceCounts->sum() }}</div>
                    <div class="mt-1 text-xs font-medium uppercase text-slate-500">Records</div>
                </div>
                <div class="rounded-xl border border-slate-200 px-4 py-3">
                    <div class="text-2xl font-semibold text-amber-600">{{ (int) ($attendanceCounts['late'] ?? 0) }}</div>
                    <div class="mt-1 text-xs font-medium uppercase text-slate-500">Late</div>
                </div>
                <div class="rounded-xl border border-slate-200 px-4 py-3">
                    <div class="text-2xl font-semibold text-red-600">{{ (int) ($attendanceCounts['absent'] ?? 0) }}</div>
                    <div class="mt-1 text-xs font-medium uppercase text-slate-500">Absent</div>
                </div>
            </div>
        </div>
    </section>

    @if($hr)
        <div class="grid gap-4 md:grid-cols-4">
            @foreach([['Salary','PHP '.number_format($employee->salary,2)],['Region',$employee->work_region ?: 'Not set'],['SSS',$employee->sss_number ?: '-'],['TIN',$employee->tin ?: '-']] as $c)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $c[0] }}</div>
                    <div class="mt-2 text-sm font-semibold text-slate-950">{{ $c[1] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="font-semibold text-slate-950">Attendance Calendar</h2>
                    <p class="text-sm text-slate-500">{{ $calendarAnchor->format('F Y') }}</p>
                </div>
                <div class="flex flex-wrap justify-end gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 text-slate-500"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Present</span>
                    <span class="inline-flex items-center gap-1 text-slate-500"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Late</span>
                    <span class="inline-flex items-center gap-1 text-slate-500"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>Absent</span>
                </div>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase text-slate-400">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday)
                        <div>{{ $weekday }}</div>
                    @endforeach
                </div>
                <div class="mt-2 grid grid-cols-7 gap-2">
                    @foreach($calendarDays as $day)
                        @php
                            $record = $attendanceByDate->get($day->toDateString());
                            $inMonth = $day->month === $calendarAnchor->month;
                            $classes = $record
                                ? ($dayTone[$record->status] ?? 'border-slate-200 bg-slate-50 text-slate-700')
                                : 'border-slate-200 bg-white text-slate-500';
                        @endphp
                        <div class="min-h-[5.75rem] rounded-xl border p-2 {{ $classes }} {{ $inMonth ? '' : 'opacity-40' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold">{{ $day->day }}</span>
                                @if($record)
                                    <span class="h-2 w-2 rounded-full
                                        @if($record->status === 'present') bg-emerald-500
                                        @elseif($record->status === 'late') bg-amber-500
                                        @elseif($record->status === 'absent') bg-red-500
                                        @elseif($record->status === 'on_leave') bg-blue-500
                                        @else bg-slate-400 @endif"></span>
                                @endif
                            </div>
                            @if($record)
                                <div class="mt-3 text-xs font-semibold">{{ ucfirst(str_replace('_', ' ', $record->status)) }}</div>
                                <div class="mt-1 text-[11px] leading-4 opacity-80">
                                    {{ $record->time_in ? \Carbon\Carbon::parse($record->time_in)->format('g:i A') : 'No in' }}
                                    @foreach(['lunch_in' => 'Lunch in', 'lunch_out' => 'Lunch out', 'cb_in' => 'CB in', 'cb_out' => 'CB out'] as $punch => $punchLabel)
                                        @if($record->{$punch} ?? null)<br>{{ $punchLabel }} {{ \Carbon\Carbon::parse($record->{$punch})->format('g:i A') }}@endif
                                    @endforeach
                                    <br>
                                    {{ $record->time_out ? \Carbon\Carbon::parse($record->time_out)->format('g:i A') : 'No out' }}
                                </div>
                            @else
                                <div class="mt-3 text-xs text-slate-400">No record</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="font-semibold text-slate-950">Leave History</h2>
                    <p class="text-sm text-slate-500">Requests and approval status</p>
                </div>
                <i class="fas fa-umbrella-beach text-slate-300"></i>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($leave as $l)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-slate-900">
                                    {{ \Carbon\Carbon::parse($l->start_date)->format('M d, Y') }}
                                    @if($l->end_date !== $l->start_date)
                                        - {{ \Carbon\Carbon::parse($l->end_date)->format('M d, Y') }}
                                    @endif
                                </div>
                                <div class="mt-0.5 text-sm text-slate-500">{{ ucfirst(str_replace('_', ' ', $l->leave_type ?? 'leave')) }}</div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $tone($l->status) }}">
                                {{ $l->status === 'pending_hr' ? 'HR review' : ucfirst(str_replace('_', ' ', $l->status)) }}
                            </span>
                        </div>
                        @if(!empty($l->reason))
                            <p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $l->reason }}</p>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No leave records.</div>
                @endforelse
            </div>
        </section>

        @if($hr)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h2 class="border-b border-slate-100 px-5 py-4 font-semibold text-slate-950">Payroll History</h2>
                <div class="divide-y divide-slate-100">
                    @forelse($payroll as $p)
                        <div class="flex justify-between gap-4 px-5 py-3 text-sm">
                            <span class="text-slate-600">{{ $p->period_start }} - {{ $p->period_end }}</span>
                            <span class="font-semibold text-slate-950">PHP {{ number_format($p->net_pay,2) }} · {{ ucfirst($p->status) }}</span>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500">No payroll records.</div>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h2 class="border-b border-slate-100 px-5 py-4 font-semibold text-slate-950">Salary History</h2>
                <div class="divide-y divide-slate-100">
                    @forelse($salaryHistory as $s)
                        <div class="flex justify-between gap-4 px-5 py-3 text-sm">
                            <span class="text-slate-600">{{ $s->effective_from }}<br><span class="text-xs text-slate-400">{{ $s->reason }}</span></span>
                            <span class="font-semibold text-slate-950">PHP {{ number_format($s->salary,2) }}</span>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500">No recorded salary changes yet.</div>
                    @endforelse
                </div>
            </section>
        @endif
    </div>

    @if($hr)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-950">Record Lifecycle Event</h2>
            <form method="POST" action="{{ route('hr.operations.employee.lifecycle',$employee->employee_id) }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <select name="event_type" class="rounded-lg border-gray-300">
                    <option>onboarding</option>
                    <option>probation</option>
                    <option>promotion</option>
                    <option>transfer</option>
                    <option>salary_change</option>
                    <option>separation</option>
                    <option>rehire</option>
                </select>
                <input type="date" name="effective_date" required class="rounded-lg border-gray-300">
                <input name="details" placeholder="Details" class="rounded-lg border-gray-300">
                <button class="rounded-lg bg-slate-900 px-4 text-white">Record</button>
            </form>
        </section>
    @endif
</div>
</x-dynamic-component>
