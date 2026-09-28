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
    public ?string $syncStarted = null;
    public bool $overwriteManual = false;

    public $selectedDate;
    public $attendanceRecords = [];
    public $employees = [];
    public $departments = [];
    public $filters = [
        'status' => null,
        'department' => null,
        'company' => null,
        'search' => null,
    ];

    /** The two businesses, the same list the employee screen offers. */
    public array $companies = ['GKLASAM OPC', 'Imprint Cafe'];
    public $stats = [];

    /** Start date of the cutoff the summary shows: the 1st or the 16th. */
    public string $summaryCutoff = '';

    /** Its own search: finding one person in the cutoff without narrowing the day's table. */
    public string $summarySearch = '';

    /** Official business: who, which days, and where. */
    public string $obEmployee = '';
    public string $obFrom = '';
    public string $obTo = '';
    public string $obNote = '';

    public function mount()
    {
        $this->selectedDate = date('Y-m-d');
        $this->summaryCutoff = \App\Services\AttendanceSummary::cutoffFor(date('Y-m-d'))->start;
        $this->loadData();
    }

    /** The last few cutoffs, newest first, for the picker. */
    public function cutoffOptions(): array
    {
        return \App\Support\PayPeriod::recent(6);
    }

    /**
     * Everybody active, with their counts for the chosen cutoff, following the
     * same company, department and name filters as the day's table - so
     * narrowing to Imprint Cafe narrows both.
     */
    public function cutoffSummary(): \Illuminate\Support\Collection
    {
        $period = \App\Support\PayPeriod::fromStart($this->summaryCutoff ?: date('Y-m-d'));

        $people = DB::table('employees as e')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->leftJoin('departments as d', 'd.department_id', '=', 'e.department_id')
            ->where('e.status', 'active')
            ->when($this->filters['company'] ?? null, fn ($q, $c) => $q->where('e.company', $c))
            ->when($this->filters['department'] ?? null, fn ($q, $d) => $q->where('e.department_id', $d))
            ->when($this->filters['search'] ?? null, fn ($q, $term) => $q->where('u.full_name', 'like', '%'.$term.'%'))
            ->when(trim($this->summarySearch) !== '', function ($q) {
                $term = '%'.trim($this->summarySearch).'%';
                $q->where(fn ($w) => $w->where('u.full_name', 'like', $term)->orWhere('e.employee_no', 'like', $term));
            })
            ->orderByRaw("COALESCE(NULLIF(u.last_name, ''), u.full_name)")
            ->select('e.employee_id', 'e.employee_no', 'e.company', 'u.full_name', 'd.department_name')
            ->get();

        $counts = (new \App\Services\AttendanceSummary)
            ->forEmployees($people->pluck('employee_id')->map(fn ($id) => (int) $id)->all(), $period);

        return $people->map(function ($p) use ($counts) {
            return (object) ((array) $p + ($counts[$p->employee_id]
                ?? ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'unpaid_leave' => 0]));
        });
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
                'e.company',
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

        if ($this->filters['company']) {
            $query->where('e.company', $this->filters['company']);
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

        if ($this->filters['company']) {
            $query->where('e.company', $this->filters['company']);
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


    public bool $showTrail = false;

    public function toggleTrail(): void
    {
        $this->showTrail = ! $this->showTrail;
    }

    /** Recent changes to attendance and pay, newest first. */
    public function getTrailProperty()
    {
        return \App\Services\Auditor::recent(['hr_attendance', 'hr_payroll', 'payroll_corrections', 'leaves'], 30);
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
        $before = DB::table('hr_attendance')->where('attendance_id', $attendanceId)->first();

        DB::table('hr_attendance')
            ->where('attendance_id', $attendanceId)
            ->update([
                'status' => $status,
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'notes' => $notes,
                'updated_at' => now()
            ]);

        // The edit that a disputed deduction usually turns on.
        \App\Services\Auditor::record('update', 'hr_attendance', $attendanceId,
            $before ? ['status' => $before->status, 'time_in' => $before->time_in, 'time_out' => $before->time_out] : null,
            ['status' => $status, 'time_in' => $timeIn, 'time_out' => $timeOut]);

        $this->loadAttendance();
        session()->flash('success', 'Attendance updated successfully!');
    }

    /**
     * Official business: working away from the office, where there is no
     * scanner. Each day becomes a worked day - not absent, late or short -
     * and the sync leaves it alone, since the row is no longer the scanner's.
     * Any scans the day already has are kept, only the status changes.
     */
    public function markOfficialBusiness(): void
    {
        $data = $this->validate([
            'obEmployee' => 'required|integer|exists:employees,employee_id',
            'obFrom' => 'required|date_format:Y-m-d',
            'obTo' => 'nullable|date_format:Y-m-d|after_or_equal:obFrom',
            'obNote' => 'required|string|max:120',
        ], [
            'obEmployee.required' => 'Choose who was on official business.',
            'obNote.required' => 'Say where - an event name or place.',
        ]);

        $from = \Carbon\Carbon::parse($data['obFrom']);
        $to = \Carbon\Carbon::parse($data['obTo'] ?: $data['obFrom']);
        if ($from->diffInDays($to) > 31) {
            $this->addError('obTo', 'At most a month at a time.');
            return;
        }

        $note = 'Official business: '.trim($data['obNote']);
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $existing = DB::table('hr_attendance')->where('employee_id', $data['obEmployee'])->whereDate('date', $day)->first();
            if ($existing) {
                DB::table('hr_attendance')->where('attendance_id', $existing->attendance_id)
                    ->update(['status' => 'official_business', 'notes' => $note, 'updated_at' => now()]);
            } else {
                $existing = null;
                DB::table('hr_attendance')->insert([
                    'employee_id' => $data['obEmployee'], 'date' => $day->toDateString(),
                    'status' => 'official_business', 'notes' => $note,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            \App\Services\Auditor::record('update', 'hr_attendance', $existing->attendance_id ?? null,
                $existing ? ['status' => $existing->status] : null,
                ['status' => 'official_business', 'date' => $day->toDateString(), 'notes' => $note]);
        }

        $days = $from->diffInDays($to) + 1;
        $this->reset('obEmployee', 'obFrom', 'obTo', 'obNote');
        $this->loadData();
        session()->flash('success', "Marked {$days} day(s) as official business.");
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
    /**
     * Starts the same sync the hourly task runs, in the background.
     *
     * Reading the scanner takes minutes - its whole log comes every time - and
     * the web server here answers one request at a time, so doing it inside
     * the click froze the site for everybody until it finished or timed out.
     * The command writes its outcome where the hourly run does, and a failure
     * shows as the red alert above.
     */
    public function syncFromDevice(): void
    {
        $this->syncError = null;
        $this->syncSummary = null;
        $this->syncStarted = null;

        if (\App\Console\Commands\SyncAttendance::isRunning()) {
            $this->syncStarted = 'A sync is already running. Attendance will update when it finishes.';

            return;
        }

        $command = '"'.PHP_BINARY.'" "'.base_path('artisan').'" attendance:sync'
            .($this->overwriteManual ? ' --overwrite' : '')
            .' >> "'.storage_path('logs/scanner-sync.log').'" 2>&1';

        // Detached, so this request returns at once.
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start "" /B cmd /C "'.$command.'"', 'r'));
        } else {
            exec($command.' &');
        }

        $this->syncStarted = 'Sync started. Attendance will update in a few minutes - reload the page to see it.';
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
        </div>
    </div>


    {{-- Bringing attendance in from the fingerprint scanner.

         Two ways on purpose. The pull is the one to use day to day, but it
         depends on the device being reachable from this server - and when it is
         not, attendance still has to get in somehow, so an export from the
         scanner's own software can be uploaded instead. --}}
    @if (! (\Illuminate\Support\Facades\Cache::get('attendance.sync.last')['ok'] ?? true))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-semibold"><i class="fas fa-triangle-exclamation"></i> The hourly scanner sync failed at {{ \Carbon\Carbon::parse(\Illuminate\Support\Facades\Cache::get('attendance.sync.last')['at'])->format('M j, g:i A') }}.</p>
            <p class="mt-1">{{ \Illuminate\Support\Facades\Cache::get('attendance.sync.last')['message'] }}</p>
            <p class="mt-1 text-red-700">Attendance since then is not in yet. This clears on its own after the next successful sync.</p>
        </div>
    @endif
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Biometric scanner</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Scans become attendance days: first in, lunch in, lunch out, CB in, CB out, and the last scan of the day as final out.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($this->deviceConfigured)
                    <button wire:click="syncFromDevice" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="syncFromDevice">
                            <i class="fas fa-rotate"></i> Sync from device
                        </span>
                        <span wire:loading wire:target="syncFromDevice">Starting...</span>
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

        {{-- Events, client sites: worked, but nowhere near the scanner. --}}
        <div class="mt-4 pt-4 border-t border-gray-200">
            <p class="form-label">Official business</p>
            <p class="text-xs text-gray-500 mb-2">For people working away from the office, like at an event. Those days count as full working days - not absent, late or short - and the scanner sync will not change them.</p>
            @php
                $obPeople = \Illuminate\Support\Facades\DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
                    ->where('e.status', 'active')->orderBy('u.full_name')->get(['e.employee_id', 'u.full_name']);
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-start">
                <div class="lg:col-span-2">
                    <select wire:model="obEmployee" class="form-input" aria-label="Employee">
                        <option value="">Choose employee…</option>
                        @foreach($obPeople as $person)
                            <option value="{{ $person->employee_id }}">{{ $person->full_name }}</option>
                        @endforeach
                    </select>
                    @error('obEmployee') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <input type="date" wire:model="obFrom" class="form-input" aria-label="From">
                    @error('obFrom') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <input type="date" wire:model="obTo" class="form-input" aria-label="To (leave blank for one day)" title="To - leave blank for one day">
                    @error('obTo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <input type="text" wire:model="obNote" class="form-input" maxlength="120" placeholder="Where, e.g. MMDA event" aria-label="Where">
                    @error('obNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <button wire:click="markOfficialBusiness" wire:loading.attr="disabled" class="btn-secondary mt-3">
                <i class="fas fa-briefcase"></i> Mark official business
            </button>
        </div>

        @if ($syncStarted)
            <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800" role="status">
                <i class="fas fa-rotate"></i> {{ $syncStarted }}
            </div>
        @endif

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

    {{-- Who changed what. Attendance decides pay, so an edit here is a change
     to somebody's money and needs to be answerable later. --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Change history</h2>
                <p class="text-sm text-gray-600 mt-1">Edits to attendance, payroll and leave, with who made them.</p>
            </div>
            <button wire:click="toggleTrail" class="btn-secondary">{{ $showTrail ? 'Hide' : 'Show' }}</button>
        </div>

        @if ($showTrail)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600 border-b border-gray-200">
                            <th class="py-2">When</th>
                            <th class="py-2">Who</th>
                            <th class="py-2">What</th>
                            <th class="py-2">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->trail as $entry)
                            <tr class="border-b border-gray-100 align-top">
                                <td class="py-2 whitespace-nowrap text-gray-600">
                                    {{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('j M Y, g:i A') }}
                                </td>
                                <td class="py-2">{{ $entry->full_name ?? 'System' }}</td>
                                <td class="py-2 text-gray-600">
                                    {{ str_replace(['hr_', '_'], ['', ' '], $entry->table_name) }} #{{ $entry->record_id }}
                                </td>
                                <td class="py-2 text-gray-600">
                                    @if ($entry->old_values)
                                        <span class="line-through text-gray-400">{{ Str::limit($entry->old_values, 70) }}</span>
                                    @endif
                                    <span class="block">{{ Str::limit((string) $entry->new_values, 70) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-gray-500">Nothing has been changed yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
                    <option value="official_business">Official business</option>
                </select>
            </div>

            <!-- Company Filter -->
            <div>
                <label class="form-label">Company</label>
                <select wire:model.live="filters.company" class="form-input">
                    <option value="">All companies</option>
                    @foreach($companies as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
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

    {{-- ------------------------------------------------ per-cutoff totals --}}
    @php
        $summaryPeriod = \App\Support\PayPeriod::fromStart($summaryCutoff ?: date('Y-m-d'));
        $summaryRows = $this->cutoffSummary();
        $summaryWorkedMinutes = $summaryRows->sum('worked_minutes');
        $summaryWorkedHours = intdiv($summaryWorkedMinutes, 60);
        $summaryWorkedRemainder = $summaryWorkedMinutes % 60;
    @endphp
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Cutoff summary</h2>
                <p class="text-sm text-gray-600">
                    Days present, late and absent per person for {{ $summaryPeriod->label() }}.
                    Absences and lates are the same ones payroll deducts.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="search" wire:model.live.debounce.300ms="summarySearch"
                           placeholder="Name or employee no."
                           class="form-input pl-9" style="width: 16rem">
                </div>
                <select wire:model.live="summaryCutoff" class="form-input" style="width: 11rem">
                    @foreach ($this->cutoffOptions() as $option)
                        <option value="{{ $option->start }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-50">
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3 text-right">Present</th>
                        <th class="px-4 py-3 text-right">Late</th>
                        <th class="px-4 py-3 text-right">Absent</th>
                        <th class="px-4 py-3 text-right">Leave</th>
                        <th class="px-4 py-3 text-right">Hours</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($summaryRows as $row)
                        @php
                            $rowHours = intdiv((int) $row->worked_minutes, 60);
                            $rowMinutes = ((int) $row->worked_minutes) % 60;
                        @endphp
                        <tr>
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900">{{ $row->full_name }}</div>
                                <div class="text-xs text-gray-500">{{ $row->employee_no }}</div>
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $row->company ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $row->department_name ?? '-' }}</td>
                            <td class="px-4 py-2 text-right font-medium text-green-700">{{ $row->present }}</td>
                            <td class="px-4 py-2 text-right {{ $row->late ? 'font-medium text-amber-700' : 'text-gray-400' }}">{{ $row->late }}</td>
                            <td class="px-4 py-2 text-right {{ $row->absent ? 'font-medium text-red-700' : 'text-gray-400' }}">{{ $row->absent }}</td>
                            <td class="px-4 py-2 text-right text-gray-700">{{ $row->leave + $row->unpaid_leave }}</td>
                            <td class="px-4 py-2 text-right font-medium text-slate-900">{{ $rowHours }}h {{ $rowMinutes }}m</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Nobody matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .att-day { width: 100%; }
        .att-day th, .att-day td { padding-left: .6rem; padding-right: .6rem; }
        .att-day th.t, .att-day td.font-mono { white-space: nowrap; text-align: center; }
        .att-day td.font-mono { font-size: .8125rem; }
        .att-day td.font-mono .block { white-space: normal; max-width: 7rem; margin: 0 auto; line-height: 1.2; }
    </style>

    <!-- Attendance Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Attendance Records - {{ date('F d, Y', strtotime($selectedDate)) }}</h2>
            <div class="text-sm text-gray-600">
                Total: {{ count($attendanceRecords) }} records
            </div>
        </div>
        
        {{-- Thirteen columns did not fit, so the table scrolled sideways.
             Company, department and job title now sit under the name, and the
             six punches are compact enough to stay on one line each. --}}
        <div class="overflow-x-auto">
            <table class="data-table att-day">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="t">First in</th>
                        <th class="t">Lunch in</th>
                        <th class="t">Lunch out</th>
                        <th class="t">CB in</th>
                        <th class="t">CB out</th>
                        <th class="t">Final out</th>
                        <th>Status</th>
                        <th class="t">Hours</th>
                        <th class="sr-only">Edit</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($attendanceRecords) > 0)
                        @foreach($attendanceRecords as $record)
                            @php
                                $timeIn = $record->time_in ? date('H:i', strtotime($record->time_in)) : '—';
                                $timeOut = $record->time_out ? date('H:i', strtotime($record->time_out)) : '—';
                                
                                // Hours actually worked: the breaks that were
                                // punched come off. This used to be the raw
                                // span from first in to final out, so an hour
                                // at lunch was shown as an hour at the machine.
                                $hours = '--';
                                if ($record->time_in && $record->time_out) {
                                    $hours = \App\Support\WorkDay::workedHours($record) . 'h';
                                }

                                $breakMinutes = \App\Support\WorkDay::breakMinutes($record);
                                $dayProblems = \App\Support\WorkDay::problems($record);

                                $punchTime = fn ($value) => $value ? date('H:i', strtotime($value)) : '—';
                                
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
                                    'official_business' => 'bg-cyan-100 text-cyan-800',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-hr-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-hr-600"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900">{{ $record->full_name ?? 'N/A' }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ $record->company ?? '' }}@if(($record->company ?? null) && ($record->department_name ?? null)) &middot; @endif{{ $record->department_name ?? '' }}
                                            </div>
                                            <div class="text-xs text-gray-400">{{ $record->job_title ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="font-mono">
                                    {{ $timeIn }}
                                    @if($minutesLate !== null && $minutesLate > \App\Support\Tardiness::GRACE_MINUTES)
                                        <span class="block text-xs font-sans text-amber-700">{{ $minutesLate }} min late</span>
                                    @endif
                                </td>
                                <td class="font-mono">{{ $punchTime($record->lunch_in ?? null) }}</td>
                                <td class="font-mono">
                                    {{ $punchTime($record->lunch_out ?? null) }}
                                    @if($breakMinutes > 0)
                                        <span class="block text-xs font-sans text-gray-500">{{ $breakMinutes }} min break</span>
                                    @endif
                                </td>
                                <td class="font-mono">{{ $punchTime($record->cb_in ?? null) }}</td>
                                <td class="font-mono">{{ $punchTime($record->cb_out ?? null) }}</td>
                                <td class="font-mono">
                                    {{ $timeOut }}
                                    @if($dayProblems)
                                        <span class="block text-xs font-sans text-amber-700" title="{{ implode('; ', $dayProblems) }}">{{ $dayProblems[0] }}</span>
                                    @endif
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
                                                class="p-2 text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                                title="Edit this day" aria-label="Edit {{ $record->full_name }}'s day">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-500">
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

    {{-- Attendance comes from the scanner. The by-hand controls that used to
         be here - a Manual Entry button that only ever showed a placeholder
         alert, a Time Out on every row, and Present / Absent / Late buttons
         for every employee - are gone. A scanner mistake is corrected with
         Edit, which is audited; a day is not typed in from nothing. --}}

    <!-- Modal for Editing Attendance -->
    <div id="editModal" class="fixed inset-0 z-[70] hidden overflow-y-auto">
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
                                    <option value="official_business">Official business</option>
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
