<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\Tardiness;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] class extends Component
{
    public $employee;
    public $attendanceStats = [];
    public $pendingLeave = [];
    public $upcomingLeave = [];
    public $leaveBalance = 0;
    public $payrollInfo = [];
    
    public function mount()
    {
        // Get current logged in employee
        $user = Auth::user();
        $this->employee = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->where('employees.user_id', $user->user_id)
            ->select(
                'employees.*',
                'users.full_name',
                'users.email',
                'users.role',
                'departments.department_name'
            )
            ->first();

        // An account with no employee record - HR and admin have none - used to
        // fall straight through to the queries below and read employee_id off
        // null. The portal is not theirs, so it is refused the same way
        // /employee/self-service already refuses it.
        abort_unless($this->employee, 403, 'An employee record is required.');

        $this->loadDashboardData();
    }
    
    public function loadDashboardData()
    {
        $this->loadAttendanceStats();
        $this->loadPendingLeave();
        $this->loadUpcomingLeave();
        $this->loadPayrollInfo();
    }
    
    private function loadAttendanceStats()
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        
        $this->attendanceStats = [
            'today' => DB::table('hr_attendance')
                ->where('employee_id', $this->employee->employee_id)
                ->whereDate('date', $today)
                // All six, not just the two: the button decides which punch
                // to make from what is already recorded, and columns it cannot
                // see read as empty - so it offered the same punch forever.
                ->select('status', ...array_keys(\App\Support\WorkDay::PUNCHES))
                ->first(),
            
            'month_stats' => DB::table('hr_attendance')
                ->where('employee_id', $this->employee->employee_id)
                ->whereBetween('date', [$startOfMonth, $today])
                ->selectRaw('
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = "on_leave" THEN 1 ELSE 0 END) as leave_days
                ')
                ->first(),
            
            'late_count' => DB::table('hr_attendance')
                ->where('employee_id', $this->employee->employee_id)
                ->where('status', 'late')
                ->whereMonth('date', $today->month)
                ->count()
        ];
    }
    
    private function loadPendingLeave()
    {
        $this->pendingLeave = DB::table('leaves')
            ->where('employee_id', $this->employee->employee_id)
            ->whereIn('status', ['pending', 'pending_hr'])
            ->select('leave_id', 'leave_type', 'start_date', 'end_date', 'total_days', 'reason', 'status', 'created_at')
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get();
    }

    private function loadUpcomingLeave()
    {
        $today = Carbon::today();

        $this->upcomingLeave = DB::table('leaves')
            ->where('employee_id', $this->employee->employee_id)
            ->where('status', 'approved')
            ->where('start_date', '>=', $today)
            ->select('leave_id', 'leave_type', 'start_date', 'end_date', 'total_days')
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get();
    }

    private function loadPayrollInfo()
    {
        $this->payrollInfo = DB::table('hr_payroll')
            ->where('employee_id', $this->employee->employee_id)
            ->where('status', 'paid')
            ->orderBy('period_end', 'desc')
            ->select('period_start', 'period_end', 'gross_pay', 'deductions', 'net_pay')
            ->first();
    }
    



    
    
}
?>

