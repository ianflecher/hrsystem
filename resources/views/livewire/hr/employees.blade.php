<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\DocumentVault;
use App\Support\WorkWeek;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    use WithPagination;

    public $departments = [];
    /** Creating a department from the form that wanted one. */
    public bool $addingDepartment = false;
    public string $inlineDepartment = '';


    public string $search = '';
    public string $statusFilter = 'all';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $full_name = '';
    public string $username = '';
    public string $email = '';
    public string $job_title = '';
    public string $shift_start = '';
    public string $shift_end = '';
    public array $rest_days = [];
    public string $immersion_until = '';
    public string $biometric_id = '';
    public $department_id = '';
    public string $hire_date = '';
    public $salary = '';
    public string $status = 'active';
    public string $role = 'employee';

    /**
     * Shown once, immediately after an account is created, and never stored in
     * readable form - the column holds only the hash. If it is missed here it
     * cannot be looked up again; it has to be reset.
     */
    public ?string $issuedPassword = null;
    public int $carriedDocuments = 0;

    /** The employee waiting on the confirmation panel, and what removing them means. */
    public ?array $removing = null;
    public ?string $issuedFor = null;

    public array $statuses = [
        'active'     => 'Active',
        'inactive'   => 'Inactive',
        'on_leave'   => 'On leave',
        'terminated' => 'Terminated',
    ];

    public array $roles = [
        'employee'   => 'Employee',
        'supervisor' => 'Supervisor',
        'leader'     => 'Leader',
        'hr'         => 'HR',
        'admin'      => 'Admin',
    ];

    public function mount(): void
    {
        $this->hire_date = now()->toDateString();
        $this->loadDepartments();
        $this->loadEmployees();
    }

    public function loadDepartments(): void
    {
        $this->departments = DB::table('departments')
            ->select('department_id', 'department_name')
            ->orderBy('department_name')
            ->get();
    }

    /**
     * A paginator rather than a property: a hundred people is a 7,500px page
     * otherwise, and Livewire cannot hold a paginator in a public property.
     */
    private function employeeQuery()
    {
        $query = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->whereNull('u.deleted_at')
            ->select(
                'e.employee_id', 'e.job_title', 'e.status', 'e.hire_date', 'e.salary',
                'u.user_id', 'u.full_name', 'u.username', 'u.email', 'u.role',
                'u.must_change_password',
                'd.department_name'
            );

        if ($this->statusFilter !== 'all') {
            $query->where('e.status', $this->statusFilter);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('u.full_name', 'like', $term)
                  ->orWhere('u.username', 'like', $term)
                  ->orWhere('u.email', 'like', $term)
                  ->orWhere('e.job_title', 'like', $term);
            });
        }

        return $query->orderBy('u.full_name');
    }

    public function with(): array
    {
        return ['employees' => $this->employeeQuery()->paginate(15)];
    }

    public function loadEmployees(): void
    {
        // Kept so the save/reset paths read the same; with() re-runs the query
        // on every render, so there is nothing to refresh by hand.
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        // Otherwise a search from page 6 lands on page 6 of a shorter list.
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $employeeId): void
    {
        $row = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->where('e.employee_id', $employeeId)
            ->select('e.*', 'u.full_name', 'u.username', 'u.email', 'u.role')
            ->first();

        if (! $row) {
            session()->flash('error', 'That employee no longer exists.');
            $this->loadEmployees();

            return;
        }

        $this->editingId     = $row->employee_id;
        $this->full_name     = $row->full_name;
        $this->username      = $row->username;
        $this->email         = $row->email;
        $this->job_title     = $row->job_title;
        $this->shift_start   = $row->shift_start ? substr($row->shift_start, 0, 5) : '';
        $this->shift_end     = $row->shift_end ? substr($row->shift_end, 0, 5) : '';
        $this->rest_days     = WorkWeek::days($row->rest_days);
        $this->immersion_until = $row->immersion_until ? substr((string) $row->immersion_until, 0, 10) : '';
        $this->biometric_id  = (string) ($row->biometric_id ?? '');
        $this->department_id = $row->department_id ?? '';
        $this->hire_date     = $row->hire_date;
        $this->salary        = $row->salary;
        $this->status        = $row->status;
        $this->role          = $row->role;
        $this->showModal     = true;
    }

    public function toggleAddDepartment(): void
    {
        $this->addingDepartment = ! $this->addingDepartment;
        $this->inlineDepartment = '';
        $this->resetValidation('inlineDepartment');
    }

    /**
     * Creates it and selects it, without disturbing anything else on the form.
     *
     * Abandoning a half-filled employee form to go and make a department was
     * the alternative, and it lost everything typed so far.
     */
    public function createDepartment(): void
    {
        $data = $this->validate(
            ['inlineDepartment' => ['required', 'string', 'max:100', 'unique:departments,department_name']],
            ['inlineDepartment.required' => 'Give the department a name.',
             'inlineDepartment.unique'   => 'There is already a department by that name.']
        );

        $id = DB::table('departments')->insertGetId([
            'department_name' => $data['inlineDepartment'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->loadDepartments();
        $this->department_id = $id;
        $this->addingDepartment = false;
        $this->inlineDepartment = '';
    }

    public function save(): void
    {
        $userId = $this->editingId
            ? DB::table('employees')->where('employee_id', $this->editingId)->value('user_id')
            : null;

        $data = $this->validate([
            'full_name'     => ['required', 'string', 'max:150'],
            'username'      => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId, 'user_id')],
            'email'         => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId, 'user_id')],
            'job_title'     => ['required', 'string', 'max:100'],
            'shift_start'   => ['nullable', 'date_format:H:i'],
            'shift_end'     => ['nullable', 'date_format:H:i'],
            'rest_days'     => ['array'],
            'rest_days.*'   => ['integer', 'between:1,7'],
            'immersion_until' => ['nullable', 'date'],
            'biometric_id'  => ['nullable', 'string', 'max:50',
                                Rule::unique('employees', 'biometric_id')->ignore($this->editingId, 'employee_id')],
            'department_id' => ['nullable'],
            'hire_date'     => ['required', 'date'],
            'salary'        => ['nullable', 'numeric', 'min:0'],
            'status'        => ['required', Rule::in(array_keys($this->statuses))],
            'role'          => ['required', Rule::in(array_keys($this->roles))],
        ]);

        $departmentId = $data['department_id'] !== '' ? (int) $data['department_id'] : null;
        // Left null when blank: somebody with no shift set cannot be judged
        // late, which is the right answer until their hours are known.
        $shiftStart = ($data['shift_start'] ?? '') !== '' ? $data['shift_start'].':00' : null;
        $shiftEnd = ($data['shift_end'] ?? '') !== '' ? $data['shift_end'].':00' : null;
        // The calendar is drawn from these, so they are the whole schedule:
        // no rest day set means the person is shown as working every day.
        $restDays = WorkWeek::store($data['rest_days'] ?? []);
        // Null once it is blank: an empty string is not a date, and a
        // regular employee is simply one with no immersion end.
        $immersionUntil = ($data['immersion_until'] ?? '') !== '' ? $data['immersion_until'] : null;
        // Null rather than empty string: the column is unique, and two blanks
        // would collide where two unenrolled people should not.
        $biometricId = ($data['biometric_id'] ?? '') !== '' ? $data['biometric_id'] : null;
        $salary = $data['salary'] === '' || $data['salary'] === null ? 0 : $data['salary'];

        if ($this->editingId) {
            DB::transaction(function () use ($data, $departmentId, $shiftStart, $shiftEnd, $restDays, $immersionUntil, $biometricId, $salary, $userId) {
                DB::table('users')->where('user_id', $userId)->update([
                    'full_name'  => $data['full_name'],
                    'username'   => $data['username'],
                    'email'      => $data['email'],
                    'role'       => $data['role'],
                    'updated_at' => now(),
                ]);

                DB::table('employees')->where('employee_id', $this->editingId)->update([
                    'job_title'     => $data['job_title'],
                    'shift_start'   => $shiftStart,
                    'shift_end'     => $shiftEnd,
                    'rest_days'     => $restDays,
                'immersion_until' => $immersionUntil,
                    'immersion_until' => $immersionUntil,
                    'biometric_id'  => $biometricId,
                    'department_id' => $departmentId,
                    'hire_date'     => $data['hire_date'],
                    'salary'        => $salary,
                    'status'        => $data['status'],
                    'updated_at'    => now(),
                ]);
            });

            session()->flash('success', $data['full_name'].' updated.');
            $this->showModal = false;
            $this->resetForm();
            $this->loadEmployees();

            return;
        }

        // A new account needs a first password, and somebody has to hand it
        // over. It is generated rather than chosen so it is not a guessable
        // house default, and the account must replace it at first sign-in.
        $password = Str::password(12, symbols: false);

        DB::transaction(function () use ($data, $departmentId, $shiftStart, $shiftEnd, $restDays, $immersionUntil, $biometricId, $salary, $password) {
            $newUserId = DB::table('users')->insertGetId([
                'full_name'            => $data['full_name'],
                'username'             => $data['username'],
                'email'                => $data['email'],
                'password'             => Hash::make($password),
                'must_change_password' => true,
                'role'                 => $data['role'],
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $employeeId = DB::table('employees')->insertGetId([
                'user_id'       => $newUserId,
                'job_title'     => $data['job_title'],
                'shift_start'   => $shiftStart,
                'shift_end'     => $shiftEnd,
                'rest_days'     => $restDays,
                'biometric_id'  => $biometricId,
                'department_id' => $departmentId,
                'hire_date'     => $data['hire_date'],
                'salary'        => $salary,
                'status'        => $data['status'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // If this person applied to us, the files they sent with their
            // application are the files HR would ask for again. They start in
            // the vault instead.
            $this->carriedDocuments = (new DocumentVault)->adoptApplicationDocuments($newUserId, $employeeId);
        });

        $this->issuedPassword = $password;
        $this->issuedFor      = $data['full_name'];
        $this->showModal      = false;
        $this->resetForm();
        $this->loadEmployees();
    }

    public function dismissIssued(): void
    {
        $this->issuedPassword = null;
        $this->issuedFor = null;
        $this->carriedDocuments = 0;
    }

    /**
     * Issues a fresh first password for somebody who never received theirs or
     * has lost it. The old one stops working immediately.
     */
    public function resetPassword(int $employeeId): void
    {
        $row = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->where('e.employee_id', $employeeId)
            ->select('u.user_id', 'u.full_name')
            ->first();

        if (! $row) {
            return;
        }

        $password = Str::password(12, symbols: false);

        DB::table('users')->where('user_id', $row->user_id)->update([
            'password'             => Hash::make($password),
            'must_change_password' => true,
            'updated_at'           => now(),
        ]);

        $this->issuedPassword = $password;
        $this->issuedFor      = $row->full_name;
        $this->loadEmployees();
    }

    /**
     * Asks first, and says what "remove" actually means for this person.
     *
     * It differs: somebody who has been paid cannot simply be deleted, because
     * their payslips are records the company has to keep. They are deactivated
     * instead - no sign-in, off the active lists, history intact. Somebody with
     * no payroll behind them is deleted outright, which is what a wrong entry
     * needs.
     */
    public function confirmRemove(int $employeeId): void
    {
        $row = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->where('e.employee_id', $employeeId)
            ->select('e.employee_id', 'e.status', 'u.user_id', 'u.full_name')
            ->first();

        if (! $row) {
            return;
        }

        if ((int) $row->user_id === (int) auth()->id()) {
            session()->flash('error', 'You cannot remove your own account.');

            return;
        }

        $payslips = DB::table('hr_payroll')->where('employee_id', $employeeId)->count();

        $this->removing = [
            'employee_id' => $row->employee_id,
            'user_id'     => $row->user_id,
            'name'        => $row->full_name,
            'payslips'    => $payslips,
            'attendance'  => DB::table('hr_attendance')->where('employee_id', $employeeId)->count(),
            'documents'   => DB::table('employee_documents')->where('employee_id', $employeeId)->count(),
            'deletes'     => $payslips === 0,
        ];
    }

    public function cancelRemove(): void
    {
        $this->removing = null;
    }

    public function remove(): void
    {
        if (! $this->removing) {
            return;
        }

        $removing = $this->removing;

        // Re-read rather than trusting the panel: it was filled in before, and
        // a payslip may have been generated since.
        if (DB::table('hr_payroll')->where('employee_id', $removing['employee_id'])->exists()) {
            $removing['deletes'] = false;
        }

        if ($removing['deletes']) {
            $paths = DB::table('employee_documents')->where('employee_id', $removing['employee_id'])->pluck('path');

            DB::transaction(function () use ($removing) {
                // The employee row goes with the account, and everything keyed
                // to either follows by cascade.
                DB::table('employees')->where('employee_id', $removing['employee_id'])->delete();
                DB::table('users')->where('user_id', $removing['user_id'])->delete();
            });

            // Only once the rows are gone, so a failed delete leaves no orphans.
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }

            session()->flash('success', $removing['name'].' was removed, along with their account and files.');
        } else {
            DB::transaction(function () use ($removing) {
                DB::table('employees')->where('employee_id', $removing['employee_id'])
                    ->update(['status' => 'inactive', 'updated_at' => now()]);
                // Cannot sign in again: the password is replaced by one nobody
                // holds, and the account is held at the change-password screen.
                DB::table('users')->where('user_id', $removing['user_id'])
                    ->update(['password' => Hash::make(Str::password(32)), 'must_change_password' => true, 'updated_at' => now()]);
            });

            session()->flash('success', $removing['name'].' was deactivated and can no longer sign in. Their payslips are kept.');
        }

        $this->removing = null;
        $this->resetPage();
        $this->loadEmployees();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId     = null;
        $this->full_name     = '';
        $this->username      = '';
        $this->email         = '';
        $this->job_title     = '';
        $this->shift_start   = '';
        $this->shift_end     = '';
        $this->rest_days     = [];
        $this->immersion_until = '';
        $this->biometric_id  = '';
        $this->department_id = '';
        $this->hire_date     = now()->toDateString();
        $this->salary        = '';
        $this->status        = 'active';
        $this->role          = 'employee';
        $this->resetErrorBag();
    }
}; ?>

<div class="p-6 md:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Employees</h1>
            <p class="text-sm text-gray-600 mt-1">
                Everyone on the payroll, and the accounts they sign in with.
            </p>
        </div>
        <button wire:click="openCreate" class="btn-primary">
            <i class="fas fa-user-plus"></i> Add employee
        </button>
    </div>

    @if ($issuedPassword)
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-key text-amber-600 mt-1"></i>
                <div class="flex-1">
                    <p class="font-semibold text-amber-900">
                        First password for {{ $issuedFor }}
                    </p>
                    <p class="text-sm text-amber-800 mt-1">
                        Hand this over in person. It is shown once and cannot be looked up
                        again &mdash; only a hash is stored. They will be asked to replace
                        it the first time they sign in.
                    </p>
                    <div class="mt-3 inline-flex items-center gap-3 rounded-lg border border-amber-300 bg-white px-4 py-2">
                        <code class="text-base font-semibold tracking-wider text-gray-900">{{ $issuedPassword }}</code>
                        @if ($carriedDocuments > 0)
                            <p class="mt-2 text-sm text-amber-800">
                                {{ $carriedDocuments }} document(s) from their application are already in their vault.
                            </p>
                        @endif
                    </div>
                </div>
                <button wire:click="dismissIssued" class="text-amber-700 hover:text-amber-900" title="Dismiss">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="flex gap-2">
            @foreach (array_merge(['all' => 'All'], $statuses) as $key => $label)
                <button wire:click="setStatusFilter('{{ $key }}')"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors
                            {{ $statusFilter === $key
                                ? 'bg-red-600 border-red-600 text-white'
                                : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex-1 min-w-[14rem]">
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input"
                   placeholder="Search by name, username, email or job title...">
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        @if ($employees->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="text-gray-900 font-medium">
                    {{ trim($search) !== '' || $statusFilter !== 'all' ? 'Nobody matches that' : 'No employees yet' }}
                </p>
                <p class="text-sm text-gray-600 mt-1">
                    {{ trim($search) !== '' || $statusFilter !== 'all'
                        ? 'Try a different search or filter.'
                        : 'Add the first one and the system creates their sign-in with it.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Job title</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr wire:key="emp-{{ $employee->employee_id }}">
                                <td>
                                    <div class="font-medium text-gray-900">{{ $employee->full_name }}</div>
                                    <div class="text-sm text-gray-600">{{ $employee->email }}</div>
                                    @if ($employee->must_change_password)
                                        <div class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-amber-700">
                                            <i class="fas fa-key"></i> Has not set their own password yet
                                        </div>
                                    @endif
                                </td>
                                <td class="text-gray-700">{{ $employee->job_title }}</td>
                                <td class="text-gray-600">{{ $employee->department_name ?? '—' }}</td>
                                <td class="text-gray-600">{{ $roles[$employee->role] ?? $employee->role }}</td>
                                <td>
                                    <span class="status-badge
                                        @if ($employee->status === 'active') status-active
                                        @elseif ($employee->status === 'on_leave') status-onleave
                                        @elseif ($employee->status === 'terminated') status-terminated
                                        @else status-inactive @endif">
                                        {{ $statuses[$employee->status] ?? $employee->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="edit({{ $employee->employee_id }})"
                                                class="px-2.5 py-1.5 text-sm text-gray-700 hover:text-gray-900" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button wire:click="resetPassword({{ $employee->employee_id }})"
                                                wire:confirm="Issue a new first password? The current one stops working immediately."
                                                class="px-2.5 py-1.5 text-sm text-gray-700 hover:text-gray-900" title="Issue a new password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <button wire:click="confirmRemove({{ $employee->employee_id }})"
                                                class="px-2.5 py-1.5 text-sm text-gray-500 hover:text-red-600" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($employees->hasPages())
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $employees->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Asked before anything happens, and specific about what will: the two
         outcomes are very different, and only one of them is reversible. --}}
    @if ($removing)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="cancelRemove"></div>

                <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Remove {{ $removing['name'] }}?
                        </h2>
                    </div>

                    <div class="px-6 py-5 space-y-3 text-sm text-gray-700">
                        @if ($removing['deletes'])
                            <p>
                                They have never been paid through this system, so this deletes them outright:
                                the employee record, the sign-in account,
                                {{ $removing['attendance'] }} attendance day(s) and
                                {{ $removing['documents'] }} document(s) in their vault.
                            </p>
                            <p class="font-semibold text-red-700">This cannot be undone.</p>
                        @else
                            <p>
                                They have {{ $removing['payslips'] }} payslip(s), which the company has to keep,
                                so they are not deleted. They will be marked inactive and will no longer be able
                                to sign in. Their attendance, payslips and documents stay as they are.
                            </p>
                            <p class="text-gray-600">Set their status back to active under Edit to undo this.</p>
                        @endif
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button wire:click="cancelRemove" type="button"
                                class="px-4 py-2 text-sm font-semibold text-gray-700 hover:text-gray-900">Cancel</button>
                        <button wire:click="remove" type="button"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                                style="background: var(--brand)">
                            {{ $removing['deletes'] ? 'Delete permanently' : 'Deactivate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="closeModal"></div>

                <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $editingId ? 'Edit employee' : 'Add employee' }}
                        </h2>
                        <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        @unless ($editingId)
                            <p class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                This creates their sign-in as well. A first password is
                                generated and shown once, for you to hand over.
                            </p>
                        @endunless

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="full_name">Full name</label>
                                <input id="full_name" type="text" wire:model="full_name" class="form-input">
                                @error('full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="job_title">Job title</label>
                                <input id="job_title" type="text" wire:model="job_title" class="form-input">
                                @error('job_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="username">Username</label>
                                <input id="username" type="text" wire:model="username" class="form-input">
                                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="email">Email</label>
                                <input id="email" type="email" wire:model="email" class="form-input">
                                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="shift_start">Shift starts</label>
                                <input id="shift_start" type="time" wire:model="shift_start" class="form-input">
                                <p class="mt-1 text-xs text-gray-500">
                                    Lateness is measured from this. Leave blank and they are never marked late.
                                </p>
                                @error('shift_start') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="shift_end">Shift ends</label>
                                <input id="shift_end" type="time" wire:model="shift_end" class="form-input">
                                @error('shift_end') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <span class="form-label">Rest days</span>
                                <div class="flex flex-wrap gap-3 mt-1">
                                    @foreach (WorkWeek::DAYS as $number => $name)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" value="{{ $number }}" wire:model="rest_days" class="rounded border-gray-300">
                                            {{ substr($name, 0, 3) }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    The shift calendar is drawn from these, so nothing is entered per date.
                                </p>
                                @error('rest_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label" for="immersion_until">Work immersion until</label>
                                <input id="immersion_until" type="date" wire:model="immersion_until" class="form-input">
                                <p class="mt-1 text-xs text-gray-500">
                                    Leave blank for a regular employee. While this date is in the future they are
                                    paid in full - no SSS, PhilHealth, Pag-IBIG or tax - and payroll goes back to
                                    normal on the first cutoff that starts after it, without anybody changing this.
                                </p>
                                @error('immersion_until') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="form-label" for="biometric_id">Scanner ID</label>
                                <input id="biometric_id" type="text" wire:model="biometric_id" class="form-input"
                                       placeholder="e.g. 14">
                                <p class="mt-1 text-xs text-gray-500">
                                    The number they are enrolled under on the fingerprint scanner.
                                    Without it their scans cannot be matched to them.
                                </p>
                                @error('biometric_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="form-label mb-0" for="department_id">Department</label>
                                    <button type="button" wire:click="toggleAddDepartment"
                                            class="text-sm text-red-600 hover:text-red-700 font-medium">
                                        {{ $addingDepartment ? 'Cancel' : '+ New department' }}
                                    </button>
                                </div>

                                @if ($addingDepartment)
                                    <div class="mt-1 flex items-start gap-2">
                                        <div class="flex-1">
                                            <input type="text" wire:model="inlineDepartment" wire:keydown.enter="createDepartment"
                                                   class="form-input" placeholder="Name of the new department" autofocus>
                                            @error('inlineDepartment')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <button type="button" wire:click="createDepartment" class="btn-secondary">Create</button>
                                    </div>
                                @else
                                <select id="department_id" wire:model="department_id" class="form-input">
                                    <option value="">Not specified</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->department_id }}">{{ $department->department_name }}</option>
                                    @endforeach
                                </select>
                                @endif
                            </div>
                            <div>
                                <label class="form-label" for="role">Role</label>
                                <select id="role" wire:model="role" class="form-input">
                                    @foreach ($roles as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="hire_date">Hire date</label>
                                <input id="hire_date" type="date" wire:model="hire_date" class="form-input">
                                @error('hire_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="salary">Monthly salary</label>
                                <input id="salary" type="number" step="0.01" min="0" wire:model="salary"
                                       class="form-input" placeholder="0.00">
                                @error('salary') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="status">Status</label>
                                <select id="status" wire:model="status" class="form-input">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
                        <button wire:click="closeModal" class="btn-secondary">Cancel</button>
                        <button wire:click="save" class="btn-primary">
                            {{ $editingId ? 'Save changes' : 'Create employee' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
