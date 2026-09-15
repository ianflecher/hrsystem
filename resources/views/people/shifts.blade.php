@use('App\Support\WorkWeek')
@if($hr)
    {{-- The only thing entered here. Shifts and rest days belong to the
         employee and are set on the Employees screen; holidays belong to the
         company, so they are the input and the calendar follows from them. --}}
    <details class="card" @if($errors->any()) open @endif><summary>Add a holiday</summary>
        <form method="POST" action="{{ $base }}" class="grid divider">@csrf
            <x-people.field name="date" label="Date" type="date" />
            <x-people.field name="name" label="Holiday" maxlength="120" placeholder="Independence Day" />
            <label class="people-field"><span>Type</span>
                <select name="type">
                    <option value="regular">Regular holiday</option>
                    <option value="special">Special non-working day</option>
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
        @php($working = $extra['staff']->reject(fn ($person) => WorkWeek::restsOn($person->rest_days, $day)))

        <div class="day">
            <strong>{{ $d }}</strong>

            @if($holiday)
                <div class="shift rest">
                    <strong>{{ $holiday->name }}</strong><br>
                    {{ $holiday->type === 'regular' ? 'Regular holiday' : 'Special non-working day' }}
                    @if($hr)
                        <form method="POST" action="{{ $base }}/{{ $holiday->id }}">@csrf
                            <button class="secondary" name="action" value="delete" aria-label="Remove {{ $holiday->name }} on {{ $date }}">Remove</button>
                        </form>
                    @endif
                </div>
            @elseif($hr)
                <div class="shift">{{ $working->count() }} working · {{ $extra['staff']->count() - $working->count() }} resting</div>
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
