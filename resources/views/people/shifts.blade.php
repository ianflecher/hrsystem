@if($hr)
<details class="card" @if($errors->any()) open @endif><summary>Assign shifts or rest days</summary>
<form method="POST" action="{{ $base }}" class="grid divider">@csrf
    @include('people.employee-select')
    <x-people.field name="label" label="Shift name" placeholder="Morning / Evening / Rest day" maxlength="80" />
    <x-people.field name="from" label="From" type="date" :value="$month->toDateString()" />
    <x-people.field name="to" label="Through" type="date" :value="$month->copy()->endOfMonth()->toDateString()" />
    <x-people.field name="starts_at" label="Start time" type="time" :required="false" />
    <x-people.field name="ends_at" label="End time (next day if earlier)" type="time" :required="false" />
    <div class="wide row">@foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $day=>$label)<label><input type="checkbox" name="weekdays[]" value="{{ $day }}" @checked(in_array($day, old('weekdays', [1,2,3,4,5])))> {{ $label }}</label>@endforeach</div>
    <label><input type="checkbox" name="rest_day" value="1"> Rest day (no work times)</label>
    <p class="muted wide">Assignments replace the employee’s schedule on the selected dates. Repeat with a different date range or weekdays to create a rotation.</p>
    <div><button>Save schedule</button></div>
</form></details>
@endif
<form method="GET" class="row card"><label>Month <input type="month" name="month" value="{{ $month->format('Y-m') }}" required></label><button>View calendar</button></form>
<div class="card scroll"><div class="calendar">
@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<strong>{{ $day }}</strong>@endforeach
@for($blank=1; $blank<$month->dayOfWeekIso; $blank++)<div></div>@endfor
@for($d=1; $d<=$month->daysInMonth; $d++)
    @php($date = $month->copy()->day($d)->toDateString())
    <div class="day"><strong>{{ $d }}</strong>
    @foreach($extra['calendar']->get($date, collect()) as $shift)
        <div class="shift {{ $shift->rest_day ? 'rest' : '' }}"><strong>{{ $shift->full_name }}</strong><br>{{ $shift->label }}<br>{{ $shift->rest_day ? 'Rest day' : substr($shift->starts_at,0,5).'–'.substr($shift->ends_at,0,5) }}
        @if($hr)<form method="POST" action="{{ $base }}/{{ $shift->id }}">@csrf<button class="secondary" name="action" value="delete" aria-label="Remove {{ $shift->full_name }} shift on {{ $date }}">Remove</button></form>@endif</div>
    @endforeach</div>
@endfor
</div></div>
