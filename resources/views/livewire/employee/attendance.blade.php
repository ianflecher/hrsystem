<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\PayPeriod;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] #[Title('My Attendance')] class extends Component
{
    public $attendance = null;
    public $attendanceHistory = [];
    public $monthlySummary = [];
    public $employee;
    public $currentCutoff;
    public $clockStatus = [];
    public $isClockedIn = false;
    public bool $showAllAttendance = false;
    public int $cutoffWorkedMinutes = 0;
    public string $selectedCutoffStart = '';
    public array $cutoffOptions = [];

    /** A day's times from somebody the scanner cannot record yet. */
    public string $logDate = '';
    public string $logIn = '';
    public string $logOut = '';
    public string $logNote = '';

    /** No scanner ID: their days come from a time log their supervisor approves. */
    public function getNeedsTimeLogProperty(): bool
    {
        return $this->employee && trim((string) ($this->employee->biometric_id ?? '')) === '';
    }

    public function getMyTimeLogsProperty()
    {
        return DB::table('time_log_requests')->where('employee_id', $this->employee->employee_id)
            ->orderByDesc('date')->limit(10)->get();
    }

    public function submitTimeLog(): void
    {
        abort_unless($this->needsTimeLog, 403);
        $this->validate([
            'logDate' => 'required|date_format:Y-m-d|before_or_equal:today',
            'logIn' => 'required|date_format:H:i',
            'logOut' => 'nullable|date_format:H:i',
            'logNote' => 'nullable|string|max:255',
        ], ['logDate.before_or_equal' => 'You can only log today or an earlier day.'], ['logDate' => 'date', 'logIn' => 'time in', 'logOut' => 'time out']);

        $already = DB::table('time_log_requests')->where('employee_id', $this->employee->employee_id)
            ->whereDate('date', $this->logDate)->whereIn('status', ['pending', 'approved'])->exists();
        if ($already) {
            $this->addError('logDate', 'You already sent in this day.');
            return;
        }

        $in = \Carbon\Carbon::parse($this->logDate.' '.$this->logIn);
        $out = $this->logOut !== '' ? \Carbon\Carbon::parse($this->logDate.' '.$this->logOut) : null;
        if ($out && $out->lte($in)) {
            $out->addDay(); // a time-out before the time-in is the next morning
        }

        DB::table('time_log_requests')->insert([
            'employee_id' => $this->employee->employee_id, 'date' => $this->logDate,
            'time_in' => $in->toDateTimeString(), 'time_out' => $out?->toDateTimeString(),
            'note' => $this->logNote !== '' ? $this->logNote : null, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->reset('logIn', 'logOut', 'logNote');
        session()->flash('timelog', 'Sent to your supervisor. It counts once approved.');
    }

    public function mount()
    {
        // Get current employee based on logged-in user
        $user = Auth::user();
        $this->employee = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->select(
                'employees.*',
                'users.full_name',
                'users.email',
                'departments.department_name',
                'departments.department_id'
            )
            ->where('users.user_id', $user->user_id)
            ->first();

        abort_unless($this->employee, 403, 'An employee record is required.');

        $this->cutoffOptions = collect(PayPeriod::recent(6))
            ->map(fn (PayPeriod $period) => ['start' => $period->start, 'label' => $period->label()])
            ->all();
        $this->selectedCutoffStart = $this->cutoffOptions[0]['start'] ?? PayPeriod::fromStart(now()->toDateString())->start;

        $this->loadData();
    }

    public function updatedSelectedCutoffStart(): void
    {
        $valid = collect($this->cutoffOptions)->pluck('start')->contains($this->selectedCutoffStart);
        if (! $valid) {
            $this->selectedCutoffStart = $this->cutoffOptions[0]['start'] ?? PayPeriod::fromStart(now()->toDateString())->start;
        }

        $this->showAllAttendance = false;
        $this->loadData();
    }
    
    public function loadData()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        // Load today's attendance
        $this->attendance = DB::table('hr_attendance')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();
            
        // Load attendance history for the selected semi-monthly cutoff.
        $cutoff = PayPeriod::fromStart($this->selectedCutoffStart ?: now()->toDateString());

        $this->currentCutoff = $cutoff->label();
        
        $this->attendanceHistory = DB::table('hr_attendance')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$cutoff->start, $cutoff->end])
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();

        $this->cutoffWorkedMinutes = collect($this->attendanceHistory)
            ->sum(fn ($row) => \App\Support\WorkDay::workedMinutes($row));

        // Absent days have no record - nobody punched - so they are added from the
        // same count payroll uses (up to yesterday), and listed like any other day.
        $last = min($cutoff->end, now()->subDay()->toDateString());
        $deductions = $last >= $cutoff->start
            ? (new \App\Services\TimeDeductions)->forPeriod($this->employee, $cutoff->start, $last) : ['dates' => []];
        $absentDates = $deductions['dates']['absent'] ?? [];
        foreach ($absentDates as $date) {
            $this->attendanceHistory[] = (object) ['date' => $date, 'status' => 'absent', 'time_in' => null, 'lunch_in' => null,
                'lunch_out' => null, 'cb_in' => null, 'cb_out' => null, 'time_out' => null, 'notes' => 'No scan, leave or official business'];
        }
        usort($this->attendanceHistory, fn ($x, $y) => strcmp(substr((string) $y->date, 0, 10), substr((string) $x->date, 0, 10)));
            
        // Load cut-off summary
        $this->monthlySummary = DB::table('hr_attendance')
            ->select(
                DB::raw('COUNT(*) as total_days'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_days'),
                DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days'),
                DB::raw('SUM(CASE WHEN status = "half_day" THEN 1 ELSE 0 END) as half_days'),
                DB::raw('SUM(CASE WHEN status = "on_leave" THEN 1 ELSE 0 END) as leave_days')
            )
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$cutoff->start, $cutoff->end])
            ->first();
        // Absences and suspensions as payroll counts them (a suspension is saved as absent).
        if ($this->monthlySummary) {
            $suspended = collect($this->attendanceHistory)->filter(fn ($r) => ($r->notes ?? '') === 'Suspension' && ! $r->time_in)->count();
            $this->monthlySummary->absent_days = count($absentDates);
            $this->monthlySummary->suspended_days = $suspended;
            $this->monthlySummary->total_days = (int) $this->monthlySummary->total_days - $suspended + count($absentDates);
        }
            
        // Check clock status
        $this->checkClockStatus();
    }
    
    public function checkClockStatus()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        // Check if clock_logs table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'clock_logs'");
        
        if (!empty($tableExists)) {
            // Get latest clock log
            $clockLog = DB::table('clock_logs')
                ->where('employee_id', $employeeId)
                ->whereDate('date', $today)
                ->orderBy('created_at', 'desc')
                ->first();
            
            $this->isClockedIn = $clockLog ? !$clockLog->clock_out : false;
            $this->clockStatus = [
                'is_clocked_in' => $this->isClockedIn,
                'last_clock_in' => $clockLog ? $clockLog->clock_in : null,
                'last_clock_out' => $clockLog ? $clockLog->clock_out : null
            ];
        } else {
            // If clock_logs table doesn't exist, check hr_attendance
            $todayAttendance = DB::table('hr_attendance')
                ->where('employee_id', $employeeId)
                ->whereDate('date', $today)
                ->first();
            
            $this->isClockedIn = $todayAttendance && $todayAttendance->time_in && !$todayAttendance->time_out;
            $this->clockStatus = [
                'is_clocked_in' => $this->isClockedIn,
                'last_clock_in' => $todayAttendance ? $todayAttendance->time_in : null,
                'last_clock_out' => $todayAttendance ? $todayAttendance->time_out : null
            ];
        }
    }
    
    // Manual Time In (for backup)
    public function manualTimeIn()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        // Check if already timed in today
        if ($this->attendance && $this->attendance->time_in) {
            session()->flash('error', 'You have already timed in today!');
            return;
        }
        
        $currentTime = now();
        $status = 'present';
        
        // Check if late (after 9:00 AM)
        $lateThreshold = Carbon::createFromTime(9, 0, 0);
        if ($currentTime->gt($lateThreshold)) {
            $status = 'late';
        }
        
        // Create attendance record
        DB::table('hr_attendance')->insert([
            'employee_id' => $employeeId,
            'date' => $today,
            'time_in' => $currentTime,
            'status' => $status,
            'notes' => 'Manual time in from attendance page',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Also create clock log if table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'clock_logs'");
        if (!empty($tableExists)) {
            DB::table('clock_logs')->insert([
                'employee_id' => $employeeId,
                'date' => $today,
                'clock_in' => $currentTime,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->loadData();
        session()->flash('success', 'Time In recorded successfully!');
    }
    
    
    // Sync clock with attendance
    
    public function refreshData()
    {
        $this->loadData();
    }

    public function toggleAttendanceHistory()
    {
        $this->showAllAttendance = ! $this->showAllAttendance;
    }
}

?>

<div class="p-6">
    <!-- Simplified Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">My Attendance Overview</h1>
        <p class="text-gray-600">Track your daily attendance and work hours</p>
    </div>

    @if ($this->needsTimeLog)
        {{-- No scanner ID yet: the day is sent in here and counts once approved. --}}
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-amber-200">
            <h2 class="text-xl font-bold text-gray-900">Log my time</h2>
            <p class="text-sm text-gray-600 mt-1">You are not on the scanner yet. Send in your time in and time out for a day; it counts once your supervisor approves it.</p>
            @if (session('timelog'))
                <div class="mt-3 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-800">{{ session('timelog') }}</div>
            @endif
            <form wire:submit="submitTimeLog" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <label class="text-sm"><span class="mb-1 block font-medium text-gray-700">Date</span>
                    <input type="date" wire:model="logDate" max="{{ now()->toDateString() }}" class="form-input" required>
                    @error('logDate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</label>
                <label class="text-sm"><span class="mb-1 block font-medium text-gray-700">Time in</span>
                    <input type="time" wire:model="logIn" class="form-input" required>
                    @error('logIn') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</label>
                <label class="text-sm"><span class="mb-1 block font-medium text-gray-700">Time out</span>
                    <input type="time" wire:model="logOut" class="form-input">
                    @error('logOut') <span class="text-xs text-red-600">{{ $message }}</span> @enderror</label>
                <label class="text-sm"><span class="mb-1 block font-medium text-gray-700">Note (optional)</span>
                    <input type="text" wire:model="logNote" maxlength="255" class="form-input" placeholder="e.g. overtime, left early"></label>
                <button type="submit" class="btn-primary">Send to supervisor</button>
            </form>
            @if ($this->myTimeLogs->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-1 pr-4">Date</th><th class="pr-4">In</th><th class="pr-4">Out</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($this->myTimeLogs as $log)
                                <tr class="border-t border-gray-100">
                                    <td class="py-1.5 pr-4">{{ \Carbon\Carbon::parse($log->date)->format('D, M j') }}</td>
                                    <td class="pr-4">{{ \Carbon\Carbon::parse($log->time_in)->format('g:i A') }}</td>
                                    <td class="pr-4">{{ $log->time_out ? \Carbon\Carbon::parse($log->time_out)->format('g:i A') : '—' }}</td>
                                    <td>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-red-100 text-red-800'][$log->status] ?? '' }}">{{ ucfirst($log->status) }}</span>
                                        @if ($log->review_note) <span class="text-xs text-gray-500">· {{ $log->review_note }}</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- Today's Status Card -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-900">Today's Status</h2>
            <div class="flex items-center space-x-2">
                <span class="text-gray-600">{{ now()->format('l, F j, Y') }}</span>
                <button 
                    wire:click="refreshData"
                    class="p-2 text-blue-600 hover:text-blue-700 transition rounded-lg hover:bg-slate-100"
                    title="Refresh data"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_18rem] gap-6">
            <!-- Punches -->
            <div class="bg-slate-50 rounded-lg p-5 border border-gray-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Scanner punches</h3>
                        <p class="text-sm text-gray-500">First in, breaks, and final out from attendance logs.</p>
                    </div>
                </div>

                @if($attendance)
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach(\App\Support\WorkDay::PUNCHES as $column => $label)
                            @php $punchValue = $attendance->{$column} ?? null; @endphp
                            <div class="rounded-xl border border-gray-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900">
                                    {{ $punchValue ? \Carbon\Carbon::parse($punchValue)->format('h:i A') : '—' }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $punchValue ? \Carbon\Carbon::parse($punchValue)->format('M j, Y') : 'Not recorded' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-5 rounded-xl border border-dashed border-gray-200 bg-white p-8 text-center">
                        <p class="text-lg font-semibold text-gray-700">No scanner punches today</p>
                        <p class="mt-1 text-sm text-gray-500">Once the scanner syncs, today’s punch sequence will appear here.</p>
                    </div>
                @endif
            </div>

            <!-- Status & Hours -->
            <div class="bg-slate-50 rounded-lg p-5 border border-gray-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-slate-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900">Status</h3>
                </div>
                @if($attendance)
                    <div class="mb-3">
                        <span class="px-4 py-2 rounded-full text-sm font-medium
                            {{ $attendance->status === 'present' ? 'bg-emerald-100 text-emerald-800' : 
                               ($attendance->status === 'late' ? 'bg-amber-100 text-amber-800' : 
                               ($attendance->status === 'on_leave' ? 'bg-blue-100 text-blue-800' : 
                               ($attendance->status === 'half_day' ? 'bg-yellow-100 text-yellow-800' : 
                               'bg-gray-100 text-gray-800'))) 
                            }}">
                            {{ ucfirst(str_replace('_', ' ', $attendance->status)) }}
                        </span>
                    </div>
                    @if($attendance->time_in && $attendance->time_out)
                        @php
                            $totalMinutes = \App\Support\WorkDay::workedMinutes($attendance);
                            $hours = intdiv($totalMinutes, 60);
                            $minutes = $totalMinutes % 60;
                        @endphp
                        <p class="text-2xl font-bold text-gray-900">{{ $hours }}h {{ $minutes }}m</p>
                        <p class="text-gray-500 text-sm">Total hours worked</p>
                    @endif
                @else
                    <p class="text-xl text-gray-400 italic">No attendance today</p>
                @endif
                
                <!-- Clock System Status -->
                @if($isClockedIn)
                    <div class="mt-4 p-3 bg-slate-100 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-700 font-medium">Clocked In via System</p>
                        <p class="text-xs text-gray-500">
                            @if($clockStatus['last_clock_in'])
                                Since {{ \Carbon\Carbon::parse($clockStatus['last_clock_in'])->format('h:i A') }}
                            @endif
                        </p>
                    </div>
                @elseif($clockStatus['last_clock_in'])
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-700 font-medium">Last Clocked</p>
                        <p class="text-xs text-blue-600">
                            {{ \Carbon\Carbon::parse($clockStatus['last_clock_in'])->format('h:i A') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Sync Button -->
        {{-- Manual Time Out and Sync with Clock System were here. Times come
             from the scanner; a mistake is for HR to correct, with a record
             of who did. --}}
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Cut-off Summary -->
        <div class="lg:col-span-2">
            <!-- Cut-off Statistics -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-green-100 mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Cut-off Overview</h2>
                <p class="text-sm text-gray-500 mb-6">{{ $currentCutoff }}</p>
                
                @if($monthlySummary)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-slate-50 rounded-lg p-5 text-center border border-gray-200">
                            <div class="text-3xl font-bold text-emerald-700 mb-2">{{ $monthlySummary->present_days ?? 0 }}</div>
                            <p class="text-emerald-600 text-sm">Present Days</p>
                        </div>
                        
                        <div class="bg-amber-50 rounded-lg p-5 text-center border border-amber-200">
                            <div class="text-3xl font-bold text-amber-700 mb-2">{{ $monthlySummary->late_days ?? 0 }}</div>
                            <p class="text-amber-600 text-sm">Late Days</p>
                        </div>
                        
                        <div class="bg-blue-50 rounded-lg p-5 text-center border border-blue-200">
                            <div class="text-3xl font-bold text-blue-700 mb-2">{{ $monthlySummary->leave_days ?? 0 }}</div>
                            <p class="text-blue-600 text-sm">Leave Days</p>
                        </div>
                        
                        <div class="bg-slate-50 rounded-lg p-5 text-center border border-gray-200">
                            <div class="text-3xl font-bold text-gray-900 mb-2">{{ $monthlySummary->absent_days ?? 0 }}</div>
                            <p class="text-gray-500 text-sm">Absent Days</p>
                            @if($monthlySummary->suspended_days ?? 0)<p class="text-xs text-gray-500 mt-1">+ {{ $monthlySummary->suspended_days }} suspended</p>@endif
                        </div>
                    </div>
                    
                    <!-- Cut-off Total -->
                    <div class="mt-6 p-5 bg-slate-50 border border-slate-200 rounded-lg text-gray-900">
                        <div class="grid gap-4 md:grid-cols-3 md:items-center">
                            <div>
                                <p class="text-sm text-gray-600">Total Working Days This Cut-off</p>
                                <p class="text-3xl font-bold mt-1">{{ $monthlySummary->total_days ?? 0 }}</p>
                            </div>
                            <div class="md:text-center">
                                @php
                                    $cutoffHours = intdiv($cutoffWorkedMinutes, 60);
                                    $cutoffMinutes = $cutoffWorkedMinutes % 60;
                                @endphp
                                <p class="text-sm text-gray-600">Total Hours This Cut-off</p>
                                <p class="text-3xl font-bold mt-1">{{ $cutoffHours }}h {{ $cutoffMinutes }}m</p>
                            </div>
                            <div class="text-right">
                                @php
                                    $presentRate = $monthlySummary->total_days > 0 ? 
                                        round(($monthlySummary->present_days / $monthlySummary->total_days) * 100, 1) : 0;
                                @endphp
                                <p class="text-2xl font-bold">{{ $presentRate }}%</p>
                                <p class="text-sm opacity-90">Attendance Rate</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No attendance data available for this cut-off.</p>
                    </div>
                @endif
            </div>
            
            <!-- Recent Attendance -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 mb-1">Attendance Punches</h2>
                        <p class="text-sm text-gray-500">{{ $currentCutoff }}</p>
                    </div>
                    <label class="text-sm text-gray-600">
                        <span class="block mb-1 font-medium text-gray-700">Cut-off</span>
                        <select wire:model.live="selectedCutoffStart" class="form-input min-w-[14rem]">
                            @foreach($cutoffOptions as $option)
                                <option value="{{ $option['start'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                
                @if(count($attendanceHistory) > 0)
                    <div class="space-y-4">
                        @foreach(array_slice($attendanceHistory, 0, $showAllAttendance ? count($attendanceHistory) : 7) as $record)
                            @php
                                $problems = \App\Support\WorkDay::problems($record);
                                $hasNoOut = $record->time_in && ! $record->time_out;
                                $status = $hasNoOut ? 'undertime' : (($record->notes ?? '') === 'Suspension' && ! $record->time_in ? 'suspended' : $record->status);
                                $statusClasses = [
                                    'present' => 'bg-emerald-100 text-emerald-800',
                                    'late' => 'bg-amber-100 text-amber-800',
                                    'undertime' => 'bg-orange-100 text-orange-800',
                                    'on_leave' => 'bg-blue-100 text-blue-800',
                                    'half_day' => 'bg-yellow-100 text-yellow-800',
                                    'absent' => 'bg-red-100 text-red-800',
                                    'suspended' => 'bg-gray-200 text-gray-800',
                                ];
                                $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-800';
                                $worked = \App\Support\WorkDay::workedMinutes($record, 60, $employee);
                            @endphp
                            <div class="p-4 bg-slate-50 rounded-lg hover:bg-slate-100 transition">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="flex items-center">
                                    <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center mr-4 border border-gray-200">
                                        <span class="text-gray-900 font-bold">{{ \Carbon\Carbon::parse($record->date)->format('d') }}</span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            {{ \Carbon\Carbon::parse($record->date)->format('l, M j') }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </span>
                                            @if($worked > 0)
                                                <span class="text-xs text-gray-500">{{ intdiv($worked, 60) }}h {{ $worked % 60 }}m</span>
                                            @endif
                                            @if($problems)
                                                <span class="text-xs font-medium text-amber-700">{{ $problems[0] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6 lg:min-w-[34rem]">
                                        @foreach(\App\Support\WorkDay::PUNCHES as $column => $label)
                                            @php
                                                $punch = $record->{$column} ?? null;
                                            @endphp
                                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                                                <p class="mt-1 font-mono text-sm font-semibold text-gray-900">
                                                    {{ $punch ? \Carbon\Carbon::parse($punch)->format('h:i A') : '—' }}
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    @if(count($attendanceHistory) > 7)
                        <div class="mt-6 text-center">
                            <button type="button"
                                    wire:click="toggleAttendanceHistory"
                                    class="px-4 py-2 text-blue-600 hover:text-blue-700 font-medium hover:bg-red-50 rounded-lg transition">
                                {{ $showAllAttendance ? 'Show Less' : 'View All Records →' }}
                            </button>
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="text-gray-500">No attendance records found for this cut-off.</p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Right Column - Employee Info & Legend -->
        <div class="space-y-8">
            <!-- Employee Info Card -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Employee Information</h2>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Name</p>
                        <p class="font-medium text-gray-900">{{ $employee->full_name }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Department</p>
                        <p class="font-medium text-gray-900">{{ $employee->department_name }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Employee ID</p>
                        <p class="font-medium text-gray-900">{{ $employee->employee_no ?: 'Not assigned' }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-emerald-600 mb-1">Status</p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            {{ $employee->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 
                               ($employee->status === 'on_leave' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst(str_replace('_', ' ', $employee->status)) }}
                        </span>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Hire Date</p>
                        <p class="font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($employee->hire_date)->format('M d, Y') }}
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Status Legend -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h2 class="text-xl font-bold text-emerald-800 mb-4">Status Guide</h2>
                
                <div class="space-y-3">
                    <div class="flex items-center p-3 bg-green-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-emerald-800">Present</p>
                            <p class="text-sm text-green-600">On time attendance</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-amber-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-amber-800">Late</p>
                            <p class="text-sm text-amber-600">
                                @if ($employee->shift_start ?? null)
                                    Arrived more than {{ \App\Support\Tardiness::GRACE_MINUTES }} minutes after {{ \Carbon\Carbon::parse($employee->shift_start)->format('g:i A') }}
                                @else
                                    Arrived after the shift start
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-blue-800">On Leave</p>
                            <p class="text-sm text-blue-600">Approved leave day</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-slate-100 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-slate-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Absent</p>
                            <p class="text-sm text-gray-500">No attendance recorded</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Stats</h2>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-slate-100 rounded-lg">
                        <span class="text-gray-600">This Week</span>
                        @php
                            $weekDays = collect($attendanceHistory)->filter(function($record) {
                                return in_array($record->status, ['present', 'late', 'half_day']) && 
                                       Carbon::parse($record->date)->between(
                                           now()->startOfWeek(), 
                                           now()->endOfWeek()
                                       );
                            })->count();
                        @endphp
                        <span class="text-xl font-bold text-gray-900">{{ $weekDays }} days</span>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <span class="text-blue-700">Average Hours/Day</span>
                        @php
                            $avgHours = 0;
                            $completedDays = collect($attendanceHistory)->filter(function($record) {
                                return $record->time_in && $record->time_out;
                            });
                            
                            if($completedDays->count() > 0) {
                                $totalHours = $completedDays->sum(function($record) {
                                    $timeIn = Carbon::parse($record->time_in);
                                    $timeOut = Carbon::parse($record->time_out);
                                    return $timeIn->diffInHours($timeOut);
                                });
                                $avgHours = round($totalHours / $completedDays->count(), 1);
                            }
                        @endphp
                        <span class="text-xl font-bold text-blue-800">{{ $avgHours }}h</span>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                        <span class="text-purple-700">Current Time</span>
                        <span class="text-xl font-bold text-purple-800" id="current-time">
                            {{ now()->format('h:i A') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
             class="fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-sm z-50">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
             class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-sm z-50">
            {{ session('error') }}
        </div>
    @endif
    
    @if(session()->has('info'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
             class="fixed bottom-4 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-sm z-50">
            {{ session('info') }}
        </div>
    @endif
</div>

<!-- JavaScript for Live Clock -->
<script>
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour12: true, 
            hour: '2-digit', 
            minute: '2-digit'
        });
        
        const currentTimeElement = document.getElementById('current-time');
        if (currentTimeElement) {
            currentTimeElement.textContent = timeString;
        }
    }
    
    // Update clock every minute
    setInterval(updateClock, 60000);
    updateClock(); // Initial call
    
    // Listen for clock system events
    document.addEventListener('clockIn', function() {
        // Force refresh page after clock in
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
    
    document.addEventListener('clockOut', function() {
        // Force refresh page after clock out
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
    
    // If using Livewire, listen for refresh events
    if (typeof Livewire !== 'undefined') {
        Livewire.on('refresh-attendance', () => {
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    }
</script>
