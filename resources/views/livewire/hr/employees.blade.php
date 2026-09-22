<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\DocumentVault;
use App\Support\PeopleAccess;
use App\Services\SalaryHistory;
use App\Support\WorkWeek;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    use WithPagination;

    public $departments = [];
    /** Creating a department from the form that wanted one. */
    public bool $showDepartmentDialog = false;
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
    public $allowance = '';
    /** Why the pay changed. Only asked for when it actually has. */
    public string $payChangeReason = '';
    /** What they were on when the form opened, to spot a change. */
    public $payWas = null;
    public string $status = 'active';
    public string $employment_type = 'Regular';
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
        'awol'       => 'AWOL',
    ];

    /**
     * What somebody is engaged as, which is not the same question as whether
     * they still work here. A regular employee can be AWOL; an OJT is still an
     * OJT on the day they leave.
     *
     * Not to be confused with the employment type on a job posting, which uses
     * full-time/part-time/contract and describes a vacancy rather than a
     * person.
     */
    public array $employmentTypes = [
        'Regular'       => 'Regular',
        'Probation'     => 'Probation',
        'Immersion'     => 'Immersion',
        'Seasonal'      => 'Seasonal',
        'Project-Based' => 'Project-Based',
        'Part-Timers'   => 'Part-Timers',
        'OJT'           => 'OJT',
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
                'e.employee_id', 'e.job_title', 'e.status', 'e.hire_date', 'e.salary', 'e.allowance',
                'e.employment_type',
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

        // By surname, the way a staff list is read. Anyone without their name
        // in parts - the two admin accounts - falls back to the whole thing
        // rather than sorting to the top under an empty string.
        return $query
            ->orderByRaw("COALESCE(NULLIF(u.last_name, ''), u.full_name)")
            ->orderByRaw("COALESCE(NULLIF(u.first_name, ''), u.full_name)");
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
        $this->employment_type = $this->knownEmploymentType($row->employment_type);
        $this->payWas        = ['salary' => (float) $row->salary, 'allowance' => (float) ($row->allowance ?? 0)];
        $this->allowance     = $row->allowance;
        $this->status        = $row->status;
        $this->role          = $row->role;
        $this->showModal     = true;
    }

    /* ------------------------------------------------ managing departments */

    public bool $showDepartments = false;
    public string $newDepartment = '';
    public ?int $renamingDepartment = null;
    public string $renameDepartmentTo = '';

    /** Departments with how many people are in each, for the panel. */
    public function departmentRoll(): \Illuminate\Support\Collection
    {
        return DB::table('departments as d')
            ->leftJoin('employees as e', 'e.department_id', '=', 'd.department_id')
            ->groupBy('d.department_id', 'd.department_name')
            ->orderBy('d.department_name')
            ->selectRaw('d.department_id, d.department_name, COUNT(e.employee_id) as headcount')
            ->get();
    }

    public function toggleDepartments(): void
    {
        $this->showDepartments = ! $this->showDepartments;
        $this->renamingDepartment = null;
        $this->newDepartment = '';
        $this->resetValidation();
    }

    public function addDepartment(): void
    {
        PeopleAccess::hr();

        $this->validate(
            ['newDepartment' => ['required', 'string', 'max:100', Rule::unique('departments', 'department_name')]],
            [],
            ['newDepartment' => 'department name'],
        );

        DB::table('departments')->insert([
            'department_name' => trim($this->newDepartment),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        session()->flash('success', trim($this->newDepartment).' added.');
        $this->newDepartment = '';
        $this->loadDepartments();
    }

    public function startRename(int $id, string $current): void
    {
        $this->renamingDepartment = $id;
        $this->renameDepartmentTo = $current;
        $this->resetValidation();
    }

    public function saveRename(): void
    {
        PeopleAccess::hr();

        if (! $this->renamingDepartment) {
            return;
        }

        $this->validate(
            ['renameDepartmentTo' => ['required', 'string', 'max:100',
                Rule::unique('departments', 'department_name')->ignore($this->renamingDepartment, 'department_id')]],
            [],
            ['renameDepartmentTo' => 'department name'],
        );

        DB::table('departments')->where('department_id', $this->renamingDepartment)
            ->update(['department_name' => trim($this->renameDepartmentTo), 'updated_at' => now()]);

        session()->flash('success', 'Renamed to '.trim($this->renameDepartmentTo).'.');
        $this->renamingDepartment = null;
        $this->loadDepartments();
    }

    /**
     * A department with people in it is not deleted.
     *
     * The version this replaced emptied it first - it set department_id to
     * null for everybody in it and then deleted the row - so one click on a
     * trash icon could quietly unassign thirty-one people, and nothing on the
     * screen said so beforehand. Moving them somewhere is a decision, not a
     * side effect of tidying a list.
     */
    public function deleteDepartment(int $id): void
    {
        PeopleAccess::hr();

        $name = DB::table('departments')->where('department_id', $id)->value('department_name');

        if ($name === null) {
            return;
        }

        $headcount = DB::table('employees')->where('department_id', $id)->count();

        if ($headcount > 0) {
            session()->flash('error', $name.' still has '.$headcount.' '
                .Str::plural('person', $headcount).' in it. Move them to another department first.');

            return;
        }

        DB::table('departments')->where('department_id', $id)->delete();
        session()->flash('success', $name.' deleted.');
        $this->loadDepartments();
    }

    /**
     * The stored value, matched to one we offer.
     *
     * The column default is a lowercase 'regular' and rows created before the
     * list existed carry it, so a strict comparison would refuse to save
     * anybody who had never been edited - the form would reject a value it had
     * just loaded itself.
     */
    private function knownEmploymentType(?string $stored): string
    {
        $stored = trim((string) $stored);

        foreach (array_keys($this->employmentTypes) as $known) {
            if (strcasecmp($known, $stored) === 0) {
                return $known;
            }
        }

        return 'Regular';
    }

    public function openDepartmentDialog(): void
    {
        $this->inlineDepartment = '';
        $this->resetValidation('inlineDepartment');
        $this->showDepartmentDialog = true;
    }

    public function closeDepartmentDialog(): void
    {
        $this->showDepartmentDialog = false;
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
        $this->closeDepartmentDialog();
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
            'allowance'     => ['nullable', 'numeric', 'min:0'],
            'status'        => ['required', Rule::in(array_keys($this->statuses))],
            'employment_type' => ['required', Rule::in(array_keys($this->employmentTypes))],
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
        // Kept apart from basic because the two are charged on differently:
        // SSS, PhilHealth and the 13th month read the basic alone, while tax
        // and Pag-IBIG read the two together. Folding one into the other would
        // quietly change all five.
        $allowance = $data['allowance'] === '' || $data['allowance'] === null ? 0 : $data['allowance'];

        if ($this->editingId) {
            DB::transaction(function () use ($data, $departmentId, $shiftStart, $shiftEnd, $restDays, $immersionUntil, $biometricId, $salary, $allowance, $userId) {
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
                    'biometric_id'  => $biometricId,
                    'department_id' => $departmentId,
                    'hire_date'     => $data['hire_date'],
                    'salary'        => $salary,
                    'allowance'     => $allowance,
                    'status'        => $data['status'],
                    'employment_type' => $data['employment_type'],
                    'updated_at'    => now(),
                ]);

                // A raise is logged, not overwritten - but only a raise. An
                // employee with no history yet has not had a pay change just
                // because somebody edited their shift, and writing a baseline
                // entry dated today would claim their pay moved when it did
                // not. The figures the form opened with are what decides it.
                $payMoved = $this->payWas === null
                    || round((float) $salary, 2) !== round((float) $this->payWas['salary'], 2)
                    || round((float) $allowance, 2) !== round((float) $this->payWas['allowance'], 2);

                if ($payMoved) {
                    SalaryHistory::record(
                        employeeId: $this->editingId,
                        salary: (float) $salary,
                        allowance: (float) $allowance,
                        reason: $this->payChangeReason !== '' ? $this->payChangeReason : null,
                    );
                }
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

        DB::transaction(function () use ($data, $departmentId, $shiftStart, $shiftEnd, $restDays, $immersionUntil, $biometricId, $salary, $allowance, $password) {
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
                'allowance'     => $allowance,
                'status'        => $data['status'],
                'employment_type' => $data['employment_type'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // The pay they start on is the first entry in the log.
            SalaryHistory::record(
                employeeId: $employeeId,
                salary: (float) $salary,
                allowance: (float) $allowance,
                effectiveFrom: $data['hire_date'],
                reason: 'Starting pay',
            );

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
        $this->employment_type = 'Regular';
        $this->payChangeReason = '';
        $this->payWas        = null;
        $this->allowance     = '';
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
        <div class="flex flex-wrap gap-2">
            {{-- Departments belong here, beside the people in them, rather
                 than on the applications screen where they used to live. --}}
            <button wire:click="toggleDepartments" class="btn-secondary">
                <i class="fas fa-sitemap"></i> Departments
            </button>
            <button wire:click="openCreate" class="btn-primary">
                <i class="fas fa-user-plus"></i> Add employee
            </button>
        </div>
    </div>

    @if ($showDepartments)
        @php $roll = $this->departmentRoll(); @endphp
        <div class="mb-6 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-gray-800">Departments</h2>
                    <p class="text-sm text-gray-600">{{ $roll->count() }} in all, {{ $roll->sum('headcount') }} people assigned</p>
                </div>
                <button wire:click="toggleDepartments" class="text-gray-400 hover:text-gray-600" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-5">
                <div class="flex flex-wrap gap-2 mb-4">
                    <input type="text" wire:model="newDepartment" wire:keydown.enter="addDepartment"
                           class="form-input flex-1 min-w-[14rem]" maxlength="100"
                           placeholder="New department name">
                    <button wire:click="addDepartment" class="btn-primary">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                @error('newDepartment') <p class="-mt-2 mb-3 text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach ($roll as $d)
                        <div class="border border-gray-200 rounded-lg p-3">
                            @if ($renamingDepartment === (int) $d->department_id)
                                <input type="text" wire:model="renameDepartmentTo" wire:keydown.enter="saveRename"
                                       class="form-input text-sm" maxlength="100">
                                @error('renameDepartmentTo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <div class="flex gap-2 mt-2">
                                    <button wire:click="saveRename" class="text-sm text-career-700 font-medium">Save</button>
                                    <button wire:click="$set('renamingDepartment', null)" class="text-sm text-gray-500">Cancel</button>
                                </div>
                            @else
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ $d->department_name }}</p>
                                        <p class="text-sm text-gray-500 mt-0.5">
                                            <i class="fas fa-users mr-1 text-gray-400"></i>{{ $d->headcount }}
                                            {{ Str::plural('person', $d->headcount) }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <button wire:click="startRename({{ $d->department_id }}, '{{ addslashes($d->department_name) }}')"
                                                class="text-gray-400 hover:text-gray-700" title="Rename">
                                            <i class="fas fa-pen text-sm"></i>
                                        </button>
                                        {{-- Only offered when it would not strand anybody. --}}
                                        @if ($d->headcount === 0)
                                            <button wire:click="deleteDepartment({{ $d->department_id }})"
                                                    wire:confirm="Delete {{ $d->department_name }}?"
                                                    class="text-gray-400 hover:text-red-600" title="Delete">
                                                <i class="fas fa-trash text-sm"></i>
                                            </button>
                                        @else
                                            <span class="text-gray-300 cursor-not-allowed" title="Has people in it">
                                                <i class="fas fa-trash text-sm"></i>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

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
                            <th>Type</th>
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
                                    <span class="text-sm text-gray-700">{{ $employee->employment_type ?: '-' }}</span>
                                </td>
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
        <div class="fixed inset-0 z-[70] overflow-y-auto">
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
        <div class="fixed inset-0 z-[70] overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="closeModal"></div>

                <div class="relative w-full max-w-4xl bg-white rounded-xl shadow-xl">
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

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
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
                                    Lateness is measured from this. Blank means never late.
                                </p>
                                @error('shift_start') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="shift_end">Shift ends</label>
                                <input id="shift_end" type="time" wire:model="shift_end" class="form-input">
                                @error('shift_end') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2 lg:col-span-3">
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
                                    The shift calendar is drawn from these.
                                </p>
                                @error('rest_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="immersion_until">Work immersion until</label>
                                <input id="immersion_until" type="date" wire:model="immersion_until" class="form-input">
                                <p class="mt-1 text-xs text-gray-500">
                                    Blank for a regular employee. Until this date they are paid in full - no SSS,
                                    PhilHealth, Pag-IBIG or tax - and payroll returns to normal by itself after.
                                </p>
                                @error('immersion_until') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="form-label" for="biometric_id">Scanner ID</label>
                                <input id="biometric_id" type="text" wire:model="biometric_id" class="form-input"
                                       placeholder="e.g. 14">
                                <p class="mt-1 text-xs text-gray-500">
                                    Their enrolment number on the scanner. Without it, their scans match nobody.
                                </p>
                                @error('biometric_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="form-label mb-0" for="department_id">Department</label>
                                    <button type="button" wire:click="openDepartmentDialog"
                                            class="text-sm text-red-600 hover:text-red-700 font-medium">+ New department</button>
                                </div>
                                <select id="department_id" wire:model="department_id" class="form-input">
                                    <option value="">Not specified</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->department_id }}">{{ $department->department_name }}</option>
                                    @endforeach
                                </select>
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
                            {{-- Two figures, because they are treated differently:
                                 basic carries the tax and the contributions, an
                                 allowance is paid whole. --}}
                            <div>
                                <label class="form-label" for="salary">Basic salary (monthly)</label>
                                <input id="salary" type="number" step="0.01" min="0" wire:model.live="salary"
                                       class="form-input" placeholder="0.00">
                                @error('salary') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label" for="allowance">Allowance</label>
                                <input id="allowance" type="number" step="0.01" min="0" wire:model.live="allowance"
                                       class="form-input" placeholder="0.00">
                                @error('allowance') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                @if ((float) $salary > 0 || (float) $allowance > 0)
                                    <p class="mt-1 text-xs text-gray-600">
                                        Total &#8369;{{ number_format((float) $salary + (float) $allowance, 2) }} a month
                                    </p>
                                @endif
                            </div>

                            {{-- A pay change is logged, so it is worth a line
                                 saying why. Only shown once the figures have
                                 actually moved, so an ordinary edit is not
                                 asked to justify itself. --}}
                            @php
                                $payMoved = $payWas !== null && (
                                    round((float) $salary, 2) !== round((float) $payWas['salary'], 2)
                                    || round((float) $allowance, 2) !== round((float) $payWas['allowance'], 2)
                                );
                            @endphp
                            @if ($payMoved)
                                <div class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                    <p class="text-sm text-amber-900">
                                        Pay is changing from
                                        <strong>&#8369;{{ number_format((float) $payWas['salary'] + (float) $payWas['allowance'], 2) }}</strong>
                                        to
                                        <strong>&#8369;{{ number_format((float) $salary + (float) $allowance, 2) }}</strong>
                                        a month. This is recorded against their record.
                                    </p>
                                    <label class="form-label mt-2" for="payChangeReason">Reason</label>
                                    <input id="payChangeReason" type="text" wire:model.live="payChangeReason"
                                           class="form-input" maxlength="255"
                                           placeholder="Annual increase, promotion, correction...">
                                </div>
                            @endif
                            <div>
                                {{-- What they are engaged as, which is a
                                     different question from whether they are
                                     still here. A regular employee can be
                                     AWOL; an OJT is an OJT until they leave. --}}
                                <label class="form-label" for="employment_type">Employment type</label>
                                <select id="employment_type" wire:model="employment_type" class="form-input">
                                    @foreach ($employmentTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('employment_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

    {{-- Layered above the employee/opening dialog that opened it, so the form
         underneath keeps everything already typed into it. --}}
    @if ($showDepartmentDialog)
        <div class="fixed inset-0 z-[80] overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="closeDepartmentDialog"></div>

                <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">New department</h2>
                        <button type="button" wire:click="closeDepartmentDialog"
                                class="text-gray-400 hover:text-gray-600" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="px-6 py-5">
                        <label class="form-label" for="inlineDepartment">Name</label>
                        <input id="inlineDepartment" type="text" wire:model="inlineDepartment"
                               wire:keydown.enter="createDepartment" class="form-input"
                               placeholder="e.g. Production, Store, Administration" autofocus>
                        @error('inlineDepartment')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-600">
                            It is selected here as soon as it is created. Nothing already filled in is lost.
                        </p>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
                        <button type="button" wire:click="closeDepartmentDialog" class="btn-secondary">Cancel</button>
                        <button type="button" wire:click="createDepartment" class="btn-primary">Create department</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
