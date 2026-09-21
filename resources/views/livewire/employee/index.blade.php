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
                ->select('status', 'time_in', 'time_out')
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
            ->where('status', 'pending')
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
    
    public function clockIn()
    {
        $today = Carbon::today();
        
        // Check if already clocked in
        $existing = DB::table('hr_attendance')
            ->where('employee_id', $this->employee->employee_id)
            ->whereDate('date', $today)
            ->first();
        
        if ($existing && $existing->time_in) {
            session()->flash('error', 'You have already clocked in today!');
            return;
        }
        
        DB::table('hr_attendance')->updateOrInsert(
            [
                'employee_id' => $this->employee->employee_id,
                'date' => $today
            ],
            [
                'time_in' => now(),
                // Measured against this employee's own shift with the five
                // minute grace. The old rule only bit from 10:00, so a 9:45
                // arrival was recorded as on time.
                'status' => Tardiness::isLate(now(), $this->employee->shift_start ?? null) ? 'late' : 'present',
                'updated_at' => now()
            ]
        );
        
        session()->flash('success', 'Clocked in successfully!');
        $this->loadAttendanceStats();
    }
    
    public function clockOut()
    {
        $today = Carbon::today();
        
        // For overnight shifts, the open attendance row belongs to the
        // shift's start date, so do not restrict clock-out to today's date.
        $attendance = DB::table('hr_attendance')
            ->where('employee_id', $this->employee->employee_id)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->orderByDesc('date')
            ->first();
        
        if (!$attendance || !$attendance->time_in) {
            session()->flash('error', 'You need to clock in first!');
            return;
        }
        
        if ($attendance->time_out) {
            session()->flash('error', 'You have already clocked out today!');
            return;
        }
        
        DB::table('hr_attendance')
            ->where('employee_id', $this->employee->employee_id)
            ->whereDate('date', $today)
            ->update([
                'time_out' => now(),
                'updated_at' => now()
            ]);
        
        session()->flash('success', 'Clocked out successfully!');
        $this->loadAttendanceStats();
    }
    
}
?>

<div class="p-6 md:p-8">
    <!-- Welcome Section -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Welcome back, {{ $employee->full_name ?? 'Employee' }}!
        </h1>
        <p class="text-gray-600 dark:text-gray-400">
            {{ $employee->department_name ? "Department: {$employee->department_name}" : '' }}
            • {{ $employee->job_title ?? '' }}
        </p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Attendance Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today's Status</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ $attendanceStats['today']->status ?? 'Not Recorded' }}
                    </p>
                </div>
                <div class="p-3 bg-slate-100 rounded-lg">
                    <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex space-x-2">
                @if(!$attendanceStats['today'] || !$attendanceStats['today']->time_in)
                    <button wire:click="clockIn" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700">
                        Clock In
                    </button>
                @endif
                @if($attendanceStats['today'] && $attendanceStats['today']->time_in && !$attendanceStats['today']->time_out)
                    <button wire:click="clockOut" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700">
                        Clock Out
                    </button>
                @endif
            </div>
        </div>

        <!-- Monthly Attendance -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Monthly Attendance</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->present_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Present</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->late_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Late</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->absent_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Absent</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->leave_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">On Leave</p>
                </div>
            </div>
        </div>

        <!-- Tasks -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Leave</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ count($pendingLeave) }}
                    </p>
                </div>
                <div class="p-3 bg-slate-100 dark:bg-slate-800 rounded-lg">
                    <svg class="w-6 h-6 text-slate-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Latest Pay -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Last Payment</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        ₱{{ number_format($payrollInfo->net_pay ?? 0, 2) }}
                    </p>
                    @if($payrollInfo)
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($payrollInfo->period_end)->format('M d, Y') }}
                        </p>
                    @endif
                </div>
                <div class="p-3 bg-slate-100 rounded-lg">
                    <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tasks Section -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">My Leave Requests</h2>
                    <a href="{{ route('employee.leave') }}" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">
                        File a request
                    </a>
                </div>
                <div class="p-6">
                    @if(count($pendingLeave) > 0)
                        <div class="space-y-4">
                            @foreach($pendingLeave as $leave)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium text-gray-900 dark:text-white">
                                                {{ str_replace('_', ' ', ucfirst($leave->leave_type)) }} Leave
                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $leave->reason }}</p>
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
                        <p class="text-center text-gray-600 dark:text-gray-400 py-8">No pending leave requests</p>
                    @endif
                </div>
            </div>

            <!-- Upcoming Approved Leave -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mt-6">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upcoming Approved Leave</h2>
                </div>
                <div class="p-6">
                    @if(count($upcomingLeave) > 0)
                        <div class="space-y-3">
                            @foreach($upcomingLeave as $leave)
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-lg bg-slate-100">
                                            <svg class="w-5 h-5 text-slate-600"
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
                        <p class="text-center text-gray-600 dark:text-gray-400 py-8">No upcoming approved leave</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Links -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Links</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('employee.attendance') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Attendance</span>
                        </a>
                        <a href="{{ route('employee.leave') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tasks</span>
                        </a>
                        <a href="{{ route('employee.payroll') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Payroll</span>
                        </a>
                        <a href="{{ route('employee.leave') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Leave</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Time Tracking -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Time</h2>
                </div>
                <div class="p-6">
                    @if($attendanceStats['today'] && $attendanceStats['today']->time_in)
                        <div class="text-center">
                            <div class="flex justify-center items-center space-x-4 mb-4">
                                <div class="text-center">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Clock In</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($attendanceStats['today']->time_in)->format('h:i A') }}
                                    </p>
                                </div>
                                @if($attendanceStats['today']->time_out)
                                    <div class="text-center">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Clock Out</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($attendanceStats['today']->time_out)->format('h:i A') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                            @if($attendanceStats['today']->time_in && $attendanceStats['today']->time_out)
                                @php
                                    $totalMinutes = \Carbon\Carbon::parse($attendanceStats['today']->time_out)
                                        ->diffInMinutes(\Carbon\Carbon::parse($attendanceStats['today']->time_in));
                                    $hours = floor($totalMinutes / 60);
                                    $minutes = $totalMinutes % 60;
                                @endphp
                                <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Hours Today</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $hours }}h {{ $minutes }}m</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-center text-gray-600 dark:text-gray-400 py-4">Not clocked in yet</p>
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