<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.humanresource')] #[Title('Employee 360')] class extends Component
{
    public object $employee;
    public array $stats = [];
    public function mount(int $employeeId): void
    {
        \App\Support\PeopleAccess::hr();
        $this->employee = DB::table('employees as e')->join('users as u','e.user_id','=','u.user_id')
            ->leftJoin('departments as d','e.department_id','=','d.department_id')
            ->where('e.employee_id',$employeeId)->select('e.*','u.full_name','u.username','u.email','u.role','d.department_name')->firstOrFail();
        $this->stats=[
            'attendance'=>DB::table('hr_attendance')->where('employee_id',$employeeId)->count(),
            'leave'=>DB::table('leaves')->where('employee_id',$employeeId)->count(),
            'overtime'=>DB::table('overtime_requests')->where('employee_id',$employeeId)->count(),
            'payroll'=>DB::table('hr_payroll')->where('employee_id',$employeeId)->count(),
            'documents'=>DB::table('employee_documents')->where('employee_id',$employeeId)->count(),
            'loans'=>DB::table('employee_loans')->where('employee_id',$employeeId)->count(),
        ];
    }
    public function getPayrollProperty(){ return DB::table('hr_payroll')->where('employee_id',$this->employee->employee_id)->orderByDesc('period_end')->limit(8)->get(); }
    public function getAttendanceProperty(){ return DB::table('hr_attendance')->where('employee_id',$this->employee->employee_id)->orderByDesc('date')->limit(10)->get(); }
    public function getDocumentsProperty(){ return DB::table('employee_documents')->where('employee_id',$this->employee->employee_id)->orderByDesc('created_at')->limit(10)->get(); }
}
?>
<div class="p-6 md:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div><a href="{{ route('hr.employees') }}" class="text-sm text-gray-500">← Employees</a><h1 class="text-2xl font-bold mt-1">{{ $employee->full_name }}</h1><p class="text-gray-600">{{ $employee->job_title }} · {{ $employee->department_name ?: 'No department' }}</p></div>
        <a href="{{ route('hr.employees') }}" class="btn-secondary">Edit employee</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        @foreach($stats as $label=>$value)<div class="bg-white rounded-xl border p-4"><div class="text-xs uppercase text-gray-500">{{ $label }}</div><div class="text-2xl font-bold mt-1">{{ $value }}</div></div>@endforeach
    </div>
    <div class="grid lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border p-5 lg:col-span-2"><h2 class="font-semibold mb-4">Employment profile</h2><div class="grid md:grid-cols-2 gap-4 text-sm"><div><span class="text-gray-500">Email</span><div>{{ $employee->email }}</div></div><div><span class="text-gray-500">Status</span><div>{{ ucfirst(str_replace('_',' ',$employee->status)) }}</div></div><div><span class="text-gray-500">Hire date</span><div>{{ $employee->hire_date }}</div></div><div><span class="text-gray-500">Pay basis</span><div>{{ ucfirst($employee->pay_basis ?? 'monthly') }}</div></div><div><span class="text-gray-500">Base salary</span><div>₱{{ number_format((float)$employee->salary,2) }}</div></div><div><span class="text-gray-500">Daily/hourly rate</span><div>{{ $employee->daily_rate ? '₱'.number_format($employee->daily_rate,2) : '—' }}</div></div><div><span class="text-gray-500">Region</span><div>{{ $employee->work_region ?: 'Not configured' }}</div></div><div><span class="text-gray-500">Wage order</span><div>{{ $employee->wage_order_code ?: 'Not configured' }}</div></div></div></div>
        <div class="bg-white rounded-xl border p-5"><h2 class="font-semibold mb-4">Government IDs</h2><dl class="space-y-3 text-sm">@foreach(app(\App\Services\PhilippinePayrollCompliance::class)->employeeGovernmentIds($employee) as $k=>$v)<div class="flex justify-between gap-3"><dt class="text-gray-500">{{ strtoupper($k) }}</dt><dd>{{ $v ?: 'Not recorded' }}</dd></div>@endforeach</dl></div>
    </div>
    <div class="grid lg:grid-cols-2 gap-6 mt-6">
      <div class="bg-white rounded-xl border p-5"><h2 class="font-semibold mb-4">Recent payroll</h2><div class="overflow-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-2">Period</th><th>Status</th><th>Gross</th><th>Net</th></tr></thead><tbody>@forelse($this->payroll as $p)<tr class="border-t"><td class="py-2">{{ $p->period_start }} — {{ $p->period_end }}</td><td>{{ ucfirst($p->status) }}</td><td>₱{{ number_format($p->gross_pay,2) }}</td><td>₱{{ number_format($p->net_pay,2) }}</td></tr>@empty<tr><td colspan="4" class="py-4 text-gray-500">No payroll records.</td></tr>@endforelse</tbody></table></div></div>
      <div class="bg-white rounded-xl border p-5"><h2 class="font-semibold mb-4">Recent attendance</h2><div class="overflow-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-2">Date</th><th>Status</th><th>In</th><th>Out</th></tr></thead><tbody>@foreach($this->attendance as $a)<tr class="border-t"><td class="py-2">{{ $a->date }}</td><td>{{ ucfirst($a->status) }}</td><td>{{ $a->time_in ?: '—' }}</td><td>{{ $a->time_out ?: '—' }}</td></tr>@endforeach</tbody></table></div></div>
    </div>
</div>
