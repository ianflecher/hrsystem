@use('App\Support\WorkWeek')
@php($teamShiftManager = !$hr && in_array(auth()->user()->role, ['supervisor', 'leader'], true))
@if($hr || $teamShiftManager)

    {{-- The team's supervisor or leader sets the schedule on the grid; HR does not. --}}
    @if($teamShiftManager)
    @include('people.partials.shift-grid')
    @endif

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
    <h2>Leave calendar · {{ $extra['period']->label() }}</h2>
    <p class="muted">Current payroll cutoff: who is on leave, and the holidays. Shifts and rest days are set on each person under Employees.</p>
{{-- Phones: only the days something happens, as a list. A seven-column
     grid of mostly empty boxes does not fit a phone. --}}
<ul class="leave-agenda">
    @php($agendaDays = 0)
    @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
        @php($date = $day->toDateString())
        @php($holiday = $extra['holidays']->get($date))
        @php($onLeave = $extra['leaves']->get($date, collect()))
        @if($holiday || $onLeave->isNotEmpty())
            @php($agendaDays++)
            <li class="{{ $date === now()->toDateString() ? 'today' : '' }}">
                <div class="agenda-date"><strong>{{ $day->day }}</strong><span>{{ $day->format('D') }}</span></div>
                <div class="agenda-items">
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
                            <strong>{{ $leave->full_name }}</strong> · {{ ucwords(str_replace('_', ' ', (string) $leave->leave_type)) }}
                            @if($leave->status !== 'approved') <span class="badge">Pending</span>@endif
                        </div>
                    @endforeach
                </div>
            </li>
        @endif
    @endfor
    @if($agendaDays === 0)
        <li class="agenda-empty">Nobody is on leave and there are no holidays this cutoff.</li>
    @endif
</ul>
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
    <p class="muted">Who works which hours, and who rests. Supervisors and team leaders change it with Edit schedule; below it is what the scanner saw.</p>
    @php($departments = $extra['staff']->pluck('department_name')->map(fn ($name) => $name ?: 'No department')->unique()->sort()->values())
    @php($pickedDepartment = request('department'))
    @php($shiftStaff = $pickedDepartment ? $extra['staff']->filter(fn ($p) => ($p->department_name ?: 'No department') === $pickedDepartment)->values() : $extra['staff'])
    <form method="GET" class="shift-period-filter">
        <label class="people-field"><span>Cutoff</span>
            <select name="cutoff" onchange="this.form.submit()">
                @foreach($extra['periods'] as $optionPeriod)
                    <option value="{{ $optionPeriod->start }}" @selected($optionPeriod->start === $extra['period']->start)>
                        {{ $optionPeriod->label() }}
                    </option>
                @endforeach
            </select>
        </label>
        @if($departments->count() > 1)
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
        @endif
        <noscript><button>Show</button></noscript>
    </form>
@php($shortTime = fn ($t) => $t ? ltrim(\Carbon\Carbon::parse($t)->format('g'), '0') : null)
@php($fullTime = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i') : null)
@php($leavePaid = fn ($l) => $l->leave_type !== 'unpaid' && ($l->pay_status === null || $l->pay_status === 'paid'))
@php($shiftLabel = fn ($start, $end) => $start ? $shortTime($start).($end ? '-'.$shortTime($end) : '') : '—')
@php($shiftTitle = fn ($start, $end) => $start ? $fullTime($start).($end ? '-'.$fullTime($end) : '') : 'No shift set')
@php($monthlyCutoff = function ($person) use ($periodStart, $periodEnd) {
    if (($person->pay_basis ?? null) !== 'monthly') return null;
    $total = $periodStart->diffInDays($periodEnd) + 1;
    $rest = 0;
    for ($d = $periodStart->copy(); $d->lte($periodEnd); $d->addDay()) {
        if ($d->isSunday()) $rest++;
    }
    return ['paid' => max(0, $total - $rest), 'rest' => $rest];
})
<div class="shift-grid-wrap">
<table class="shift-grid">
    <thead>
        <tr>
            <th class="sg-name">Name</th>
            @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                @php($holiday = $extra['holidays']->get($day->toDateString()))
                <th class="{{ $day->isWeekend() ? 'sg-weekend' : '' }} {{ $holiday ? 'sg-holiday' : '' }} {{ $day->isToday() ? 'sg-today' : '' }}" title="{{ $day->format('l, M j, Y') }}{{ $holiday ? ' - '.$holiday->name : '' }}">
                    <span>{{ strtoupper(substr($day->format('D'), 0, 2)) }}</span>{{ $day->day }}
                </th>
            @endfor
            <th class="sg-leave-total" title="Leave days this cutoff">Leave</th>
        </tr>
    </thead>
    <tbody>
        @forelse($shiftStaff->groupBy(fn ($p) => $p->department_name ?: 'No department')->sortKeys() as $department => $people)
            @if(! $pickedDepartment && $shiftStaff->pluck('department_name')->unique()->count() > 1)
                <tr class="sg-dept"><td colspan="{{ $periodStart->diffInDays($periodEnd) + 3 }}"><span>{{ $department }}</span></td></tr>
            @endif
            @foreach($people as $person)
                <tr class="sg-name-row"><td colspan="{{ $periodStart->diffInDays($periodEnd) + 1 }}">{{ $person->full_name }}</td></tr>
                @php($paidDays = 0)
                @php($unpaidDays = 0)
                @php($monthly = $monthlyCutoff($person))
                <tr>
                    <td class="sg-name">
                        {{ $person->full_name }}
                        @if($monthly)
                            <span class="sg-monthly-note">{{ $monthly['paid'] }} working · {{ $monthly['rest'] }} resting</span>
                        @endif
                    </td>
                    @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                        @php($date = $day->toDateString())
                        @php($mark = $extra['assignments']->get($date, collect())->firstWhere('employee_id', $person->employee_id))
                        @php($mark = ($mark && ($mark->status ?? 'approved') === 'approved') ? $mark : null)
                        @php($leave = $extra['leaves']->get($date, collect())->first(fn ($l) => $l->employee_id == $person->employee_id && $l->status === 'approved'))
                        @php($dayMark = ($extra['dayMarks'] ?? collect())->get($person->employee_id.'|'.$date))
                        @if($dayMark && $dayMark->status === 'official_business')
                            <td class="sg-ob" title="{{ $dayMark->notes }}">OB</td>
                        @elseif($dayMark)
                            <td class="sg-susp" title="Suspension">S</td>
                        @elseif($leave)
                            @php($isPaid = $leavePaid($leave))
                            @php($isPaid ? $paidDays++ : $unpaidDays++)
                            <td class="{{ $isPaid ? 'sg-leave' : 'sg-leave-unpaid' }}" title="{{ ucwords(str_replace('_', ' ', (string) $leave->leave_type)) }} - {{ $isPaid ? 'paid' : 'unpaid' }}">{{ $isPaid ? 'PAID' : 'UNPAID' }}</td>
                        @elseif($mark ? $mark->rest_day : WorkWeek::restsOn($person->rest_days, $day))
                            <td class="sg-rest" title="Rest day">RD</td>
                        @elseif($mark && $mark->starts_at)
                            <td class="{{ $day->isWeekend() ? 'sg-weekend' : '' }}" title="{{ $shiftTitle($mark->starts_at, $mark->ends_at) }}">{{ $shiftLabel($mark->starts_at, $mark->ends_at) }}</td>
                        @else
                            <td class="{{ $day->isWeekend() ? 'sg-weekend' : '' }} {{ $person->shift_start ? '' : 'sg-none' }}" title="{{ $shiftTitle($person->shift_start, $person->shift_end) }}">{{ $shiftLabel($person->shift_start, $person->shift_end) }}</td>
                        @endif
                    @endfor
                    <td class="sg-leave-total">
                        @if($paidDays)<span class="sg-count sg-leave">{{ $paidDays }} paid</span>@endif
                        @if($unpaidDays)<span class="sg-count sg-leave-unpaid">{{ $unpaidDays }} unpaid</span>@endif
                        @if(! $paidDays && ! $unpaidDays)<span class="sg-none">—</span>@endif
                    </td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="{{ $periodStart->diffInDays($periodEnd) + 2 }}" class="muted">Nobody in this department.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
<p class="muted sg-legend"><span class="sg-rest">RD</span> rest day <span class="sg-leave">PAID</span> paid leave <span class="sg-leave-unpaid">UNPAID</span> unpaid leave <span class="sg-susp">S</span> suspension <span class="sg-ob">OB</span> official business <span class="sg-none">—</span> no shift set yet</p>
</div>

{{-- What actually happened: each day's first in and final out from the scanner,
     in red when it was late or short against that day's shift. --}}
@php($worked = \Illuminate\Support\Facades\DB::table('hr_attendance')->whereIn('employee_id', $shiftStaff->pluck('employee_id'))->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])->get()->keyBy(fn ($a) => $a->employee_id.'|'.substr((string) $a->date, 0, 10)))
@php($clock = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('g:i') : '?')
@php($lateTotals = [])
<div class="card scroll">
    <h2>Worked · from the scanner · {{ $extra['period']->label() }}</h2>
    <p class="muted">Each day's first in and final out. <span style="color:#dc2626;font-weight:600">Red</span>: late or left early against that day's shift.</p>
    <div class="shift-grid-wrap">
    <table class="shift-grid worked-grid">
        <thead>
            <tr>
                <th class="sg-name">Name</th>
                @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                    <th class="{{ $day->isWeekend() ? 'sg-weekend' : '' }} {{ $day->isToday() ? 'sg-today' : '' }}" title="{{ $day->format('l, M j, Y') }}"><span>{{ strtoupper(substr($day->format('D'), 0, 2)) }}</span>{{ $day->day }}</th>
                @endfor
                <th class="sg-leave-total">Late</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shiftStaff->groupBy(fn ($p) => $p->department_name ?: 'No department')->sortKeys() as $department => $people)
                @if(! $pickedDepartment && $shiftStaff->pluck('department_name')->unique()->count() > 1)
                    <tr class="sg-dept"><td colspan="{{ $periodStart->diffInDays($periodEnd) + 3 }}"><span>{{ $department }}</span></td></tr>
                @endif
                @foreach($people as $person)
                    <tr>
                        <td class="sg-name">{{ $person->full_name }}</td>
                        @for($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay())
                            @php($date = $day->toDateString())
                            @php($a = $worked[$person->employee_id.'|'.$date] ?? null)
                            @php($leave = $extra['leaves']->get($date, collect())->first(fn ($l) => $l->employee_id == $person->employee_id && $l->status === 'approved'))
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
                                @php($cellClass = ! $effectiveOut ? 'wk-missing' : ($late || $short ? 'wk-alert' : 'wk-ok'))
                                <td class="wk-cell {{ $cellClass }}" title="In {{ $clock($a->time_in) }}, out {{ $effectiveOut ? $clock($effectiveOut).($usedFallbackOut ? ' last punch' : '') : 'no out' }}">
                                    <span class="{{ $late ? 'wk-off' : '' }}">{{ $clock($a->time_in) }}</span>@if($late)<span class="wk-min">{{ $lateMin }}m late</span>@endif<br>
                                    <span class="{{ $short || ! $effectiveOut ? 'wk-off' : '' }}">{{ $effectiveOut ? $clock($effectiveOut) : 'no out' }}</span>@if($usedFallbackOut)<span class="wk-min">last punch</span>@endif @if($short)<span class="wk-min">{{ $missingFinalOut ? 'undertime' : $shortMin.'m early' }}</span>@endif
                                </td>
                            @elseif($leave)
                                @php($isPaid = $leavePaid($leave))
                                <td class="{{ $isPaid ? 'sg-leave' : 'sg-leave-unpaid' }}" title="{{ ucwords(str_replace('_', ' ', (string) $leave->leave_type)) }} - {{ $isPaid ? 'paid' : 'unpaid' }}">{{ $isPaid ? 'PAID' : 'UNPAID' }}</td>
                            @elseif($a && $a->status === 'official_business')
                                <td class="sg-ob">OB</td>
                            @elseif($a && $a->notes === 'Suspension')
                                <td class="sg-susp">S</td>
                            @elseif($plan['rest'])
                                <td class="wk-rest" title="Rest day">RD</td>
                            @elseif($day->lt(\Carbon\Carbon::today()) && ! $plan['rest'] && ! $leave)
                                <td class="wk-absent" title="Absent">ABS</td>
                            @else
                                <td class="sg-none">{{ $day->lt(\Carbon\Carbon::today()) ? '—' : '' }}</td>
                            @endif
                        @endfor
                        <td class="sg-leave-total">@if($t = $lateTotals[$person->employee_id] ?? null)<span class="wk-off">{{ $t[0] }}× · {{ $t[1] }}m</span>@else<span class="sg-none">—</span>@endif</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    </div>
</div>
<style>
    .people .worked-grid .wk-cell { font-size:10px; line-height:1.25; white-space:nowrap; border-left-width:3px; }
    .people .worked-grid .wk-ok { background:#ecfdf5; border-left-color:#22c55e; color:#14532d; }
    .people .worked-grid .wk-alert { background:#fff7ed; border-left-color:#f97316; color:#7c2d12; }
    .people .worked-grid .wk-missing { background:#fef2f2; border-left-color:#ef4444; color:#7f1d1d; }
    .people .worked-grid .wk-absent { background:#dc2626; color:#fff; font-weight:800; font-size:10px; letter-spacing:.04em; }
    .people .worked-grid .wk-rest { background:#dc2626; color:#fff; font-weight:800; font-size:10px; letter-spacing:.04em; }
    .people .worked-grid .wk-off { color:#dc2626; font-weight:700; }
    .people .worked-grid .wk-min { display:block; font-size:9px; color:#dc2626; }
    .people .worked-grid tbody tr:hover .wk-ok { background:#dcfce7; }
    .people .worked-grid tbody tr:hover .wk-alert { background:#ffedd5; }
    .people .worked-grid tbody tr:hover .wk-missing { background:#fee2e2; }
    .people .worked-grid tbody tr:hover .wk-absent { background:#b91c1c; }
    .people .worked-grid tbody tr:hover .wk-rest { background:#b91c1c; }
</style>
