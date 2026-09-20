@if($hr)
<article class="card">
    <h2>CSV reports</h2>
    <form method="GET" action="{{ route('people.reports.download') }}" class="grid divider">
        <label class="people-field"><span>Report</span>
            <select name="report" required>
                <option value="headcount">Headcount</option>
                <option value="attendance">Attendance</option>
                <option value="leave">Leave</option>
                <option value="payroll">Payroll</option>
            </select>
        </label>
        <x-people.field name="from" label="From" type="date" :required="false" />
        <x-people.field name="to" label="To" type="date" :required="false" />
        <label class="people-field"><span>Department</span>
            <select name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)<option value="{{ $department->department_id }}">{{ $department->department_name }}</option>@endforeach
            </select>
        </label>
        <p class="muted wide">Headcount uses hire date. Attendance uses work date. Leave includes requests that overlap the range. Payroll uses cutoff start.</p>
        <div><button>Download CSV</button></div>
    </form>
</article>
@else
    <div class="card muted">Reports are available to HR.</div>
@endif
