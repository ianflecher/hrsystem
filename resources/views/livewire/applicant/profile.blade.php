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
    public array $siblings = [];   // one box per sibling they say they have

    /** 'k12' (junior and senior high) or 'high_school' (the old curriculum). */
    public string $secondary = '';

    /** Shown once the declaration is in: the form is done, and says so. */
    public bool $showDone = false;
    public array $d = [];          // the disclosures

    /** Nobody has more than this many, and a number field invites typos. */
    private const MAX_SIBLINGS = 20;

    /**
     * Columns that cannot hold the word "N/A": dates, numbers, enums and
     * yes/no flags. A blank one of these stays null.
     *
     * The yes/no disclosures are on this list for a second reason as well -
     * on a form somebody signs, "not answered" and "answered no" are
     * different things, and writing N/A over the difference would lose it.
     */
    private const NOT_TEXT = [
        'date_of_birth', 'civil_status', 'sibling_count', 'permanent_same_as_present',
        'certified_at', 'daily_salary', 'level', 'days_to_render', 'available_start_date',
        'declared_at', 'has_medical_condition', 'takes_maintenance_medication',
        'has_relative_employed', 'ever_terminated', 'ever_convicted', 'employed_elsewhere',
        'has_employment_bond', 'was_union_member', 'can_start_immediately',
        'sss_on_file', 'pagibig_on_file', 'philhealth_on_file', 'tin_on_file',
    ];

    /**
     * What everybody has, and must therefore give.
     *
     * These are not N/A-able: a person has a name, an address, a birthday and
     * somebody to ring if something happens to them at work. Leaving one of
     * these blank is an unfinished form, not an answer, so Next stops on it
     * rather than writing N/A over the gap.
     *
     * Everything absent from this list can genuinely not apply - no middle
     * name, no bank account yet, no SSS number for a first job - and those
     * are the ones a blank turns into N/A.
     */
    private const REQUIRED = [
        1 => [
            'surname'           => 'surname',
            'first_name'        => 'first name',
            'present_street'    => 'present address',
            'present_city'      => 'city',
            'present_province'  => 'province',
            'permanent_street'  => 'permanent address',
            'permanent_city'    => 'permanent city',
            'permanent_province' => 'permanent province',
            'cellphone'         => 'cellphone number',
            'email_address'     => 'email address',
            'date_of_birth'     => 'date of birth',
            'birthplace'        => 'birthplace',
            'civil_status'      => 'civil status',
        ],
        2 => [
            'emergency_name'         => 'emergency contact name',
            'emergency_contact_no'   => 'emergency contact number',
            'emergency_relationship' => 'relationship to that person',
            'emergency_address'      => 'their address',
        ],
    ];

    /**
     * Ways of writing "nothing here". Accepted in an optional box, refused in
     * a required one: N/A is not a birthplace.
     */
    private const NA_WORDS = [
        'n/a', 'na', 'n.a.', 'n.a', 'n\a', 'none', 'nil', 'wala', 'not applicable',
        '-', '--', '.', 'x',
    ];

    private function saysNothing(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), self::NA_WORDS, true);
    }

    /**
     * The form tells people to write N/A where something does not apply to
     * them. Most leave the box empty instead and mean the same thing, so a
     * blank optional column is stored as N/A - it reads back as an answer
     * given, which is what it was.
     *
     * Required columns are never treated this way. N/A is not an answer to
     * "what is your date of birth", and writing it would turn an unfinished
     * form into one that looks complete.
     */
    private function naIfBlank(mixed $value, string $column): mixed
    {
        if ($value !== '' && $value !== null) {
            return $value;
        }

        if (in_array($column, self::NOT_TEXT, true) || $this->isRequired($column)) {
            return null;
        }

        return 'N/A';
    }

    private function isRequired(string $column): bool
    {
        foreach (self::REQUIRED as $fields) {
            if (array_key_exists($column, $fields)) {
                return true;
            }
        }

        return false;
    }

    /** Which of the four steps is on screen. */
    public int $step = 1;

    public array $steps = [
        1 => 'Personal details',
        2 => 'Government IDs',
        3 => 'Education',
        4 => 'Employment',
        5 => 'Disclosures',
    ];

    public array $civilStatuses = [
        'single'    => 'Single',
        'married'   => 'Married',
        'live_in'   => 'Live-in',
        'widowed'   => 'Widowed',
        'separated' => 'Separated',
    ];

    /**
     * Both shapes of secondary school. Which of the two a person sees depends
     * on the one question asked above them, so nobody is shown junior high,
     * senior high and plain high school all at once and left to guess which
     * rows are theirs.
     */
    public array $levels = [
        'elementary'  => 'Elementary',
        'junior_high' => 'Junior high school',
        'senior_high' => 'Senior high school',
        'high_school' => 'High school',
        'vocational'  => 'Vocational',
        'tertiary'    => 'College / tertiary',
    ];

    /** The levels on screen, given the answer to that question. */
    public function shownLevels(): array
    {
        $hide = match ($this->secondary) {
            'k12'         => ['high_school'],
            'high_school' => ['junior_high', 'senior_high'],
            default       => ['junior_high', 'senior_high', 'high_school'],
        };

        return array_diff_key($this->levels, array_flip($hide));
    }

    /**
     * Switching curriculum empties the rows that no longer apply, so a person
     * who fills in K-12 and then says they took plain high school does not
     * leave three secondary schools behind them in the record.
     */
    public function updatedSecondary(): void
    {
        $drop = match ($this->secondary) {
            'k12'         => ['high_school'],
            'high_school' => ['junior_high', 'senior_high'],
            default       => [],
        };

        foreach ($drop as $level) {
            $this->edu[$level] = ['school_name' => '', 'course' => '', 'year_from' => '', 'year_to' => ''];
        }
    }

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

        $filled = fn (string $level) => trim((string) ($this->edu[$level]['school_name'] ?? '')) !== '';

        $this->secondary = match (true) {
            $filled('high_school') => 'high_school',
            $filled('junior_high') || $filled('senior_high') => 'k12',
            default => '',
        };

        $this->jobs = DB::table('applicant_employment')->where('user_id', $id)
            ->orderBy('sort_order')->get()->map(fn ($j) => (array) $j)->all();
        if (! $this->jobs) {
            $this->addJob();
        }

        $this->refs = DB::table('applicant_references')->where('user_id', $id)
            ->orderBy('sort_order')->get()->map(fn ($r) => (array) $r)->all();

        $this->relatives = DB::table('applicant_relatives')->where('user_id', $id)
            ->get()->map(fn ($r) => (array) $r)->all();

        $this->siblings = DB::table('applicant_siblings')->where('user_id', $id)
            ->orderBy('sort_order')->get()->map(fn ($r) => (array) $r)->all();

        // Somebody who said four and typed two names still has four siblings,
        // so the count leads and the boxes follow it.
        $this->resizeSiblings((int) ($this->p['sibling_count'] ?? count($this->siblings)));

        $dis = DB::table('applicant_disclosures')->where('user_id', $id)->first();
        $this->d = $dis ? (array) $dis : $this->blankDisclosures();
    }

    private function blankProfile(): array
    {
        return array_fill_keys([
            'surname', 'first_name', 'middle_name',
            'present_street', 'present_city', 'present_province',
            'permanent_street', 'permanent_city', 'permanent_province',
            'cellphone', 'email_address', 'bank_account_number', 'date_of_birth',
            'birthplace', 'civil_status', 'spouse_surname', 'spouse_first_name', 'spouse_middle_name',
            'fathers_name', 'mothers_maiden_name',
            'sibling_count', 'sss_number', 'pagibig_number',
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

    /**
     * Forward saves first, so a completed step is a kept step. If the save
     * fails validation the person stays where they are and sees why.
     */
    public function next(): void
    {
        $this->validateStep($this->step);

        $this->save();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Checked after the save rather than before it: save() validates, and
        // validating empties the error bag, so an error added earlier would be
        // wiped on the way past. Saving anyway is harmless - the step is kept,
        // it just does not advance.
        if ($this->step === 3 && $this->secondary === '') {
            $this->addError('secondary', 'Please say which secondary schooling you took.');

            return;
        }

        if ($this->step === 3 && ! $this->hasSchooling()) {
            $this->addError('edu', 'Please fill in at least one level of schooling.');

            return;
        }

        // Somebody raised them. An adopted person has parents or a guardian,
        // and either one answers this - but not neither. Checked here rather
        // than as a rule because a closure rule is skipped when the value is
        // empty, which is exactly the case being caught.
        if ($this->step === 1 && ! $this->hasAParent()) {
            $this->addError('p.fathers_name',
                "Please give at least one: your father's name, your mother's maiden name, or your guardian's.");

            return;
        }

        $this->step = min($this->step + 1, count($this->steps));
    }

    /**
     * What this step will not let through. Save is deliberately not held to
     * it - somebody stopping half way should keep what they have typed - but
     * moving on means this step is done.
     */
    private function validateStep(int $step): void
    {
        $rules = [];
        $names = [];

        foreach (self::REQUIRED[$step] ?? [] as $column => $label) {
            // "required" only asks for something rather than nothing, and N/A
            // is something. These are all facts every person has, so a box
            // holding the word N/A is still an unanswered box.
            $rules['p.'.$column] = ['required', function ($attribute, $value, $fail) use ($label) {
                if ($this->saysNothing($value)) {
                    $fail('Please give your actual '.$label.'.');
                }
            }];
            $names['p.'.$column] = $label;
        }


        if ($rules) {
            $this->validate($rules, [], $names);
        }
    }

    /** Back never validates - correcting an earlier typo must not be blocked. */
    public function back(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    /** From the dialog: back to the application this was all for. */
    public function finish()
    {
        return redirect()->route('applicant.index');
    }

    public function goToStep(int $step): void
    {
        $this->step = max(1, min($step, count($this->steps)));
    }

    /**
     * The same fact, asked twice: step two asks for the number, the
     * disclosures ask whether there is one. Typing a number answers both, and
     * so does leaving it blank or writing N/A - so the answer follows the box
     * rather than waiting to be clicked, and the two cannot contradict.
     *
     * Still a radio rather than a read-only line, because somebody who has an
     * SSS number to hand but has not typed it in should be able to say so.
     */
    private const ID_ANSWERS = [
        'sss_number'        => 'sss_on_file',
        'pagibig_number'    => 'pagibig_on_file',
        'philhealth_number' => 'philhealth_on_file',
        'tin'               => 'tin_on_file',
    ];

    private function idIsGiven(string $numberColumn): bool
    {
        $value = $this->p[$numberColumn] ?? '';

        return trim((string) $value) !== '' && ! $this->saysNothing($value);
    }

    /**
     * updatedP only fires for a box somebody touches. One left blank from the
     * start has still answered the question, so the answer is filled in here -
     * but only where none was given, so a deliberate "yes, I have one, I just
     * have not typed it in" is not overwritten.
     */
    private function answerIdQuestions(): void
    {
        foreach (self::ID_ANSWERS as $numberColumn => $answerColumn) {
            $given = $this->d[$answerColumn] ?? null;

            if ($given === null || $given === '') {
                $this->d[$answerColumn] = $this->idIsGiven($numberColumn) ? '1' : '0';
            }
        }
    }

    /**
     * Who is agreeing. Typing your own name into a box proves nothing that
     * being signed in does not already say, so it is taken from the form -
     * or from the account when the form has not been named yet - rather than
     * asked for a third time.
     */
    private function signatory(): string
    {
        $fromForm = trim(($this->p['first_name'] ?? '').' '.($this->p['surname'] ?? ''));

        return $fromForm !== '' ? $fromForm : (string) Auth::user()->full_name;
    }

    /** Either parent, or a guardian in their place - but not nobody. */
    private function hasAParent(): bool
    {
        foreach ([$this->p['fathers_name'] ?? '', $this->p['mothers_maiden_name'] ?? ''] as $name) {
            if (trim((string) $name) !== '' && ! $this->saysNothing($name)) {
                return true;
            }
        }

        return false;
    }

    /** Everybody went to school somewhere; which level is theirs to say. */
    private function hasSchooling(): bool
    {
        return collect($this->edu)->contains(fn ($e) => trim((string) ($e['school_name'] ?? '')) !== '');
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

    /**
     * One box per sibling. Capped, because a typo in a number field should not
     * ask the browser to draw nine thousand inputs.
     */
    private function resizeSiblings(int $count): void
    {
        $count = max(0, min($count, self::MAX_SIBLINGS));

        $this->siblings = array_slice($this->siblings, 0, $count);

        while (count($this->siblings) < $count) {
            $this->siblings[] = ['name' => ''];
        }
    }

    public function addSibling(): void
    {
        if (count($this->siblings) >= self::MAX_SIBLINGS) {
            return;
        }

        $this->siblings[] = ['name' => ''];
        $this->p['sibling_count'] = count($this->siblings);
    }

    public function removeSibling(int $i): void
    {
        unset($this->siblings[$i]);
        $this->siblings = array_values($this->siblings);
        $this->p['sibling_count'] = count($this->siblings);
    }

    /** Copies the present address down, so it is not typed twice. */
    public function updatedP($value, $key): void
    {
        if ($key === 'sibling_count') {
            $this->resizeSiblings((int) $value);
        }

        if (array_key_exists($key, self::ID_ANSWERS)) {
            $this->d[self::ID_ANSWERS[$key]] = $this->idIsGiven($key) ? '1' : '0';
        }

        if ($key === 'permanent_same_as_present' && $value) {
            $this->p['permanent_street']   = $this->p['present_street'] ?? '';
            $this->p['permanent_city']     = $this->p['present_city'] ?? '';
            $this->p['permanent_province'] = $this->p['present_province'] ?? '';
        }
    }

    public function save(): void
    {
        $this->answerIdQuestions();

        $this->validate([
            'p.surname'       => ['required', 'string', 'max:100'],
            'p.first_name'    => ['required', 'string', 'max:100'],
            'p.middle_name'   => ['nullable', 'string', 'max:100'],
            'p.cellphone'     => ['nullable', 'string', 'max:40'],
            'p.email_address' => ['nullable', 'email', 'max:150'],
            'p.date_of_birth' => ['nullable', 'date', 'before:today'],
            'p.civil_status'  => ['nullable', 'in:'.implode(',', array_keys($this->civilStatuses))],
            'p.sibling_count' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_SIBLINGS],
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
                ->map(fn ($v, $k) => $this->naIfBlank($v, $k))
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

                // A level somebody did attend but left a box of blank reads as
                // N/A, the same as everywhere else on the form.
                $row = [];
                foreach ($e as $column => $value) {
                    $row[$column] = $this->naIfBlank($value, $column);
                }

                DB::table('applicant_education')->updateOrInsert(
                    ['user_id' => $id, 'level' => $level],
                    $row + ['updated_at' => now(), 'created_at' => now()]
                );
            }

            $this->rewrite('applicant_employment', $id, $this->jobs,
                ['company_name', 'company_address', 'position', 'date_from', 'date_to',
                 'reason_for_leaving', 'daily_salary'], 'company_name');

            $this->rewrite('applicant_references', $id, $this->refs,
                ['name', 'contact_no', 'position_company'], 'name');

            $this->rewrite('applicant_relatives', $id, $this->relatives,
                ['name', 'relationship', 'department'], 'name', ordered: false);

            $this->rewrite('applicant_siblings', $id, $this->siblings, ['name'], 'name');

            // Zero is an answer. The form tells people to write N/A where
            // something does not apply to them, and this is the one question
            // answered with a number instead of typed into, so it records the
            // same thing rather than leaving a gap that reads as unanswered.
            // An untouched field stays empty: "none" and "not said" differ.
            $said = $this->p['sibling_count'] ?? '';

            if ($said !== '' && $said !== null && (int) $said === 0) {
                DB::table('applicant_siblings')->insert([
                    'user_id'    => $id,
                    'name'       => 'N/A',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $dis = collect($this->d)
                ->only(array_keys($this->blankDisclosures()))
                ->map(fn ($v, $k) => $this->naIfBlank($v, $k))
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
                $insert[$c] = $this->naIfBlank($row[$c] ?? '', $c);
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
        $this->save();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->p['certified_name'] = $this->signatory();

        DB::table('applicant_profiles')->where('user_id', Auth::id())
            ->update(['certified_name' => $this->p['certified_name'], 'certified_at' => now()]);

        $this->p['certified_at'] = now();
        session()->flash('success', 'Certified and saved.');
    }

    public function declare(): void
    {
        // Before the check, not after: the four ID questions are answered by
        // what was typed on the earlier step, and validating first would stop
        // somebody on questions the form fills in for them.
        $this->answerIdQuestions();

        // Every question answered before it is signed. An unanswered yes/no on
        // a declaration somebody puts their name to is not a blank that N/A
        // can cover - it is the difference between saying no and saying
        // nothing, and this is the moment that difference is claimed.
        $questions = [
            'has_medical_condition'        => 'the question about a medical condition',
            'takes_maintenance_medication' => 'the question about maintenance medication',
            'has_relative_employed'        => 'the question about relatives employed here',
            'ever_terminated'              => 'the question about being terminated',
            'ever_convicted'               => 'the question about convictions',
            'employed_elsewhere'           => 'the question about being employed elsewhere',
            'has_employment_bond'          => 'the question about an employment bond',
            'was_union_member'             => 'the question about union membership',
            'can_start_immediately'        => 'the question about starting immediately',
            'sss_on_file'                  => 'whether you have an SSS number',
            'pagibig_on_file'              => 'whether you have a Pag-IBIG number',
            'philhealth_on_file'           => 'whether you have a PhilHealth number',
            'tin_on_file'                  => 'whether you have a TIN',
        ];

        $rules = [];
        $names = [];

        foreach ($questions as $field => $label) {
            $rules['d.'.$field] = ['required'];
            $names['d.'.$field] = $label;
        }

        $this->validate($rules, [], $names);

        $this->save();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->d['declared_name'] = $this->signatory();

        DB::table('applicant_disclosures')->where('user_id', Auth::id())
            ->update(['declared_name' => $this->d['declared_name'], 'declared_at' => now()]);

        $this->d['declared_at'] = now();

        // The last thing the form asks for. Saying so plainly beats leaving
        // somebody on the final step wondering whether that was it.
        $this->showDone = true;
    }
}; ?>

<div class="px-6 md:px-8 pt-2 pb-6 md:pb-8 max-w-[100rem] mx-auto">
    <div class="mb-3">
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

    {{-- Where they are, and how much is left. Steps already passed can be
         clicked back to; ones ahead cannot, because they have not been saved. --}}
    <nav class="mb-4 flex flex-wrap items-center gap-2" aria-label="Form steps">
        @foreach ($steps as $number => $label)
            @php $state = $number === $step ? 'current' : ($number < $step ? 'done' : 'ahead'); @endphp
            <button type="button"
                    @if ($state === 'ahead') disabled @else wire:click="goToStep({{ $number }})" @endif
                    @if ($state === 'current') aria-current="step" @endif
                    class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors
                        @if ($state === 'current') border-red-600 bg-red-600 text-white
                        @elseif ($state === 'done') border-gray-300 bg-white text-gray-700 hover:border-red-300
                        @else border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed @endif">
                <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs
                    @if ($state === 'current') bg-white/20
                    @elseif ($state === 'done') bg-green-100 text-green-700
                    @else bg-gray-200 @endif">
                    @if ($state === 'done') <i class="fas fa-check"></i> @else {{ $number }} @endif
                </span>
                {{ $label }}
            </button>
        @endforeach
    </nav>

    @if ($step === 1)
        @include('partials.applicant-profile-personal')
    @elseif ($step === 2)
        @include('partials.applicant-profile-government')
    @elseif ($step === 3)
        @include('partials.applicant-profile-education')
    @elseif ($step === 4)
        @include('partials.applicant-profile-employment')
    @else
        @include('partials.applicant-profile-disclosures')
    @endif

    <div class="mt-6 flex items-center justify-between gap-3">
        <div>
            @if ($step > 1)
                <button type="button" wire:click="back" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </button>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500">Step {{ $step }} of {{ count($steps) }}</span>

            {{-- Save on its own, so somebody can stop half way and come back
                 without being made to walk to the end of the form first. --}}
            <button type="button" wire:click="save" class="btn-secondary">
                <i class="fas fa-floppy-disk mr-2"></i>Save
            </button>

            @if ($step < count($steps))
                <button type="button" wire:click="next" class="btn-primary">
                    Next<i class="fas fa-arrow-right ml-2"></i>
                </button>
            @endif
        </div>
    </div>

    {{-- The end of the form says so. Without it somebody finishes the last
         step, nothing visibly happens, and they are left wondering whether
         that was it. No way to dismiss it back onto the form: there is
         nothing left to do here, and the application is where they were
         headed. --}}
    @if ($showDone)
        <div class="fixed inset-0 z-[70] overflow-y-auto" role="dialog" aria-modal="true"
             aria-labelledby="done-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50"></div>

                <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                        <i class="fas fa-check text-xl text-green-700"></i>
                    </div>

                    <h2 id="done-title" class="mt-4 text-lg font-semibold text-gray-900">
                        That's everything.
                    </h2>

                    <p class="mt-2 text-sm text-gray-600">
                        Your details are complete and signed. HR can read them alongside your
                        application now &mdash; you do not need to send them separately.
                    </p>

                    <p class="mt-2 text-sm text-gray-600">
                        You can come back and change any of it from <strong>My details</strong>.
                    </p>

                    <button type="button" wire:click="finish" class="btn-primary mt-5 w-full">
                        Go to my application<i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
