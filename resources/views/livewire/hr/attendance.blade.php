<!-- attendance.blade.php -->
<?php

use App\Services\Attendance\PunchFileReader;
use App\Services\Attendance\PunchImporter;
use App\Services\Attendance\ZktecoPuller;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    use WithFileUploads;

    public $punchFile;
    public ?array $syncSummary = null;
    public ?string $syncError = null;
    public bool $overwriteManual = false;

    public $selectedDate;
    public $attendanceRecords = [];
    public $employees = [];
    public $departments = [];
    public $filters = [
        'status' => null,
        'department' => null,
        'search' => null,
    ];
    public $stats = [];

    public function mount()
    {
        $this->selectedDate = date('Y-m-d');
        $this->loadData();
    }

    public function loadData()
    {
        $this->loadAttendance();
        $this->loadEmployees();
        $this->loadDepartments();
        $this->loadStats();
    }

    public function loadAttendance()
    {
        $query = DB::table('hr_attendance as a')
            ->select(
                'a.*',
                'e.employee_id',
                'e.job_title',
                'e.shift_start',
                'e.shift_end',
                'u.full_name',
                'u.username',
                'u.email',
                'd.department_name'
            )
            ->leftJoin('employees as e', 'a.employee_id', '=', 'e.employee_id')
            ->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->whereDate('a.date', $this->selectedDate);

        if ($this->filters['status']) {
            $query->where('a.status', $this->filters['status']);
        }

        if ($this->filters['department']) {
            $query->where('e.department_id', $this->filters['department']);
        }

        if ($this->filters['search']) {
            $query->where(function($q) {
                $q->where('u.full_name', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.username', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.email', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        $this->attendanceRecords = $query->orderBy('a.date', 'desc')->get();
    }

    public function loadEmployees()
    {
        $query = DB::table('employees as e')
            ->select(
                'e.employee_id',
                'e.job_title',
                'e.status as emp_status',
                'u.full_name',
                'u.username',
                'u.email',
                'd.department_name',
                DB::raw('COALESCE(a.status, "not_marked") as attendance_status')
            )
            ->leftJoin('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->leftJoin('hr_attendance as a', function($join) {
                $join->on('a.employee_id', '=', 'e.employee_id')
                     ->whereDate('a.date', $this->selectedDate);
            })
            ->where('e.status', 'active');

        if ($this->filters['department']) {
            $query->where('e.department_id', $this->filters['department']);
        }

        if ($this->filters['search']) {
            $query->where(function($q) {
                $q->where('u.full_name', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.username', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.email', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        $this->employees = $query->orderBy('u.full_name')->get();
    }

    public function loadDepartments()
    {
        $this->departments = DB::table('departments')
            ->select('department_id', 'department_name')
            ->orderBy('department_name')
            ->get();
    }

    public function loadStats()
    {
        $stats = DB::table('hr_attendance')
            ->select(
                DB::raw('COUNT(CASE WHEN status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(CASE WHEN status = "absent" THEN 1 END) as absent_count'),
                DB::raw('COUNT(CASE WHEN status = "late" THEN 1 END) as late_count'),
                DB::raw('COUNT(CASE WHEN status = "half_day" THEN 1 END) as half_day_count'),
                DB::raw('COUNT(CASE WHEN status = "on_leave" THEN 1 END) as on_leave_count'),
                DB::raw('COUNT(*) as total_count')
            )
            ->whereDate('date', $this->selectedDate)
            ->first();

        $this->stats = [
            'present' => $stats->present_count ?? 0,
            'absent' => $stats->absent_count ?? 0,
            'late' => $stats->late_count ?? 0,
            'half_day' => $stats->half_day_count ?? 0,
            'on_leave' => $stats->on_leave_count ?? 0,
            'total' => $stats->total_count ?? 0
        ];
    }

    public function markAttendance($employeeId, $status)
    {
        // Check if attendance already exists
        $existing = DB::table('hr_attendance')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $this->selectedDate)
            ->first();

        if ($existing) {
            DB::table('hr_attendance')
                ->where('attendance_id', $existing->attendance_id)
                ->update([
                    'status' => $status,
                    'time_in' => $status === 'present' ? now() : null,
                    'notes' => 'Corrected by HR',
                    'updated_at' => now()
                ]);
        } else {
            DB::table('hr_attendance')->insert([
                'employee_id' => $employeeId,
                'date' => $this->selectedDate,
                'status' => $status,
                'time_in' => $status === 'present' ? now() : null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $this->loadData();
        session()->flash('success', 'Attendance marked successfully!');
    }

    public function markTimeOut($attendanceId)
    {
        DB::table('hr_attendance')
            ->where('attendance_id', $attendanceId)
            ->update([
                'time_out' => now(),
                'notes' => 'Corrected by HR',
                'updated_at' => now()
            ]);

        $this->loadAttendance();
        session()->flash('success', 'Time out recorded successfully!');
    }

    public function updateDate($date)
    {
        $this->selectedDate = $date;
        $this->loadData();
    }

    public function applyFilters()
    {
        $this->loadData();
    }

    public function resetFilters()
    {
        $this->filters = [
            'status' => null,
            'department' => null,
            'search' => null,
        ];
        $this->loadData();
    }

    public function editAttendance($attendanceId, $status, $timeIn = null, $timeOut = null, $notes = null)
    {
        DB::table('hr_attendance')
            ->where('attendance_id', $attendanceId)
            ->update([
                'status' => $status,
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'notes' => $notes,
                'updated_at' => now()
            ]);

        $this->loadAttendance();
        session()->flash('success', 'Attendance updated successfully!');
    }

    // Add these methods to handle real-time updates
    public function updatedFilters()
    {
        $this->loadData();
    }

    public function updatedSelectedDate($value)
    {
        $this->loadData();
    }

    /**
     * Whether a pull is even possible. Without an address configured the button
     * would only ever produce the same error, so it is not offered.
     */
    public function getDeviceConfiguredProperty(): bool
    {
        return (bool) config('attendance.zkteco.host');
    }

    /**
     * Fetch straight from the scanner over the network.
     */
    public function syncFromDevice(PunchImporter $importer): void
    {
        $this->syncError = null;
        $this->syncSummary = null;

        try {
            $punches = ZktecoPuller::fromConfig()->punches();
        } catch (\Throwable $e) {
            // The causes are mundane - device off, wrong address, different
            // subnet - so the message says which rather than "sync failed".
            $this->syncError = $e->getMessage();

            return;
        }

        $this->syncSummary = $importer->import($punches, $this->overwriteManual) + ['source' => 'the scanner'];
        $this->loadAttendance();
        $this->loadStats();
    }

    /**
     * The fallback: an export from the scanner's own software. Always available,
     * because it needs nothing of the network.
     */
    public function importFile(PunchImporter $importer): void
    {
        $this->syncError = null;
        $this->syncSummary = null;

        $this->validate([
            'punchFile' => ['required', 'file', 'max:10240'],
        ], [], ['punchFile' => 'file']);

        try {
            $read = (new PunchFileReader)->read($this->punchFile->getRealPath());
        } catch (\Throwable $e) {
            $this->syncError = $e->getMessage();

            return;
        }

        $this->syncSummary = $importer->import($read['punches'], $this->overwriteManual)
            + ['source' => 'the file', 'unreadable' => $read['unreadable']];

        $this->reset('punchFile');
        $this->loadAttendance();
        $this->loadStats();
    }

    public function dismissSync(): void
    {
        $this->syncSummary = null;
        $this->syncError = null;
    }
}
?>

<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Attendance Management</h1>
            <p class="text-gray-600 mt-1">Track and manage employee attendance</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="relative">
                <input type="date" 
                       wire:model.live="selectedDate"
                       class="form-input pl-10"
                       value="{{ $selectedDate }}">
                <i class="fas fa-calendar-alt absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            <button class="btn-primary" onclick="openManualEntry()">
                <i class="fas fa-plus mr-2"></i>Manual Entry
            </button>
        </div>
    </div>


    {{-- Bringing attendance in from the fingerprint scanner.

         Two ways on purpose. The pull is the one to use day to day, but it
         depends on the device being reachable from this server - and when it is
         not, attendance still has to get in somehow, so an export from the
         scanner's own software can be uploaded instead. --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Biometric scanner</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Scans become attendance days: first of the day in, last of the day out.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($this->deviceConfigured)
                    <button wire:click="syncFromDevice" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="syncFromDevice">
                            <i class="fas fa-rotate"></i> Sync from device
                        </span>
                        <span wire:loading wire:target="syncFromDevice">Reading the scanner...</span>
                    </button>
                @else
                    <span class="text-sm text-gray-500">
                        <i class="fas fa-circle-info"></i>
                        Device connection is not set up. Ask your administrator to connect the scanner.
                    </span>
                @endif
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-200">
            <label class="form-label" for="punchFile">Or upload an export from the scanner</label>
            <div class="flex flex-wrap items-center gap-3">
                <input id="punchFile" type="file" wire:model="punchFile" accept=".csv,.txt,.dat"
                       class="block text-sm text-gray-700
                              file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                              file:text-sm file:font-semibold file:bg-slate-100 file:text-gray-700
                              hover:file:bg-slate-200">
                <button wire:click="importFile" wire:loading.attr="disabled" @disabled(! $punchFile) class="btn-secondary disabled:opacity-40 disabled:cursor-not-allowed">
                    Import file
                </button>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" wire:model="overwriteManual" class="rounded border-gray-300">
                    Replace days entered by hand
                </label>
            </div>
            @error('punchFile') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-gray-500">
                CSV or the device's own .dat. Days somebody typed in are kept unless you tick the box.
            </p>
        </div>

        @if ($syncError)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-sm text-red-800">{{ $syncError }}</p>
            </div>
        @endif

        @if ($syncSummary)
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-sm text-green-900">
                        Read {{ $syncSummary['days'] }} day(s) for
                        {{ $syncSummary['employees'] }} employee(s) from {{ $syncSummary['source'] }}.

                        @if (($syncSummary['unreadable'] ?? 0) > 0)
                            <span class="block mt-1 text-amber-800">
                                {{ $syncSummary['unreadable'] }} row(s) could not be read and were left out.
                            </span>
                        @endif

                        @if (! empty($syncSummary['unknown']))
                            <span class="block mt-1 text-amber-800">
                                These scanner IDs are not linked to anybody, so their scans were ignored:
                                <strong>{{ implode(', ', $syncSummary['unknown']) }}</strong>.
                                Set them on the employee under Employees.
                            </span>
                        @endif
                    </div>
                    <button wire:click="dismissSync" class="text-green-700 hover:text-green-900"><i class="fas fa-times"></i></button>
                </div>
            </div>
        @endif
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl p-4 mb-6 shadow-sm border border-gray-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="form-label">Status</label>
                <select wire:model.live="filters.status" class="form-input">
                    <option value="">All Status</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="half_day">Half Day</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>

            <!-- Department Filter -->
            <div>
                <label class="form-label">Department</label>
                <select wire:model.live="filters.department" class="form-input">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->department_id }}">{{ $dept->department_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Search -->
            <div>
                <label class="form-label">Search Employee</label>
                <div class="relative">
                    <input type="text" 
                           wire:model.live.debounce.300ms="filters.search"
                           placeholder="Search by name..."
                           class="form-input pl-10">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-end gap-2">
                <button wire:click="applyFilters" class="btn-primary w-full">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <button wire:click="resetFilters" class="btn-secondary w-full">
                    <i class="fas fa-redo mr-2"></i>Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Attendance Stats -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-green-100 text-green-600">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['present'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Present</div>
            <div class="card-subtitle">{{ date('M d, Y', strtotime($selectedDate)) }}</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-red-100 text-red-600">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['absent'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Absent</div>
            <div class="card-subtitle">{{ date('M d, Y', strtotime($selectedDate)) }}</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-yellow-100 text-yellow-600">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['late'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Late</div>
            <div class="card-subtitle">{{ date('M d, Y', strtotime($selectedDate)) }}</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-blue-100 text-blue-600">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['half_day'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Half Day</div>
            <div class="card-subtitle">{{ date('M d, Y', strtotime($selectedDate)) }}</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-purple-100 text-purple-600">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['on_leave'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">On Leave</div>
            <div class="card-subtitle">{{ date('M d, Y', strtotime($selectedDate)) }}</div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Attendance Records - {{ date('F d, Y', strtotime($selectedDate)) }}</h2>
            <div class="text-sm text-gray-600">
                Total: {{ count($attendanceRecords) }} records
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Job Title</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                        <th>Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($attendanceRecords) > 0)
                        @foreach($attendanceRecords as $record)
                            @php
                                $timeIn = $record->time_in ? date('h:i A', strtotime($record->time_in)) : '--:--';
                                $timeOut = $record->time_out ? date('h:i A', strtotime($record->time_out)) : '--:--';
                                
                                // Calculate hours if both times exist
                                $hours = '--';
                                if ($record->time_in && $record->time_out) {
                                    $diff = strtotime($record->time_out) - strtotime($record->time_in);
                                    $hours = round($diff / 3600, 1) . 'h';
                                }
                                
                                // Measured against their own shift. Either can be
                                // unknowable - no shift set, or no scan - and then
                                // nothing is claimed about the day.
                                $minutesLate = \App\Support\Tardiness::minutesLate(
                                    $record->time_in ? \Carbon\Carbon::parse($record->time_in) : null,
                                    $record->shift_start ?? null);
                                $minutesShort = \App\Support\Undertime::minutesShort(
                                    $record->time_out ? \Carbon\Carbon::parse($record->time_out) : null,
                                    $record->shift_end ?? null);

                                // Status colors
                                $statusColors = [
                                    'present' => 'bg-green-100 text-green-800',
                                    'absent' => 'bg-red-100 text-red-800',
                                    'late' => 'bg-yellow-100 text-yellow-800',
                                    'half_day' => 'bg-blue-100 text-blue-800',
                                    'on_leave' => 'bg-purple-100 text-purple-800',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-hr-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-hr-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $record->full_name ?? 'N/A' }}</div>
                                            <div class="text-sm text-gray-500">{{ $record->username ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $record->department_name ?? 'No Department' }}
                                    </span>
                                </td>
                                <td>{{ $record->job_title ?? 'N/A' }}</td>
                                <td class="font-mono">
                                    {{ $timeIn }}
                                    @if($minutesLate !== null && $minutesLate > \App\Support\Tardiness::GRACE_MINUTES)
                                        <span class="block text-xs font-sans text-amber-700">{{ $minutesLate }} min late</span>
                                    @endif
                                </td>
                                <td class="font-mono">
                                    {{ $timeOut }}
                                    @if($minutesShort !== null && $minutesShort > \App\Support\Tardiness::GRACE_MINUTES)
                                        <span class="block text-xs font-sans text-amber-700">{{ $minutesShort }} min undertime</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$record->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                    </span>
                                </td>
                                <td class="font-medium">{{ $hours }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal('{{ $record->attendance_id }}', '{{ $record->status }}', '{{ $record->time_in }}', '{{ $record->time_out }}', `{{ $record->notes ?? '' }}`)" 
                                                class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition-colors">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                        @if(!$record->time_out && $record->time_in)
                                            <button wire:click="markTimeOut('{{ $record->attendance_id }}')" 
                                                    class="px-3 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-slate-100 transition-colors">
                                                <i class="fas fa-sign-out-alt mr-1"></i>Time Out
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-calendar-times text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-lg">No attendance records found for {{ date('F d, Y', strtotime($selectedDate)) }}</p>
                                    <p class="text-sm mt-1">Try selecting a different date or check your filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Employee List for Quick Marking -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Mark Attendance for Active Employees</h3>
        <p class="text-sm text-gray-500 mb-4">Showing {{ count($employees) }} active employees</p>
        @if(count($employees) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($employees as $employee)
                    @php
                        $statusColors = [
                            'present' => 'bg-green-100 text-green-800',
                            'absent' => 'bg-red-100 text-red-800',
                            'late' => 'bg-yellow-100 text-yellow-800',
                            'half_day' => 'bg-blue-100 text-blue-800',
                            'on_leave' => 'bg-purple-100 text-purple-800',
                            'not_marked' => 'bg-gray-100 text-gray-800',
                        ];
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-hr-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-hr-600"></i>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $employee->full_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $employee->job_title }}</div>
                                    <div class="text-xs text-gray-400">{{ $employee->department_name }}</div>
                                </div>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $statusColors[$employee->attendance_status] }}">
                                {{ $employee->attendance_status === 'not_marked' ? 'Not Marked' : ucfirst(str_replace('_', ' ', $employee->attendance_status)) }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button wire:click="markAttendance('{{ $employee->employee_id }}', 'present')"
                                    class="px-3 py-2 text-xs bg-red-50 text-red-600 rounded hover:bg-slate-100 flex items-center justify-center">
                                <i class="fas fa-check mr-1"></i>Present
                            </button>
                            <button wire:click="markAttendance('{{ $employee->employee_id }}', 'absent')"
                                    class="px-3 py-2 text-xs bg-red-50 text-red-600 rounded hover:bg-slate-100 flex items-center justify-center">
                                <i class="fas fa-times mr-1"></i>Absent
                            </button>
                            <button wire:click="markAttendance('{{ $employee->employee_id }}', 'late')"
                                    class="px-3 py-2 text-xs bg-yellow-50 text-yellow-600 rounded hover:bg-yellow-100 flex items-center justify-center">
                                <i class="fas fa-clock mr-1"></i>Late
                            </button>
                            <button wire:click="markAttendance('{{ $employee->employee_id }}', 'on_leave')"
                                    class="px-3 py-2 text-xs bg-purple-50 text-purple-600 rounded hover:bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-umbrella-beach mr-1"></i>Leave
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-users text-3xl text-gray-300 mb-3"></i>
                <p>No active employees found matching your criteria.</p>
            </div>
        @endif
    </div>

    <!-- Modal for Editing Attendance -->
    <div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal()"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="editForm">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Attendance</h3>
                        
                        <input type="hidden" id="editAttendanceId">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Status</label>
                                <select id="editStatus" class="form-input">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="half_day">Half Day</option>
                                    <option value="on_leave">On Leave</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Time In</label>
                                    <input type="time" id="editTimeIn" class="form-input">
                                </div>
                                <div>
                                    <label class="form-label">Time Out</label>
                                    <input type="time" id="editTimeOut" class="form-input">
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea id="editNotes" rows="3" class="form-input" placeholder="Optional notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" onclick="saveAttendance()" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-hr-600 text-base font-medium text-white hover:bg-hr-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-hr-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Changes
                        </button>
                        <button type="button" onclick="closeEditModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-hr-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openManualEntry() {
            alert('Manual entry feature would be implemented here.');
        }
        
        function openEditModal(attendanceId, status, timeIn, timeOut, notes) {
            document.getElementById('editAttendanceId').value = attendanceId;
            document.getElementById('editStatus').value = status;
            
            // Fix for quotes in notes
            const safeNotes = notes.replace(/`/g, "'");
            document.getElementById('editNotes').value = safeNotes;
            
            // Handle time inputs
            if (timeIn && timeIn !== 'null') {
                try {
                    const timeInDate = new Date(timeIn);
                    if (!isNaN(timeInDate.getTime())) {
                        const timeString = timeInDate.toTimeString().slice(0,5);
                        document.getElementById('editTimeIn').value = timeString;
                    } else {
                        document.getElementById('editTimeIn').value = '';
                    }
                } catch(e) {
                    document.getElementById('editTimeIn').value = '';
                }
            } else {
                document.getElementById('editTimeIn').value = '';
            }
            
            if (timeOut && timeOut !== 'null') {
                try {
                    const timeOutDate = new Date(timeOut);
                    if (!isNaN(timeOutDate.getTime())) {
                        const timeString = timeOutDate.toTimeString().slice(0,5);
                        document.getElementById('editTimeOut').value = timeString;
                    } else {
                        document.getElementById('editTimeOut').value = '';
                    }
                } catch(e) {
                    document.getElementById('editTimeOut').value = '';
                }
            } else {
                document.getElementById('editTimeOut').value = '';
            }
            
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        function saveAttendance() {
            const attendanceId = document.getElementById('editAttendanceId').value;
            const status = document.getElementById('editStatus').value;
            const timeIn = document.getElementById('editTimeIn').value;
            const timeOut = document.getElementById('editTimeOut').value;
            const notes = document.getElementById('editNotes').value;
            
            // Convert time strings to proper format
            const formattedTimeIn = timeIn ? `{{ $selectedDate }} ${timeIn}:00` : null;
            const formattedTimeOut = timeOut ? `{{ $selectedDate }} ${timeOut}:00` : null;
            
            // Call Livewire method
            window.Livewire.dispatch('editAttendance', {
                attendanceId: attendanceId,
                status: status,
                timeIn: formattedTimeIn,
                timeOut: formattedTimeOut,
                notes: notes
            });
            
            closeEditModal();
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</div>
