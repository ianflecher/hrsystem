@use('App\Support\WorkWeek')
@php($teamShiftManager = !$hr && in_array(auth()->user()->role, ['supervisor', 'leader'], true))
@if($hr || $teamShiftManager)

    @if($hr)
    <details class="card"><summary>Add a holiday</summary>
        <form method="POST" action="{{ $base }}" class="grid divider">@csrf
            <input type="hidden" name="kind" value="holiday">
            <x-people.field name="date" label="Date" type="date" />
            <x-people.field name="name" label="Holiday" maxlength="120" placeholder="Independence Day" />
            <label class="people-field"><span>Type</span>
                <select name="type">
                    <option value="regular">Regular holiday</option>
                    <option value="special_non_working">Special non-working day</option>
                    <option value="special_working">Special working day</option>
                </select>
            </label>
            <p class="muted wide">Entering a date again replaces the holiday already on it.</p>
            <div><button>Save holiday</button></div>
        </form>
    </details>
    @endif
@endif

@php($periodStart = \Carbon\Carbon::parse($extra['period']->start))
@php($periodEnd = \Carbon\Carbon::parse($extra['period']->end))
<div class="card scroll">
    <h2>{{ $extra['period']->label() }}</h2>
    <p class="muted">Current payroll cutoff: who is on leave, and the holidays. Shifts and rest days are set on each person under Employees.</p>
<div class="calendar">
    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<strong class="weekday">{{ $day }}</strong>@endforeach
    @for($blank = 1; $blank < $periodStart->dayOfWeekIso; $blank++)<div></div>@endfor

    @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
        @php($date = $day->toDateString())
        @php($holiday = $extra['holidays']->get($date))
        @php($onLeave = $extra['leaves']->get($date, collect()))
        <div class="day {{ $holiday ? 'day--holiday' : ($onLeave->isNotEmpty() ? 'day--assigned' : 'day--work') }} {{ $date === now()->toDateString() ? 'today' : '' }}">
            <span class="day-number">{{ $day->day }}</span>

            @if($holiday)
                <div class="shift holiday">
                    <strong>{{ $holiday->name }}</strong><br>
                    {{ \App\Services\HolidayPay::describe($holiday->classification ?? $holiday->type) }}
                    @if($hr)
                        <form method="POST" action="{{ $base }}/{{ $holiday->id }}">@csrf
                            <button class="secondary" name="action" value="delete-holiday" aria-label="Remove {{ $holiday->name }} on {{ $date }}">Remove</button>
                        </form>
                    @endif
                </div>
            @endif

            @foreach($onLeave as $leave)
                <div class="shift rest">
                    <strong>{{ $leave->full_name }}</strong><br>
                    {{ ucwords(str_replace('_', ' ', (string) $leave->leave_type)) }}
                    @if($leave->status !== 'approved')<br><span class="badge">Pending</span>@endif
                </div>
            @endforeach
        </div>
    @endfor
</div></div>


{{-- Read-only: shifts and rest days come from each person under Employees,
     plus anything marked for this cutoff. Nothing here is edited. --}}
<div class="card scroll">
    <h2>Shift schedule · {{ $extra['period']->label() }}</h2>
    <p class="muted">Who works which hours, and who rests. For viewing only - change shifts and rest days under Employees.</p>
    @php($departments = $extra['staff']->pluck('department_name')->map(fn ($name) => $name ?: 'No department')->unique()->sort()->values())
    @php($pickedDepartment = request('department'))
    @php($shiftStaff = $pickedDepartment ? $extra['staff']->filter(fn ($p) => ($p->department_name ?: 'No department') === $pickedDepartment)->values() : $extra['staff'])
    @if($departments->count() > 1)
        <form method="GET" class="shift-picker">
            <label class="people-field"><span>Department</span>
                <select name="department" onchange="this.form.submit()">
                    <option value="">All departments ({{ $extra['staff']->count() }})</option>
                    @foreach($departments as $department)
                        <option value="{{ $department }}" @selected($pickedDepartment === $department)>
                            {{ $department }} ({{ $extra['staff']->filter(fn ($p) => ($p->department_name ?: 'No department') === $department)->count() }})
                        </option>
                    @endforeach
                </select>
            </label>
            <noscript><button>Show</button></noscript>
        </form>
    @endif
@php($shortTime = fn ($t) => $t ? ltrim(\Carbon\Carbon::parse($t)->format('g'), '0') : null)
@php($shiftLabel = fn ($start, $end) => $start ? $shortTime($start).($end ? '-'.$shortTime($end) : '') : '—')
<div class="shift-grid-wrap">
<table class="shift-grid">
    <thead>
        <tr>
            <th class="sg-name">Name</th>
            @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                @php($holiday = $extra['holidays']->get($day->toDateString()))
                <th class="{{ $day->isWeekend() ? 'sg-weekend' : '' }} {{ $holiday ? 'sg-holiday' : '' }} {{ $day->isToday() ? 'sg-today' : '' }}" title="{{ $holiday->name ?? '' }}">
                    <span>{{ strtoupper(substr($day->format('D'), 0, 2)) }}</span>{{ $day->day }}
                </th>
            @endfor
        </tr>
    </thead>
    <tbody>
        @forelse($shiftStaff->groupBy(fn ($p) => $p->department_name ?: 'No department')->sortKeys() as $department => $people)
            @if(! $pickedDepartment && $shiftStaff->pluck('department_name')->unique()->count() > 1)
                <tr class="sg-dept"><td colspan="{{ $periodStart->diffInDays($periodEnd) + 2 }}">{{ $department }}</td></tr>
            @endif
            @foreach($people as $person)
                <tr>
                    <td class="sg-name">{{ $person->full_name }}</td>
                    @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                        @php($date = $day->toDateString())
                        @php($mark = $extra['assignments']->get($date, collect())->firstWhere('employee_id', $person->employee_id))
                        @php($mark = ($mark && ($mark->status ?? 'approved') === 'approved') ? $mark : null)
                        @php($leave = $extra['leaves']->get($date, collect())->first(fn ($l) => $l->employee_id == $person->employee_id && $l->status === 'approved'))
                        @if($leave)
                            <td class="sg-leave" title="{{ ucwords(str_replace('_', ' ', (string) $leave->leave_type)) }}">LEAVE</td>
                        @elseif($mark ? $mark->rest_day : WorkWeek::restsOn($person->rest_days, $day))
                            <td class="sg-rest">RD</td>
                        @elseif($mark && $mark->starts_at)
                            <td class="{{ $day->isWeekend() ? 'sg-weekend' : '' }}">{{ $shiftLabel($mark->starts_at, $mark->ends_at) }}</td>
                        @else
                            <td class="{{ $day->isWeekend() ? 'sg-weekend' : '' }} {{ $person->shift_start ? '' : 'sg-none' }}">{{ $shiftLabel($person->shift_start, $person->shift_end) }}</td>
                        @endif
                    @endfor
                </tr>
            @endforeach
        @empty
            <tr><td colspan="{{ $periodStart->diffInDays($periodEnd) + 2 }}" class="muted">Nobody in this department.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
<p class="muted sg-legend"><span class="sg-rest">RD</span> rest day <span class="sg-leave">LEAVE</span> approved leave <span class="sg-none">—</span> no shift set yet</p>
</div>