<div class="p-6 md:p-8 space-y-8">
    @php
        $todayRow = $attendanceStats['today'] ?? null;
        $todayStatus = $todayRow->status ?? 'not_recorded';
        $statusLabel = str_replace('_', ' ', ucfirst($todayStatus));
        $statusTone = match ($todayStatus) {
            'present' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'late' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'absent' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'on_leave' => 'bg-blue-50 text-blue-700 ring-blue-200',
            default => 'bg-slate-50 text-slate-600 ring-slate-200',
        };
        $month = $attendanceStats['month_stats'] ?? null;
        $firstPunch = $todayRow?->time_in ? \Carbon\Carbon::parse($todayRow->time_in)->format('h:i A') : '—';
        $lastPunch = $todayRow?->time_out ? \Carbon\Carbon::parse($todayRow->time_out)->format('h:i A') : '—';
    @endphp

    <!-- Welcome Section -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-600">Employee Portal</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-950 md:text-3xl">
                    Welcome back, {{ $employee->full_name ?? 'Employee' }}
                </h1>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
                    {{ $employee->department_name ?: 'No department assigned' }}
                    @if($employee->job_title)
                        <span class="text-slate-300">•</span>
                        <span class="font-semibold text-slate-700">{{ $employee->job_title }}</span>
                    @endif
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <p class="text-slate-500">Today</p>
                <p class="font-semibold text-slate-950">{{ now()->format('l, F j, Y') }}</p>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        <!-- Attendance Card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-500">Today's Status</p>
                    <span class="mt-3 inline-flex rounded-full px-3 py-1 text-sm font-bold capitalize ring-1 {{ $statusTone }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100">
                    <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            {{-- Read-only. Punches come from the scanner; a button here let
                 people record a time they were not physically at the
                 scanner for. --}}
            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs font-medium text-slate-500">First in</p>
                    <p class="mt-1 font-bold text-slate-950">{{ $firstPunch }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs font-medium text-slate-500">Final out</p>
                    <p class="mt-1 font-bold text-slate-950">{{ $lastPunch }}</p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-x-3 gap-y-2 border-t border-slate-100 pt-4 text-xs">
                @foreach (\App\Support\WorkDay::PUNCHES as $column => $label)
                    <div class="text-slate-500">{{ $label }}</div>
                    <div class="col-span-2 font-mono font-semibold text-slate-900">
                        {{ ($todayRow->{$column} ?? null) ? \Carbon\Carbon::parse($todayRow->{$column})->format('H:i') : '—' }}
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Monthly Attendance -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Monthly Attendance</p>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-emerald-50 p-3">
                    <p class="text-2xl font-bold text-emerald-700">{{ $month->present_days ?? 0 }}</p>
                    <p class="text-xs font-medium text-emerald-700/80">Present</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-3">
                    <p class="text-2xl font-bold text-amber-700">{{ $month->late_days ?? 0 }}</p>
                    <p class="text-xs font-medium text-amber-700/80">Late</p>
                </div>
                <div class="rounded-xl bg-rose-50 p-3">
                    <p class="text-2xl font-bold text-rose-700">{{ $month->absent_days ?? 0 }}</p>
                    <p class="text-xs font-medium text-rose-700/80">Absent</p>
                </div>
                <div class="rounded-xl bg-blue-50 p-3">
                    <p class="text-2xl font-bold text-blue-700">{{ $month->leave_days ?? 0 }}</p>
                    <p class="text-xs font-medium text-blue-700/80">On Leave</p>
                </div>
            </div>
        </div>

        <!-- Tasks -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Pending Leave</p>
                    <p class="mt-3 text-4xl font-bold text-slate-950">
                        {{ count($pendingLeave) }}
                    </p>
                    <p class="mt-2 text-sm text-slate-500">Request{{ count($pendingLeave) === 1 ? '' : 's' }} awaiting review</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Latest Pay -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Last Payment</p>
                    <p class="mt-3 text-3xl font-bold text-slate-950">
                        ₱{{ number_format($payrollInfo->net_pay ?? 0, 2) }}
                    </p>
                    @if($payrollInfo)
                        <p class="mt-2 text-sm text-slate-500">
                            {{ \Carbon\Carbon::parse($payrollInfo->period_end)->format('M d, Y') }}
                        </p>
                    @else
                        <p class="mt-2 text-sm text-slate-500">No paid payroll yet</p>
                    @endif
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100">
                    <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Tasks Section -->
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-950">My Leave Requests</h2>
                    <a href="{{ route('employee.leave') }}" class="rounded-lg px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50">
                        File a request
                    </a>
                </div>
                <div class="p-6">
                    @if(count($pendingLeave) > 0)
                        <div class="space-y-4">
                            @foreach($pendingLeave as $leave)
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-semibold text-slate-950">
                                                {{ str_replace('_', ' ', ucfirst($leave->leave_type)) }} Leave
                                            </h3>
                                            <p class="mt-1 text-sm text-slate-500">{{ $leave->reason }}</p>
                                            <div class="flex items-center mt-2 space-x-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Pending
                                                </span>
                                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ \Carbon\Carbon::parse($leave->start_date)->format('M d') }}
                                                    &ndash;
                                                    {{ \Carbon\Carbon::parse($leave->end_date)->format('M d') }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $leave->total_days }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">day{{ $leave->total_days == 1 ? '' : 's' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex min-h-32 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-center">
                            <p class="font-semibold text-slate-700">No pending leave requests</p>
                            <p class="mt-1 text-sm text-slate-500">Submitted leaves will appear here while they wait for approval.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Upcoming Approved Leave -->
            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-950">Upcoming Approved Leave</h2>
                </div>
                <div class="p-6">
                    @if(count($upcomingLeave) > 0)
                        <div class="space-y-3">
                            @foreach($upcomingLeave as $leave)
                                <div class="flex items-center justify-between rounded-xl p-3 hover:bg-slate-50">
                                    <div class="flex items-center">
                                        <div class="rounded-lg bg-slate-100 p-2">
                                            <svg class="h-5 w-5 text-slate-600"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                {{ str_replace('_', ' ', ucfirst($leave->leave_type)) }} Leave
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $leave->total_days }} day{{ $leave->total_days == 1 ? '' : 's' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($leave->start_date)->format('M d') }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($leave->start_date)->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex min-h-32 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-center">
                            <p class="font-semibold text-slate-700">No upcoming approved leave</p>
                            <p class="mt-1 text-sm text-slate-500">Approved future leave will appear here.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Links -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-950">Quick Links</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('employee.attendance') }}" 
                           class="flex flex-col items-center justify-center rounded-xl border border-slate-200 p-4 text-center hover:border-red-200 hover:bg-red-50">
                            <svg class="mb-2 h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-bold text-slate-700">Attendance</span>
                        </a>
                        <a href="{{ route('employee.leave') }}" 
                           class="flex flex-col items-center justify-center rounded-xl border border-slate-200 p-4 text-center hover:border-red-200 hover:bg-red-50">
                            <svg class="mb-2 h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm font-bold text-slate-700">Tasks</span>
                        </a>
                        <a href="{{ route('employee.payroll') }}" 
                           class="flex flex-col items-center justify-center rounded-xl border border-slate-200 p-4 text-center hover:border-red-200 hover:bg-red-50">
                            <svg class="mb-2 h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-bold text-slate-700">Payroll</span>
                        </a>
                        <a href="{{ route('employee.leave') }}" 
                           class="flex flex-col items-center justify-center rounded-xl border border-slate-200 p-4 text-center hover:border-red-200 hover:bg-red-50">
                            <svg class="mb-2 h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm font-bold text-slate-700">Leave</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Time Tracking -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-950">Today's Time</h2>
                </div>
                <div class="p-6">
                    @if($attendanceStats['today'] && $attendanceStats['today']->time_in)
                        <div class="text-center">
                            <div class="mb-4 grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-slate-50 p-3 text-center">
                                    <p class="text-sm text-slate-500">Clock In</p>
                                    <p class="text-lg font-bold text-slate-950">
                                        {{ \Carbon\Carbon::parse($attendanceStats['today']->time_in)->format('h:i A') }}
                                    </p>
                                </div>
                                @if($attendanceStats['today']->time_out)
                                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                                        <p class="text-sm text-slate-500">Clock Out</p>
                                        <p class="text-lg font-bold text-slate-950">
                                            {{ \Carbon\Carbon::parse($attendanceStats['today']->time_out)->format('h:i A') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 py-8 text-center">
                            <p class="font-semibold text-slate-700">Not clocked in yet</p>
                            <p class="mt-1 text-sm text-slate-500">Scanner punches will appear here.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if(session()->has('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Toastify({
                text: "{{ session('success') }}",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#E31B23",
            }).showToast();
        });
    </script>
@endif

@if(session()->has('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Toastify({
                text: "{{ session('error') }}",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#EF4444",
            }).showToast();
        });
    </script>
@endif
