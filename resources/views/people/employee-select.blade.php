@if ($hr)
    <label class="people-field"><span>Employee</span><select name="employee_id" required><option value="">Choose an employee</option>
        @foreach($employees as $employee)<option value="{{ $employee->employee_id }}" @selected(old('employee_id') == $employee->employee_id)>{{ $employee->full_name }}</option>@endforeach
    </select></label>
@endif
