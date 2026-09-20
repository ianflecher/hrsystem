@use('App\Support\WorkWeek')
@if($hr)
    <details class="card" @if($errors->any()) open @endif><summary>Assign a shift</summary>
        <form method="POST" action="{{ $base }}" class="grid divider">@csrf
            <label class="people-field"><span>Employee</span>
                <select name="employee_id" required>
                    @foreach($employees as $person)<option value="{{ $person->employee_id }}">{{ $person->full_name }}</option>@endforeach
                </select>
            </label>
            <x-people.field name="from" label="From" type="date" />
            <x-people.field name="to" label="To" type="date" />
            <x-people.field name="starts_at" label="Starts" type="time" :required="false" />
            <x-people.field name="ends_at" label="Ends" type="time" :required="false" />
            <x-people.field name="label" label="Label" maxlength="80" :required="false" placeholder="Morning shift" />
            <label class="people-field"><span>Rest day</span><label class="row"><input type="checkbox" name="rest_day" value="1"> Mark as rest day</label></label>
            <p class="muted wide">Assignments override the employee's default shift for attendance and payroll on those dates.</p>
            <div><button>Save assignment</button></div>
        </form>
    </details>

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

<form method="GET" class="row card">
    <label>Month <input type="month" name="month" value="{{ $month->format('Y-m') }}" required></label>
    <button>View calendar</button>
</form>

<div class="card scroll"><div class="calendar">
    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<strong>{{ $day }}</strong>@endforeach
    @for($blank = 1; $blank < $month->dayOfWeekIso; $blank++)<div></div>@endfor

    @for($d = 1; $d <= $month->daysInMonth; $d++)
        @php($day = $month->copy()->day($d))
        @php($date = $day->toDateString())
        @php($holiday = $extra['holidays']->get($date))
        @php($assigned = $extra['assignments']->get($date, collect()))
        @php($working = $extra['staff']->reject(fn ($person) => WorkWeek::restsOn($person->rest_days, $day)))

        <div class="day">
            <strong>{{ $d }}</strong>

            @if($holiday)
                <div class="shift rest">
                    <strong>{{ $holiday->name }}</strong><br>
                    {{ \App\Services\HolidayPay::describe($holiday->classification ?? $holiday->type) }}
                    @if($hr)
                        <form method="POST" action="{{ $base }}/{{ $holiday->id }}">@csrf
                            <button class="secondary" name="action" value="delete-holiday" aria-label="Remove {{ $holiday->name }} on {{ $date }}">Remove</button>
                        </form>
                    @endif
                </div>
            @elseif($assigned->isNotEmpty())
                @foreach($assigned as $shift)
                    <div class="shift {{ $shift->rest_day ? 'rest' : '' }}">
                        <strong>{{ $shift->full_name }}</strong><br>
                        @if($shift->rest_day)
                            Rest day
                        @else
                            {{ substr($shift->starts_at, 0, 5) }}–{{ substr($shift->ends_at, 0, 5) }}
                        @endif
                        @if($shift->label)<br>{{ $shift->label }}@endif
                        @if($hr)
                            <form method="POST" action="{{ $base }}/{{ $shift->id }}">@csrf
                                <button class="secondary" name="action" value="delete" aria-label="Remove assigned shift on {{ $date }}">Remove</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            @elseif($hr)
                <div class="shift">{{ $working->count() }} default working · {{ $extra['staff']->count() - $working->count() }} resting</div>
            @elseif($working->isEmpty())
                <div class="shift rest">Rest day</div>
            @else
                @php($me = $extra['staff']->first())
                <div class="shift">
                    @if($me->shift_start)
                        {{ substr($me->shift_start, 0, 5) }}@if($me->shift_end)–{{ substr($me->shift_end, 0, 5) }}@endif
                    @else
                        Working
                    @endif
                </div>
            @endif
        </div>
    @endfor
</div></div>

@if($hr)
    <article class="card">
        <h2>Rest days</h2>
        <p class="muted">Set on each person under Employees. Nothing here needs entering per date.</p>
        <div class="scroll"><table>
            <thead><tr><th>Employee</th><th>Shift</th><th>Rest days</th></tr></thead>
            <tbody>
                @forelse($extra['staff'] as $person)
                    <tr>
                        <td>{{ $person->full_name }}</td>
                        <td>{{ $person->shift_start ? substr($person->shift_start, 0, 5).($person->shift_end ? '–'.substr($person->shift_end, 0, 5) : '') : '—' }}</td>
                        <td>{{ WorkWeek::label($person->rest_days) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No active employees.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </article>
@else
    @php($me = $extra['staff']->first())
    <p class="muted">Your rest days: {{ $me ? WorkWeek::label($me->rest_days) : 'None set' }}. HR sets these, along with your shift times.</p>
@endif
