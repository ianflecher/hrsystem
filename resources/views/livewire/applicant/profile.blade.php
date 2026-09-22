<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The application form - the whole 201 file, filled in once.
 *
 * Everything keys on user_id rather than on an application, so somebody who
 * applies twice does not retype it and somebody who is hired arrives with it
 * already complete.
 *
 * The four disclosure sections at the end hold sensitive personal information
 * under the Data Privacy Act. They are written here by the person they are
 * about and read in the back office by HR; nothing else on the site touches
 * them.
 */
new #[Layout('components.layouts.applicant')] class extends Component
{
    public array $p = [];          // the profile
    public array $edu = [];        // one entry per level
    public array $jobs = [];       // previous employers, repeatable
    public array $refs = [];       // character references, optional
    public array $relatives = [];  // relatives working here
    public array $d = [];          // the disclosures

    public array $civilStatuses = [
        'single'    => 'Single',
        'married'   => 'Married',
        'live_in'   => 'Live-in',
        'widowed'   => 'Widowed',
        'separated' => 'Separated',
    ];

    /** Both shapes of secondary school: K-12 split, and plain high school. */
    public array $levels = [
        'elementary'  => 'Elementary',
        'junior_high' => 'Junior high school',
        'senior_high' => 'Senior high school',
        'high_school' => 'High school (if not split into junior and senior)',
        'vocational'  => 'Vocational',
        'tertiary'    => 'College / tertiary',
    ];

    public function mount(): void
    {
        $id = Auth::id();

        $row = DB::table('applicant_profiles')->where('user_id', $id)->first();
        $this->p = $row ? (array) $row : $this->blankProfile();

        // Sensible starting values from the account itself, so the form opens
        // part-filled rather than empty.
        if (! $row) {
            $user = Auth::user();
            $this->p['email_address'] = $user->email;
            $parts = preg_split('/\s+/', trim((string) $user->full_name));
            $this->p['first_name'] = $parts[0] ?? '';
            $this->p['surname'] = count($parts) > 1 ? array_pop($parts) : '';
        }

        foreach (array_keys($this->levels) as $level) {
            $e = DB::table('applicant_education')->where('user_id', $id)->where('level', $level)->first();
            $this->edu[$level] = [
                'school_name' => $e->school_name ?? '',
                'course'      => $e->course ?? '',
                'year_from'   => $e->year_from ?? '',
                'year_to'     => $e->year_to ?? '',
            ];
        }

        $this->jobs = DB::table('applicant_employment')->where('user_id', $id)
            ->orderBy('sort_order')->get()->map(fn ($j) => (array) $j)->all();
        if (! $this->jobs) {
            $this->addJob();
        }

        $this->refs = DB::table('applicant_references')->where('user_id', $id)
            ->orderBy('sort_order')->get()->map(fn ($r) => (array) $r)->all();

        $this->relatives = DB::table('applicant_relatives')->where('user_id', $id)
            ->get()->map(fn ($r) => (array) $r)->all();

        $dis = DB::table('applicant_disclosures')->where('user_id', $id)->first();
        $this->d = $dis ? (array) $dis : $this->blankDisclosures();
    }

    private function blankProfile(): array
    {
        return array_fill_keys([
            'surname', 'first_name', 'middle_name', 'present_address', 'permanent_address',
            'cellphone', 'email_address', 'bank_account_number', 'date_of_birth',
            'birthplace', 'civil_status', 'spouse_surname', 'spouse_first_name', 'spouse_middle_name',
            'fathers_name', 'mothers_maiden_name', 'siblings', 'sss_number', 'pagibig_number',
            'philhealth_number', 'tin', 'emergency_name', 'emergency_contact_no',
            'emergency_relationship', 'emergency_address', 'certified_name',
        ], '') + ['permanent_same_as_present' => false];
    }

    private function blankDisclosures(): array
    {
        return array_fill_keys([
            'has_medical_condition', 'takes_maintenance_medication', 'has_relative_employed',
            'ever_terminated', 'ever_convicted', 'employed_elsewhere', 'has_employment_bond',
            'was_union_member', 'can_start_immediately', 'sss_on_file', 'pagibig_on_file',
            'philhealth_on_file', 'tin_on_file',
        ], null) + array_fill_keys([
            'medical_condition_details', 'maintenance_medication_details', 'ever_terminated_details',
            'ever_convicted_details', 'employment_bond_details', 'union_position',
            'days_to_render', 'available_start_date', 'declared_name',
        ], '');
    }

    public function addJob(): void
    {
        $this->jobs[] = ['company_name' => '', 'company_address' => '', 'position' => '',
                         'date_from' => '', 'date_to' => '', 'reason_for_leaving' => '',
                         'daily_salary' => ''];
    }

    public function removeJob(int $i): void
    {
        unset($this->jobs[$i]);
        $this->jobs = array_values($this->jobs);
    }

    public function addRef(): void
    {
        $this->refs[] = ['name' => '', 'contact_no' => '', 'position_company' => ''];
    }

    public function removeRef(int $i): void
    {
        unset($this->refs[$i]);
        $this->refs = array_values($this->refs);
    }

    public function addRelative(): void
    {
        $this->relatives[] = ['name' => '', 'relationship' => '', 'department' => ''];
    }

    public function removeRelative(int $i): void
    {
        unset($this->relatives[$i]);
        $this->relatives = array_values($this->relatives);
    }

    /** Copies the present address down, so it is not typed twice. */
    public function updatedP($value, $key): void
    {
        if ($key === 'permanent_same_as_present' && $value) {
            $this->p['permanent_address'] = $this->p['present_address'] ?? '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'p.surname'       => ['required', 'string', 'max:100'],
            'p.first_name'    => ['required', 'string', 'max:100'],
            'p.middle_name'   => ['nullable', 'string', 'max:100'],
            'p.cellphone'     => ['nullable', 'string', 'max:40'],
            'p.email_address' => ['nullable', 'email', 'max:150'],
            'p.date_of_birth' => ['nullable', 'date', 'before:today'],
            'p.civil_status'  => ['nullable', 'in:'.implode(',', array_keys($this->civilStatuses))],
            'jobs.*.company_name' => ['nullable', 'string', 'max:180'],
            'jobs.*.daily_salary' => ['nullable', 'numeric', 'min:0'],
            'refs.*.name'         => ['nullable', 'string', 'max:150'],
            'relatives.*.name'    => ['nullable', 'string', 'max:150'],
            'd.days_to_render'    => ['nullable', 'integer', 'min:0', 'max:365'],
            'd.available_start_date' => ['nullable', 'date'],
        ], [], [
            'p.surname'    => 'surname',
            'p.first_name' => 'first name',
        ]);

        $id = Auth::id();

        DB::transaction(function () use ($id) {
            $profile = collect($this->p)
                ->only(array_keys($this->blankProfile()))
                ->map(fn ($v) => $v === '' ? null : $v)
                ->all();
            $profile['permanent_same_as_present'] = (bool) ($this->p['permanent_same_as_present'] ?? false);
            $profile['updated_at'] = now();

            if (DB::table('applicant_profiles')->where('user_id', $id)->exists()) {
                DB::table('applicant_profiles')->where('user_id', $id)->update($profile);
            } else {
                DB::table('applicant_profiles')->insert($profile + ['user_id' => $id, 'created_at' => now()]);
            }

            // Education is a fixed set of levels, so each is written in place
            // and a level left blank is removed rather than stored empty.
            foreach ($this->edu as $level => $e) {
                $filled = array_filter($e, fn ($v) => trim((string) $v) !== '');

                if (! $filled) {
                    DB::table('applicant_education')->where('user_id', $id)->where('level', $level)->delete();
                    continue;
                }

                DB::table('applicant_education')->updateOrInsert(
                    ['user_id' => $id, 'level' => $level],
                    $e + ['updated_at' => now(), 'created_at' => now()]
                );
            }

            $this->rewrite('applicant_employment', $id, $this->jobs,
                ['company_name', 'company_address', 'position', 'date_from', 'date_to',
                 'reason_for_leaving', 'daily_salary'], 'company_name');

            $this->rewrite('applicant_references', $id, $this->refs,
                ['name', 'contact_no', 'position_company'], 'name');

            $this->rewrite('applicant_relatives', $id, $this->relatives,
                ['name', 'relationship', 'department'], 'name', ordered: false);

            $dis = collect($this->d)
                ->only(array_keys($this->blankDisclosures()))
                ->map(fn ($v) => $v === '' ? null : $v)
                ->all();
            $dis['updated_at'] = now();

            if (DB::table('applicant_disclosures')->where('user_id', $id)->exists()) {
                DB::table('applicant_disclosures')->where('user_id', $id)->update($dis);
            } else {
                DB::table('applicant_disclosures')->insert($dis + ['user_id' => $id, 'created_at' => now()]);
            }
        });

        session()->flash('success', 'Your details have been saved.');
    }

    /**
     * Replaces a repeatable set outright.
     *
     * Rows have no stable identity once somebody adds and removes entries in
     * the browser, so matching them up would invent a correspondence that does
     * not exist. Deleting and rewriting is the honest version, and these sets
     * are a handful of rows.
     */
    private function rewrite(string $table, int $id, array $rows, array $columns,
                            string $required, bool $ordered = true): void
    {
        DB::table($table)->where('user_id', $id)->delete();

        $order = 0;
        foreach ($rows as $row) {
            if (trim((string) ($row[$required] ?? '')) === '') {
                continue;
            }

            $insert = [];
            foreach ($columns as $c) {
                $insert[$c] = ($row[$c] ?? '') === '' ? null : $row[$c];
            }

            if ($ordered) {
                $insert['sort_order'] = $order++;
            }

            DB::table($table)->insert($insert + ['user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Stamps the certification with the moment it was agreed to. */
    public function certify(): void
    {
        $this->validate(['p.certified_name' => ['required', 'string', 'max:150']],
            [], ['p.certified_name' => 'printed name']);

        $this->save();

        DB::table('applicant_profiles')->where('user_id', Auth::id())
            ->update(['certified_name' => $this->p['certified_name'], 'certified_at' => now()]);

        $this->p['certified_at'] = now();
        session()->flash('success', 'Certified and saved.');
    }

    public function declare(): void
    {
        $this->validate(['d.declared_name' => ['required', 'string', 'max:150']],
            [], ['d.declared_name' => 'printed name']);

        $this->save();

        DB::table('applicant_disclosures')->where('user_id', Auth::id())
            ->update(['declared_name' => $this->d['declared_name'], 'declared_at' => now()]);

        $this->d['declared_at'] = now();
        session()->flash('success', 'Declaration recorded.');
    }
}; ?>

<div class="p-6 md:p-8 max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Application details</h1>
        <p class="text-sm text-gray-600 mt-1">
            Filled in once. It stays with your account, so you will not be asked for it again
            if you apply for another role &mdash; and it becomes your record here if you are hired.
            Where something does not apply to you, write <strong>N/A</strong>.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @include('partials.applicant-profile-personal')
    @include('partials.applicant-profile-education')
    @include('partials.applicant-profile-employment')
    @include('partials.applicant-profile-disclosures')

    <div class="mt-8 flex justify-end">
        <button wire:click="save" class="btn-primary">Save everything</button>
    </div>
</div>
