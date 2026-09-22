<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    public $applications = [];

    /**
     * Every round, keyed by application, for the list.
     *
     * The list joins only the latest interview, so a candidate recommended
     * twice and then turned down showed just the last verdict - and somebody
     * scanning the list could not see they had been through three rounds at
     * all. One query for the page rather than one per row.
     *
     * @var array<int, list<object>>
     */
    public array $roundsByApplication = [];

    /** The interview results panel: which application, and its rounds. */
    public bool $showResultsModal = false;
    public $resultsApplication = null;
    public array $resultsRounds = [];
    public $employees = [];
    public $departments = [];
    public $users = [];
    public $filters = [
        'status' => null,
        'date_from' => null,
        'date_to' => null,
        'search' => null,
    ];
    public $stats = [];
    public $selectedApplication = null;
    public $selectedEmployee = null;
    public $showApplicationModal = false;

    /**
     * The 201 file the applicant filled in, for the application on screen.
     *
     * The dialog used to show only what registration collects - a name, an
     * email and a position - while everything HR actually reviews sat in
     * applicant_profiles and was never read by anything.
     */
    /** Every interview this application has had, in order. */
    public array $rounds = [];

    public $profile = null;
    public array $education = [];
    public array $employment = [];
    public array $references = [];
    public array $relatives = [];
    public array $siblings = [];
    public $disclosures = null;
    public $showRoleChangeModal = false;
    public $showInterviewModal = false;
    public $showInterviewResultModal = false;
    public $showDepartmentModal = false;
    public $showDocumentsModal = false;
    
    // Salary management
    public $showSalaryModal = false;
    public $selectedEmployeeForSalary = null;
    public $newSalary = '';
    
    public $selectedUserForRoleChange = null;
    public $newRole = 'employee';
    public $newDepartment = '';
    public $documents = [];
    
    // Interview scheduling
    public $interviewDate = '';
    public $interviewTime = '';
    public $interviewNotes = '';
    public $interviewerId = '';

    /** Set when an existing round is being moved rather than a new one added. */
    public $editingInterviewId = null;

    /* -------------------------------------------------------------- hiring
     *
     * Hiring used to write salary 0.00 and status active straight into the
     * employees table. That is one "Active employees without salary"
     * exception, and the payroll control centre refuses to approve any period
     * while one exists - so every hire silently stopped the whole company's
     * payslips until somebody worked out why.
     *
     * The figure is known at the moment of hiring; it is the offer. So it is
     * asked for here rather than left at zero to be found later.
     */
    public bool $showHireModal = false;
    public $hiringApplicationId = null;
    public $hireSalary = '';
    public $hireAllowance = '';
    public $hirePayBasis = 'monthly';
    public $hireDailyRate = '';
    public $hireDepartmentId = '';
    public $hireJobTitle = '';
    public $hireResponsibilities = '';
    public $hireStartsOn = '';
    public $interviewType = 'in_person';
    
    // Interview results
    public $interviewFeedback = '';
    public $interviewResult = 'passed';

    public function mount()
    {
        $this->filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
        $this->filters['date_to'] = date('Y-m-d');
        $this->loadData();
    }

    public function loadData()
    {
        $this->loadApplications();
        $this->loadEmployees();
        $this->loadDepartments();
        $this->loadUsers();
        $this->loadStats();
    }

    public function loadApplications()
    {
        $query = DB::table('job_applications as ja')
            ->select(
                'ja.application_id',
                'ja.user_id',
                'ja.position_applied',
                'ja.years_experience',
                'ja.application_date',
                'ja.status',
                'ai.scheduled_at as interview_date',
                'ai.type as interview_type',
                'ai.interviewer_id',
                'ai.hr_notes as interview_notes',
                'ai.status as interview_status',
                'ai.round as interview_round',
                'ai.recommendation as interviewer_recommendation',
                'ja.notes',
                'ja.resume_data',
                'ja.created_at',
                'ja.updated_at',
                'u.full_name',
                'u.username',
                'u.email',
                'u.role',
                'interviewer.full_name as interviewer_name',
                DB::raw('(SELECT COUNT(*) FROM job_applications ja2 WHERE ja2.user_id = ja.user_id) as total_applications')
            )
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->leftJoin('application_interviews as ai', 'ai.interview_id', '=', DB::raw(
                '(SELECT interview_id FROM application_interviews x
                    WHERE x.application_id = ja.application_id AND x.status != "cancelled"
                    ORDER BY x.round DESC, x.scheduled_at DESC LIMIT 1)'
            ))
            ->leftJoin('users as interviewer', 'ai.interviewer_id', '=', 'interviewer.user_id')
            ->orderBy('ai.scheduled_at', 'desc')
            ->orderBy('ja.application_date', 'desc');

        if ($this->filters['status']) {
            if ($this->filters['status'] === 'active') {
                $query->whereIn('ja.status', ['pending', 'reviewed', 'shortlisted', 'rejected']);
            } elseif ($this->filters['status'] === 'interview_scheduled') {
                $query->where('ja.status', 'reviewed')
                      ->whereNotNull('ai.scheduled_at');
            } else {
                $query->where('ja.status', $this->filters['status']);
            }
        } else {
            $query->whereIn('ja.status', ['pending', 'reviewed', 'shortlisted', 'rejected']);
        }

        if ($this->filters['date_from']) {
            $query->whereDate('ja.application_date', '>=', $this->filters['date_from']);
        }

        if ($this->filters['date_to']) {
            $query->whereDate('ja.application_date', '<=', $this->filters['date_to']);
        }

        if ($this->filters['search']) {
            $query->where(function($q) {
                $q->where('u.full_name', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.username', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.email', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('ja.position_applied', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        $this->applications = $query->get();

        $this->roundsByApplication = DB::table('application_interviews as ai')
            ->select('ai.application_id', 'ai.round', 'ai.status', 'ai.recommendation',
                'ai.recommendation_notes', 'u.full_name as interviewer_name')
            ->leftJoin('users as u', 'ai.interviewer_id', '=', 'u.user_id')
            ->whereIn('ai.application_id', $this->applications->pluck('application_id'))
            ->where('ai.status', '!=', 'cancelled')
            ->orderBy('ai.round')
            ->get()
            ->groupBy('application_id')
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }

  public function loadEmployees()
{
    $this->employees = DB::table('users as u')
        ->select(
            'u.user_id',
            'u.full_name',
            'u.username',
            'u.email',
            'u.role',
            'e.employee_id',
            'e.job_title',
            'e.hire_date',
            'e.salary',
            'e.status as emp_status',
            'e.department_id',
            'd.department_name'
        )
        ->join('employees as e', 'u.user_id', '=', 'e.user_id') // INNER JOIN
        ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
        ->where('e.status', 'active') // ✅ ONLY ACTIVE
        ->orderBy('u.full_name')
        ->get();
}


    public function loadDepartments()
    {
        $this->departments = DB::table('departments')
            ->select('department_id', 'department_name')
            ->orderBy('department_name')
            ->get();
    }

    public function loadUsers()
    {
        // Who can be given an interview to conduct. Supervisors and leaders
        // were left out, so the only people who could ever be picked were the
        // two back-office accounts - and the supervisor who actually runs the
        // team being hired for could not be handed the interview at all.
        $this->users = DB::table('users')
            ->select('user_id', 'full_name', 'username', 'email', 'role')
            ->whereIn('role', ['admin', 'hr', 'supervisor', 'leader'])
            ->orderByRaw("FIELD(role, 'supervisor', 'leader', 'hr', 'admin')")
            ->orderBy('full_name')
            ->get();
    }

    public function loadStats()
    {
        $stats = DB::table('job_applications')
            ->select(
                DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_count'),
                DB::raw('COUNT(CASE WHEN status = "reviewed" AND EXISTS (
                    SELECT 1 FROM application_interviews ai
                    WHERE ai.application_id = job_applications.application_id
                      AND ai.status != "cancelled") THEN 1 END) as interview_scheduled_count'),
                DB::raw('COUNT(CASE WHEN status = "reviewed" AND NOT EXISTS (
                    SELECT 1 FROM application_interviews ai
                    WHERE ai.application_id = job_applications.application_id
                      AND ai.status != "cancelled") THEN 1 END) as reviewed_count'),
                DB::raw('COUNT(CASE WHEN status = "shortlisted" THEN 1 END) as shortlisted_count'),
                DB::raw('COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected_count'),
                DB::raw('COUNT(CASE WHEN status = "hired" THEN 1 END) as hired_count'),
                DB::raw('COUNT(*) as total_count')
            )
            ->first();

        $this->stats = [
            'pending' => $stats->pending_count ?? 0,
            'interview_scheduled' => $stats->interview_scheduled_count ?? 0,
            'reviewed' => $stats->reviewed_count ?? 0,
            'shortlisted' => $stats->shortlisted_count ?? 0,
            'rejected' => $stats->rejected_count ?? 0,
            'hired' => $stats->hired_count ?? 0,
            'total' => $stats->total_count ?? 0
        ];
    }

    public function viewApplication($applicationId)
    {
        $this->selectedApplication = DB::table('job_applications as ja')
            ->select('ja.*', 'u.full_name', 'u.username', 'u.email', 'u.role')
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->where('ja.application_id', $applicationId)
            ->first();

        $this->rounds = $this->roundsFor($applicationId);

        $this->loadProfile($this->selectedApplication->user_id ?? null);

        $this->showApplicationModal = true;
    }

    /**
     * Everything the applicant wrote about themselves, keyed on their account
     * rather than on this application - so it is the same record whether they
     * applied once or three times.
     */
    /**
     * The rounds an application has been through, oldest first, each with who
     * ran it and what they made of it.
     *
     * @return list<object>
     */
    private function roundsFor(int $applicationId): array
    {
        return DB::table('application_interviews as ai')
            ->select('ai.*', 'u.full_name as interviewer_name', 'u.role as interviewer_role')
            ->leftJoin('users as u', 'ai.interviewer_id', '=', 'u.user_id')
            ->where('ai.application_id', $applicationId)
            ->orderBy('ai.round')
            ->orderBy('ai.scheduled_at')
            ->get()
            ->all();
    }

    private function loadProfile(?int $userId): void
    {
        $this->profile = null;
        $this->disclosures = null;
        $this->education = [];
        $this->employment = [];
        $this->references = [];
        $this->relatives = [];
        $this->siblings = [];

        if (! $userId) {
            return;
        }

        $this->profile = DB::table('applicant_profiles')->where('user_id', $userId)->first();

        if (! $this->profile) {
            return;
        }

        $this->education  = DB::table('applicant_education')->where('user_id', $userId)->get()->all();
        $this->employment = DB::table('applicant_employment')->where('user_id', $userId)->orderBy('sort_order')->get()->all();
        $this->references = DB::table('applicant_references')->where('user_id', $userId)->orderBy('sort_order')->get()->all();
        $this->relatives  = DB::table('applicant_relatives')->where('user_id', $userId)->get()->all();
        $this->siblings   = DB::table('applicant_siblings')->where('user_id', $userId)->orderBy('sort_order')->get()->all();
        $this->disclosures = DB::table('applicant_disclosures')->where('user_id', $userId)->first();
    }

    /** The order a 201 file is read in, not the order the table stores. */
    public function educationInOrder(): array
    {
        $order = ['elementary', 'junior_high', 'senior_high', 'high_school', 'vocational', 'tertiary'];
        $rows = collect($this->education)->keyBy('level');

        return collect($order)->map(fn ($l) => $rows->get($l))->filter()->all();
    }

    public array $educationLabels = [
        'elementary'  => 'Elementary',
        'junior_high' => 'Junior high',
        'senior_high' => 'Senior high',
        'high_school' => 'High school',
        'vocational'  => 'Vocational',
        'tertiary'    => 'College / tertiary',
    ];

    /** Marking somebody hired asks for the terms first. */
    public function openHireModal($applicationId)
    {
        $application = DB::table('job_applications')->where('application_id', $applicationId)->first();

        if (! $application) {
            return;
        }

        $existing = DB::table('employees')->where('user_id', $application->user_id)->first();

        $this->hiringApplicationId = $applicationId;
        $this->hireJobTitle = $application->position_applied;
        $this->hirePayBasis = $existing->pay_basis ?? 'monthly';
        // A re-hire keeps whatever they were on, if it was anything.
        $this->hireSalary = ($existing && $existing->salary > 0) ? $existing->salary : '';
        $this->hireAllowance = ($existing && $existing->allowance > 0) ? $existing->allowance : '';
        $this->hireDailyRate = ($existing && $existing->daily_rate > 0) ? $existing->daily_rate : '';
        $this->hireDepartmentId = $existing->department_id ?? '';

        // A previous offer is the best draft of the next one.
        $previous = DB::table('job_offers')->where('application_id', $applicationId)
            ->orderByDesc('offer_id')->first();

        $this->hireResponsibilities = $previous->responsibilities ?? $this->responsibilitiesFor($application->position_applied);
        $this->hireStartsOn = $previous->starts_on ?? now()->addWeek()->toDateString();

        $this->resetValidation();
        $this->showHireModal = true;
    }

    /**
     * Send the offer. It does not hire anybody - the candidate does that by
     * accepting it, which is the point: the system used to record a decision
     * it had never been given.
     */
    public function confirmHire()
    {
        $this->validate([
            'hireJobTitle'  => ['required', 'string', 'max:150'],
            'hirePayBasis'  => ['required', 'in:monthly,daily,hourly'],
            // Whichever figure the basis actually uses has to be a real one.
            'hireSalary'    => [$this->hirePayBasis === 'monthly' ? 'required' : 'nullable', 'numeric', 'min:1'],
            'hireDailyRate' => [$this->hirePayBasis === 'monthly' ? 'nullable' : 'required', 'numeric', 'min:1'],
            // Optional: plenty of roles carry none.
            'hireAllowance' => ['nullable', 'numeric', 'min:0'],
            // Somebody is agreeing to this, so it cannot be blank.
            'hireResponsibilities' => ['required', 'string', 'min:20', 'max:4000'],
            'hireStartsOn'  => ['nullable', 'date'],
        ], [], [
            'hireJobTitle'  => 'job title',
            'hireSalary'    => 'monthly salary',
            'hireDailyRate' => 'daily rate',
            'hireResponsibilities' => 'responsibilities',
            'hireStartsOn'  => 'start date',
        ]);

        $applicationId = (int) $this->hiringApplicationId;

        DB::transaction(function () use ($applicationId) {
            // Any offer still outstanding is superseded, not deleted: what was
            // offered before is part of the history.
            DB::table('job_offers')->where('application_id', $applicationId)
                ->where('status', 'sent')
                ->update(['status' => 'withdrawn', 'updated_at' => now()]);

            DB::table('job_offers')->insert([
                'application_id'   => $applicationId,
                'job_title'        => $this->hireJobTitle,
                'pay_basis'        => $this->hirePayBasis,
                'basic_salary'     => $this->hirePayBasis === 'monthly' ? (float) $this->hireSalary : 0,
                'daily_rate'       => $this->hirePayBasis === 'monthly' ? 0 : (float) $this->hireDailyRate,
                'allowance'        => (float) ($this->hireAllowance ?: 0),
                'department_id'    => $this->hireDepartmentId ?: null,
                'responsibilities' => $this->hireResponsibilities,
                'starts_on'        => $this->hireStartsOn ?: null,
                'status'           => 'sent',
                'sent_by'          => auth()->id(),
                'sent_at'          => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            DB::table('job_applications')->where('application_id', $applicationId)
                ->update(['status' => 'offered', 'updated_at' => now()]);
        });

        $this->showHireModal = false;
        $this->hiringApplicationId = null;
        $this->loadData();
        session()->flash('success', 'Offer sent. They will see it on their application, and hiring completes when they accept.');
    }

    /** A starting point for the responsibilities, from the opening itself. */
    private function responsibilitiesFor(?string $position): string
    {
        return (string) DB::table('job_positions')->where('title', $position)->value('description');
    }

    /**
     * Turn an applicant into an employee, on terms that were actually chosen.
     *
     * Called from the hire dialog. If it is ever reached without one - an old
     * link, a direct call - it refuses rather than falling back to zero,
     * because a zero here stops everybody's payslips.
     */
    /**
     * What the interviewers made of somebody, on its own.
     *
     * It is all in the application dialog too, but that is the whole 201 file,
     * and somebody deciding whether to make an offer wants the verdicts and
     * the reasons without scrolling past a birthplace to reach them.
     */
    public function openResults($applicationId)
    {
        $this->resultsApplication = DB::table('job_applications as ja')
            ->select('ja.application_id', 'ja.position_applied', 'ja.status', 'u.full_name')
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->where('ja.application_id', $applicationId)
            ->first();

        $this->resultsRounds = $this->roundsFor((int) $applicationId);
        $this->showResultsModal = true;
    }

    public function viewDocuments($applicationId)
    {
        $this->selectedApplication = DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->first();
        
        $this->documents = DB::table('application_documents as ad')
            ->select('ad.*', 'u.full_name')
            ->leftJoin('users as u', 'ad.user_id', '=', 'u.user_id')
            ->where('ad.application_id', $applicationId)
            ->orderBy('ad.uploaded_at', 'desc')
            ->get();
        
        $this->showDocumentsModal = true;
    }

public function updateApplicationStatus($applicationId, $status)
{
    // Checked first, before anything is written. Nobody is hired on terms
    // they were never shown, and "hired" is reached by the candidate
    // accepting an offer rather than by anybody setting a status.
    $accepted = null;

    if ($status === 'hired') {
        $accepted = DB::table('job_offers')->where('application_id', $applicationId)
            ->where('status', 'accepted')->orderByDesc('offer_id')->first();

        if (! $accepted) {
            session()->flash('error', 'Send them an offer first. Somebody is hired when they accept one, not before.');

            return;
        }
    }

    $updates = [
        'status' => $status,
        'updated_at' => now()
    ];

    if ($status !== 'reviewed') {
        // Cancelled, not deleted. The rounds happened, and one already given a
        // recommendation is a record worth keeping.
        DB::table('application_interviews')
            ->where('application_id', $applicationId)
            ->where('status', 'scheduled')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    DB::table('job_applications')
        ->where('application_id', $applicationId)
        ->update($updates);

    if ($accepted) {
        \App\Support\OfferAcceptance::hire((int) $accepted->offer_id);
    }

    if ($this->showApplicationModal) {
        $this->showApplicationModal = false;
        $this->selectedApplication = null;
    }
    
    $this->loadData();
    
    session()->flash('success', 'Application status updated successfully!');
}

    public function markAsReviewed($applicationId)
    {
        DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->update([
                'status' => 'reviewed',
                'updated_at' => now()
            ]);

        $this->loadData();
        session()->flash('success', 'Application marked as reviewed. You can now schedule an interview.');
    }

    /**
     * Book a round. Called with an interview id to move one that exists, and
     * without to add the next - which is how a second interview is arranged
     * without disturbing the first.
     */
    public function openInterviewModal($applicationId, $interviewId = null)
    {
        $this->selectedApplication = DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->first();

        $this->rounds = $this->roundsFor($applicationId);
        $this->editingInterviewId = $interviewId;
        $this->resetValidation();

        $existing = $interviewId
            ? DB::table('application_interviews')->where('interview_id', $interviewId)
                ->where('application_id', $applicationId)->first()
            : null;

        if ($existing) {
            $when = \Carbon\Carbon::parse($existing->scheduled_at);
            $this->interviewDate = $when->format('Y-m-d');
            $this->interviewTime = $when->format('H:i');
            $this->interviewNotes = $existing->hr_notes ?? '';
            $this->interviewerId = $existing->interviewer_id ?? '';
            $this->interviewType = $existing->type ?? 'in_person';
        } else {
            $this->interviewDate = date('Y-m-d', strtotime('+2 days'));
            $this->interviewTime = '10:00';
            $this->interviewNotes = '';
            $this->interviewerId = '';
            $this->interviewType = 'in_person';
        }

        $this->showInterviewModal = true;
    }

    public function saveInterviewSchedule()
    {
        $this->validate([
            'interviewDate' => 'required|date',
            'interviewTime' => 'required',
            'interviewerId' => 'required',
            'interviewType' => 'required|in:phone,video,in_person,technical,hr',
        ]);

        $applicationId = $this->selectedApplication->application_id;
        $when = $this->interviewDate.' '.$this->interviewTime.':00';

        $fields = [
            'interviewer_id' => $this->interviewerId,
            'scheduled_at'   => $when,
            'type'           => $this->interviewType,
            'hr_notes'       => $this->interviewNotes ?: null,
            'updated_at'     => now(),
        ];

        if ($this->editingInterviewId) {
            // Moving a round that already exists: same round, new details.
            DB::table('application_interviews')
                ->where('interview_id', $this->editingInterviewId)
                ->where('application_id', $applicationId)
                ->update($fields);

            $message = 'Interview updated.';
        } else {
            // A further round. It is added rather than written over the last
            // one, so both interviewers keep their own record and their own
            // recommendation.
            $next = 1 + (int) DB::table('application_interviews')
                ->where('application_id', $applicationId)->max('round');

            DB::table('application_interviews')->insert($fields + [
                'application_id' => $applicationId,
                'round'          => $next,
                'status'         => 'scheduled',
                'created_at'     => now(),
            ]);

            $message = $next > 1
                ? 'Round '.$next.' scheduled. The earlier round is kept.'
                : 'Interview scheduled.';
        }

        $this->showInterviewModal = false;
        $this->editingInterviewId = null;
        $this->rounds = $this->roundsFor($applicationId);
        $this->loadData();
        session()->flash('success', $message);
    }

    /** Cancels one round, leaving any others alone. */
    public function cancelInterview($interviewId)
    {
        $row = DB::table('application_interviews')->where('interview_id', $interviewId)->first();

        if (! $row) {
            return;
        }

        DB::table('application_interviews')->where('interview_id', $interviewId)
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        $this->rounds = $this->roundsFor((int) $row->application_id);
        $this->loadData();
        session()->flash('success', 'Interview cancelled.');
    }

    public function markInterviewCompleted($applicationId)
    {
        $this->selectedApplication = DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->first();
        
        $this->interviewFeedback = '';
        $this->interviewResult = 'passed';
        
        $this->showInterviewResultModal = true;
    }

    public function saveInterviewResult()
    {
        $this->validate([
            'interviewFeedback' => 'required|string|min:10',
            'interviewResult' => 'required|in:passed,failed'
        ]);
        
        // Against the latest live round: that is the one being reported on.
        $round = DB::table('application_interviews')
            ->where('application_id', $this->selectedApplication->application_id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('round')
            ->first();

        if ($round) {
            $note = "Result: ".ucfirst($this->interviewResult)."\n"
                .$this->interviewFeedback;

            DB::table('application_interviews')->where('interview_id', $round->interview_id)->update([
                'status' => 'completed',
                // Only if the interviewer has not already given their own; HR
                // recording an outcome must not overwrite what they wrote.
                'recommendation_notes' => $round->recommendation_notes ?: $note,
                'updated_at' => now(),
            ]);
        }
        
        if ($this->interviewResult === 'passed') {
            DB::table('job_applications')
                ->where('application_id', $this->selectedApplication->application_id)
                ->update(['status' => 'shortlisted']);
        } elseif ($this->interviewResult === 'failed') {
            DB::table('job_applications')
                ->where('application_id', $this->selectedApplication->application_id)
                ->update(['status' => 'rejected']);
        }
        
        $this->showInterviewResultModal = false;
        $this->loadData();
        session()->flash('success', 'Interview result saved!');
    }

    // SALARY MANAGEMENT METHODS
    public function openSalaryModal($employeeId)
    {
        $this->selectedEmployeeForSalary = DB::table('employees as e')
            ->select('e.*', 'u.full_name', 'u.email', 'd.department_name')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.employee_id', $employeeId)
            ->first();
        
        $this->newSalary = $this->selectedEmployeeForSalary->salary ?? 0;
        
        $this->showSalaryModal = true;
    }

    public function updateSalary()
    {
        $this->validate([
            'newSalary' => 'required|numeric|min:0|max:9999999.99'
        ]);

        $oldSalary = $this->selectedEmployeeForSalary->salary ?? 0;
        
        DB::table('employees')
            ->where('employee_id', $this->selectedEmployeeForSalary->employee_id)
            ->update([
                'salary' => $this->newSalary,
                'updated_at' => now()
            ]);

        // Log salary change
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'employees',
            'record_id' => $this->selectedEmployeeForSalary->employee_id,
            'old_values' => json_encode(['salary' => $oldSalary]),
            'new_values' => json_encode(['salary' => $this->newSalary]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showSalaryModal = false;
        $this->loadData();
        session()->flash('success', 'Salary updated successfully!');
    }

    /*
     * Creating a department lives on the employees screen, where the
     * department is actually assigned. Two ways in meant two dialogs, two
     * validation rules and two audit-log writes to keep in step.
     */

    public function openDepartmentModal($employeeId)
    {
        $this->selectedEmployee = DB::table('employees as e')
            ->select('e.*', 'u.full_name', 'd.department_name')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.employee_id', $employeeId)
            ->first();
        
        $this->newDepartment = $this->selectedEmployee->department_id ?? '';
        $this->showDepartmentModal = true;
    }

    public function updateDepartment()
    {
        $this->validate([
            'newDepartment' => 'required|exists:departments,department_id'
        ]);

        DB::table('employees')
            ->where('employee_id', $this->selectedEmployee->employee_id)
            ->update([
                'department_id' => $this->newDepartment,
                'updated_at' => now()
            ]);

        // Log the change
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'employees',
            'record_id' => $this->selectedEmployee->employee_id,
            'old_values' => json_encode(['department_id' => $this->selectedEmployee->department_id]),
            'new_values' => json_encode(['department_id' => $this->newDepartment]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showDepartmentModal = false;
        $this->loadData();
        session()->flash('success', 'Department updated successfully!');
    }

    public function openRoleChangeModal($userId)
    {
        $this->selectedUserForRoleChange = DB::table('users')
            ->where('user_id', $userId)
            ->first();

        $this->newRole = $this->selectedUserForRoleChange->role ?? 'employee';
        $this->showRoleChangeModal = true;
    }

    public function changeUserRole()
    {
        if (!$this->selectedUserForRoleChange) {
            return;
        }

        // newRole arrives straight from the browser and used to be written to
        // the column unchecked, so any value a request carried became somebody's
        // role - including one the column would reject outright.
        $this->validate([
            'newRole' => ['required', 'in:admin,hr,supervisor,leader,employee'],
        ]);

        $userId = $this->selectedUserForRoleChange->user_id;
        $oldRole = $this->selectedUserForRoleChange->role ?? 'employee';

        DB::table('users')
            ->where('user_id', $userId)
            ->update([
                'role' => $this->newRole,
                'updated_at' => now()
            ]);

        // Only create employee record when changing TO employee role
        if ($oldRole !== 'employee' && $this->newRole === 'employee') {
            $employee = DB::table('employees')->where('user_id', $userId)->first();
            if (!$employee) {
                DB::table('employees')->insert([
                    'user_id' => $userId,
                    'job_title' => 'New Employee',
                    'hire_date' => date('Y-m-d'),
                    'salary' => 0.00,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Log the change
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'users',
            'record_id' => $userId,
            'old_values' => json_encode(['role' => $oldRole]),
            'new_values' => json_encode(['role' => $this->newRole]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showRoleChangeModal = false;
        $this->loadData();
        session()->flash('success', 'User role changed successfully!');
    }

    public function deleteApplication($applicationId)
    {
        if (confirm('Are you sure you want to delete this application?')) {
            DB::table('job_applications')->where('application_id', $applicationId)->delete();
            $this->loadData();
            session()->flash('success', 'Application deleted successfully!');
        }
    }

    public function applyFilters()
    {
        $this->loadData();
    }

    public function resetFilters()
    {
        $this->filters = [
            'status' => null,
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'search' => null,
        ];
        $this->loadData();
    }

    /*
     * downloadResume() and downloadDocument() used to flash "download would
     * start here" and do nothing at all. They are real routes now - see
     * ApplicationFileController - because a file nobody can open may as well
     * not have been uploaded.
     */

    public function deleteDepartment($departmentId)
    {
        if (confirm('Are you sure you want to delete this department? This will remove the department assignment from all employees.')) {
            // First, remove department from all employees
            DB::table('employees')
                ->where('department_id', $departmentId)
                ->update(['department_id' => null]);
            
            // Then delete the department
            DB::table('departments')->where('department_id', $departmentId)->delete();
            
            $this->loadData();
            session()->flash('success', 'Department deleted successfully!');
        }
    }

    public function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
?>

{{-- New applications and freshly recorded recommendations arrive on their
     own. Held while any dialog is open - the schedule form and the salary
     form both carry typed values that a re-render would discard. --}}
<div @if (! $showApplicationModal && ! $showInterviewModal && ! $showDocumentsModal
          && ! $showInterviewResultModal && ! $showRoleChangeModal && ! $showSalaryModal
          && ! $showDepartmentModal && ! $showHireModal && ! $showResultsModal)
        wire:poll.30s.visible
     @endif>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Human Resources Management</h1>
            <p class="text-gray-600 mt-1">Manage applications, employees and salaries</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                {{ $stats['total'] ?? 0 }} Total Applications
            </span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-yellow-100 text-yellow-600">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['pending'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Pending</div>
            <div class="card-subtitle">Awaiting review</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-blue-100 text-blue-600">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['reviewed'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Reviewed</div>
            <div class="card-subtitle">Under consideration</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-purple-100 text-purple-600">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['interview_scheduled'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Interview</div>
            <div class="card-subtitle">Scheduled</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-red-100 text-red-600">
                    <i class="fas fa-list"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['shortlisted'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Shortlisted</div>
            <div class="card-subtitle">Top candidates</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-red-100 text-red-600">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['rejected'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Rejected</div>
            <div class="card-subtitle">Not selected</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-indigo-100 text-indigo-600">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['hired'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Hired</div>
            <div class="card-subtitle">Successfully hired</div>
        </div>
    </div>

    <!-- Department List Section -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Departments</h2>
                <p class="text-sm text-gray-600">Manage company departments</p>
            </div>
            <div class="text-sm text-gray-600">
                {{ count($departments) }} Departments
            </div>
        </div>
        
        @if(count($departments) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-6">
                @foreach($departments as $department)
                    @php
                        // Count employees in this department
                        $employeeCount = DB::table('employees')
                            ->where('department_id', $department->department_id)
                            ->count();
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="font-medium text-gray-900">{{ $department->department_name }}</h3>
                            <button wire:click="deleteDepartment('{{ $department->department_id }}')" 
                                    onclick="return confirm('Delete {{ $department->department_name }} department?')"
                                    class="text-red-400 hover:text-blue-600">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="flex items-center text-sm text-gray-500 mb-3">
                            <i class="fas fa-users mr-2"></i>
                            <span>{{ $employeeCount }} {{ Str::plural('employee', $employeeCount) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8">
                <i class="fas fa-building text-4xl text-gray-300 mb-3"></i>
                <p class="text-lg text-gray-500">No departments created yet</p>
                <p class="text-sm text-gray-400 mt-1">Create your first department using the button above.</p>
            </div>
        @endif
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl p-4 mb-6 shadow-sm border border-gray-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="form-label">Application Status</label>
                <select wire:model.live="filters.status" class="form-input">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="interview_scheduled">Scheduled for Interview</option>
                    <option value="shortlisted">Shortlisted</option>
                    <option value="rejected">Rejected</option>
                    <option value="hired">Hired</option>
                </select>
            </div>

            <!-- Date Range -->
            <div>
                <label class="form-label">Date From</label>
                <input type="date" 
                       wire:model.live="filters.date_from"
                       class="form-input">
            </div>

            <div>
                <label class="form-label">Date To</label>
                <input type="date" 
                       wire:model.live="filters.date_to"
                       class="form-input">
            </div>

            <!-- Search -->
            <div>
                <label class="form-label">Search</label>
                <div class="relative">
                    <input type="text" 
                           wire:model.live.debounce.300ms="filters.search"
                           placeholder="Search by name, position..."
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

    <!-- Applications Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Job Applications</h2>
            <div class="text-sm text-gray-600">
                Showing {{ count($applications) }} applications
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Position</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Interview Date</th>
                        <th>Recommendation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($applications) > 0)
                        @foreach($applications as $application)
                            @php
                                // Determine display status
                                $displayStatus = $application->status ?? 'pending';
                                $statusLabel = ucfirst($application->status ?? 'pending');
                                $interviewCompleted = ($application->interview_status ?? '') === 'completed';
                                
                                if (($application->interview_date ?? false) && ($application->status ?? '') === 'reviewed') {
                                    if ($interviewCompleted) {
                                        $displayStatus = 'interview_completed';
                                        $statusLabel = 'Interview Completed';
                                    } else {
                                        $displayStatus = 'scheduled_interview';
                                        $statusLabel = 'Scheduled for Interview';
                                    }
                                }

                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'reviewed' => 'bg-blue-100 text-blue-800',
                                    'scheduled_interview' => 'bg-purple-100 text-purple-800',
                                    'interview_completed' => 'bg-indigo-100 text-indigo-800',
                                    'shortlisted' => 'bg-teal-100 text-teal-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'hired' => 'bg-green-100 text-green-800',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-hr-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-hr-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $application->full_name ?? 'N/A' }}</div>
                                            <div class="text-sm text-gray-500">{{ $application->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="font-medium">{{ $application->position_applied ?? 'N/A' }}</td>
                                <td>{{ filled($application->years_experience ?? null) ? $application->years_experience : "—" }}</td>
                                <td>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$displayStatus] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabel }}
                                    </span>
                                    @if(($application->interview_date ?? false) && ($application->status ?? '') === 'reviewed')
                                        <div class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-user-tie mr-1"></i>
                                            {{ $application->interviewer_name ?? 'Interviewer not assigned' }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($application->interview_date ?? false)
                                        <div class="font-medium">
                                            {{ date('M d, Y', strtotime($application->interview_date)) }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ date('h:i A', strtotime($application->interview_date)) }}
                                        </div>
                                        @if($application->interview_type ?? false)
                                            <div class="text-xs text-gray-500">
                                                {{ ucfirst(str_replace('_', ' ', $application->interview_type)) }}
                                            </div>
                                        @endif
                                        @if($application->interview_status === 'completed')
                                            <div class="text-xs text-green-600 mt-1">
                                                <i class="fas fa-check-circle mr-1"></i>Completed
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">Not scheduled</span>
                                    @endif
                                </td>

                                {{-- The interviewer's verdict on the latest round. It lived
                                     only inside the dialog, so the one thing HR is waiting
                                     to know took a click per applicant to find out. --}}
                                <td>
                                    @php
                                        $verdicts = [
                                            'recommend'     => ['Recommends', 'bg-green-100 text-green-800', 'fa-thumbs-up'],
                                            'not_recommend' => ['Not recommended', 'bg-red-100 text-red-800', 'fa-thumbs-down'],
                                            'undecided'     => ['Undecided', 'bg-gray-100 text-gray-700', 'fa-circle-question'],
                                        ];
                                        $verdict = $application->interviewer_recommendation ?? null;
                                    @endphp

                                    {{-- Deliberately not $rounds: that is the component
                                         property the dialog further down this same template
                                         reads, and assigning to it here quietly replaced it
                                         with whichever row happened to be rendered last. --}}
                                    @php $rowRounds = $roundsByApplication[$application->application_id] ?? []; @endphp

                                    @if (count($rowRounds) === 0)
                                        <span class="text-xs text-gray-300">&mdash;</span>
                                    @else
                                        {{-- One mark per round, in order. Only the latest used to
                                             show, so somebody recommended twice and then turned
                                             down looked the same as somebody turned down once. --}}
                                        <div class="flex flex-col gap-1">
                                            @foreach ($rowRounds as $r)
                                                @php
                                                    [$rLabel, $rTone, $rIcon] = $r->recommendation
                                                        ? $verdicts[$r->recommendation]
                                                        : ['Not yet given', 'bg-gray-100 text-gray-500', 'fa-hourglass-half'];
                                                @endphp
                                                {{-- The note lives on the hover: it is a sentence or
                                                     three, which would wreck a table row, but it is
                                                     the reason behind the verdict and worth reaching
                                                     without opening the application. It is in full in
                                                     the dialog. --}}
                                                @php
                                                    $tip = 'Round '.$r->round
                                                        .($r->interviewer_name ? ' - '.$r->interviewer_name : '')
                                                        .': '.$rLabel
                                                        .(trim((string) $r->recommendation_notes) !== ''
                                                            ? "\n\n".$r->recommendation_notes : '');
                                                @endphp
                                                <span class="inline-flex items-center gap-1.5 text-xs {{ trim((string) $r->recommendation_notes) !== '' ? 'cursor-help' : '' }}"
                                                      title="{{ $tip }}">
                                                    @if (count($rowRounds) > 1)
                                                        <span class="text-gray-400 w-3 shrink-0">{{ $r->round }}</span>
                                                    @endif
                                                    <span class="px-2 py-0.5 rounded-full font-medium {{ $rTone }}">
                                                        <i class="fas {{ $rIcon }} mr-1"></i>{{ $rLabel }}
                                                    </span>
                                                    @if (trim((string) $r->recommendation_notes) !== '')
                                                        <i class="fas fa-comment-dots text-gray-400" aria-hidden="true"></i>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <div class="flex gap-2">
                                        <button wire:click="viewApplication('{{ $application->application_id }}')" 
                                                class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                        
                                        <button wire:click="viewDocuments('{{ $application->application_id }}')" 
                                                class="px-3 py-1 text-xs bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                            <i class="fas fa-file-alt mr-1"></i>Documents
                                        </button>

                                        @if (count($rowRounds) > 0)
                                            <button wire:click="openResults('{{ $application->application_id }}')"
                                                    class="px-3 py-1 text-xs bg-purple-50 text-purple-700 rounded hover:bg-purple-100">
                                                <i class="fas fa-clipboard-check mr-1"></i>Result
                                            </button>
                                        @endif
                                        
                                        @if(($application->status ?? '') === 'pending')
                                            <button wire:click="markAsReviewed('{{ $application->application_id }}')" 
                                                    class="px-3 py-1 text-xs bg-green-50 text-green-600 rounded hover:bg-green-100">
                                                <i class="fas fa-check mr-1"></i>Review
                                            </button>
                                        @endif

                                        @if(($application->status ?? '') === 'reviewed')
                                            @if($application->interview_date ?? false)
                                                @if($application->interview_status !== 'completed')
                                                    <button wire:click="markInterviewCompleted('{{ $application->application_id }}')" 
                                                            class="px-3 py-1 text-xs bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100">
                                                        <i class="fas fa-clipboard-check mr-1"></i>Complete
                                                    </button>
                                                @endif
                                                <button wire:click="openInterviewModal('{{ $application->application_id }}')" 
                                                        class="px-3 py-1 text-xs bg-purple-50 text-purple-600 rounded hover:bg-purple-100">
                                                    <i class="fas fa-edit mr-1"></i>Reschedule
                                                </button>
                                                <button wire:click="cancelInterview('{{ $application->application_id }}')" 
                                                        onclick="return confirm('Are you sure you want to cancel this interview?')"
                                                        class="px-3 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-slate-100">
                                                    <i class="fas fa-times mr-1"></i>Cancel
                                                </button>
                                            @else
                                                <button wire:click="openInterviewModal('{{ $application->application_id }}')" 
                                                        class="px-3 py-1 text-xs bg-purple-50 text-purple-600 rounded hover:bg-purple-100">
                                                    <i class="fas fa-calendar-alt mr-1"></i>Schedule
                                                </button>
                                            @endif
                                        @endif

                                        @if(in_array($application->status, ['shortlisted', 'reviewed']))
                                            @if($application->interview_status === 'completed' || $application->status === 'shortlisted')
                                                <button wire:click="openHireModal('{{ $application->application_id }}')" 
                                                        class="px-3 py-1 text-xs bg-teal-50 text-teal-600 rounded hover:bg-teal-100">
                                                    <i class="fas fa-paper-plane mr-1"></i>Offer
                                                </button>
                                                <button wire:click="updateApplicationStatus('{{ $application->application_id }}', 'rejected')" 
                                                        class="px-3 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-slate-100"
                                                        onclick="return confirm('Reject {{ $application->full_name }}?')">
                                                    <i class="fas fa-times mr-1"></i>Reject
                                                </button>
                                            @endif
                                        @endif

                                        @if($application->resume_data ?? false)
                                            <a href="{{ route('applications.resume', $application->application_id) }}" target="_blank" 
                                                    class="px-3 py-1 text-xs bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                                <i class="fas fa-download mr-1"></i>Resume
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-file-alt text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-lg">No applications found</p>
                                    <p class="text-sm mt-1">Try adjusting your filters or check back later.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- The employee roster used to sit here, in the middle of the
         applications screen. It has its own page now at /hr/employees,
         which is also where accounts are created. --}}

    <!-- Salary Management Modal -->
    @if($showSalaryModal && $selectedEmployeeForSalary)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showSalaryModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Update Employee Salary</h3>
                            <p class="text-sm text-gray-500">{{ $selectedEmployeeForSalary->full_name ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showSalaryModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Current Information -->
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-gray-500">Position:</span>
                                    <p class="font-medium">{{ $selectedEmployeeForSalary->job_title ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">Department:</span>
                                    <p class="font-medium">{{ $selectedEmployeeForSalary->department_name ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-gray-500">Current Salary:</span>
                                    <p class="font-medium text-lg text-red-600">
                                        ₱{{ number_format($selectedEmployeeForSalary->salary ?? 0, 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Salary Input -->
                        <div>
                            <label class="form-label">New Monthly Salary</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">₱</span>
                                </div>
                                <input type="number" 
                                       wire:model="newSalary" 
                                       class="form-input pl-10"
                                       step="0.01"
                                       min="0"
                                       max="9999999.99"
                                       placeholder="0.00">
                            </div>
                            @error('newSalary') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Salary Information -->
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="flex">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-700">
                                    <p><strong>Note:</strong></p>
                                    <ul class="mt-1 space-y-1">
                                        <li>• Salary is in Philippine Peso (₱)</li>
                                        <li>• This is the monthly gross salary</li>
                                        <li>• Deductions will be calculated separately</li>
                                        <li>• Changes take effect immediately</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="updateSalary" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>
                        Update Salary
                    </button>
                    <button wire:click="$set('showSalaryModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif


    {{-- Hiring asks for the pay before it happens. It used to write 0.00 and
         leave payroll to refuse every period until somebody found out why. --}}
    @if ($showHireModal)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                 wire:click="$set('showHireModal', false)"></div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Send a job offer</h3>
                            <p class="text-sm text-gray-500">They accept it before anybody is hired.</p>
                        </div>
                        <button wire:click="$set('showHireModal', false)" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Job title</label>
                            <input type="text" wire:model="hireJobTitle" class="form-input">
                            @error('hireJobTitle') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="form-label">Paid</label>
                            <select wire:model.live="hirePayBasis" class="form-input">
                                <option value="monthly">Monthly</option>
                                <option value="daily">Daily</option>
                                <option value="hourly">Hourly</option>
                            </select>
                        </div>

                        {{-- Basic and allowance are entered apart because they are
                             treated apart: basic carries the tax and the SSS,
                             PhilHealth and Pag-IBIG contributions, while a de
                             minimis allowance is paid whole. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @if ($hirePayBasis === 'monthly')
                                <div>
                                    <label class="form-label">Basic salary (monthly)</label>
                                    <input type="number" step="0.01" min="1" wire:model.live="hireSalary" class="form-input" placeholder="0.00">
                                    @error('hireSalary') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div>
                                    <label class="form-label">Basic daily rate</label>
                                    <input type="number" step="0.01" min="1" wire:model.live="hireDailyRate" class="form-input" placeholder="0.00">
                                    @error('hireDailyRate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div>
                                <label class="form-label">Allowance <span class="text-gray-400 font-normal">(optional)</span></label>
                                <input type="number" step="0.01" min="0" wire:model.live="hireAllowance" class="form-input" placeholder="0.00">
                                @error('hireAllowance') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        @php
                            $basicFigure = (float) ($hirePayBasis === 'monthly' ? $hireSalary : $hireDailyRate);
                            $allowanceFigure = (float) ($hireAllowance ?: 0);
                        @endphp
                        @if ($basicFigure > 0 || $allowanceFigure > 0)
                            <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm flex items-center justify-between">
                                <span class="text-gray-600">
                                    Total {{ $hirePayBasis === 'monthly' ? 'monthly' : 'per day' }}
                                </span>
                                <span class="font-semibold text-gray-900">
                                    &#8369;{{ number_format($basicFigure + $allowanceFigure, 2) }}
                                </span>
                            </div>
                        @endif

                        <div>
                            <label class="form-label">Department <span class="text-gray-400 font-normal">(optional)</span></label>
                            <select wire:model="hireDepartmentId" class="form-input">
                                <option value="">Not assigned</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->department_id }}">{{ $department->department_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Start date</label>
                            <input type="date" wire:model="hireStartsOn" class="form-input">
                            @error('hireStartsOn') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Part of the offer, not a note beside it: this is what
                             the person is agreeing to do. --}}
                        <div>
                            <label class="form-label">What the job involves</label>
                            <textarea wire:model="hireResponsibilities" rows="5" class="form-input"
                                      placeholder="The duties this role carries, in plain terms."></textarea>
                            @error('hireResponsibilities') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Prefilled from the opening where there is one. They read this before accepting.
                            </p>
                        </div>

                        <div class="bg-blue-50 p-3 rounded-lg text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            Sending this does not hire anybody. They see the offer on their
                            application, and it completes when they accept.
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="confirmHire"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-teal-600 text-base font-medium text-white hover:bg-teal-700 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-paper-plane mr-2"></i>Send offer
                    </button>
                    <button wire:click="$set('showHireModal', false)"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Interview results on their own. The same rounds appear in the
         application dialog, but that is the whole 201 file, and somebody
         deciding whether to hire wants the verdicts and the reasons without
         scrolling past a birthplace to reach them. --}}
    @if ($showResultsModal && $resultsApplication)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                 wire:click="$set('showResultsModal', false)"></div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all
                        w-full max-h-[90vh] overflow-y-auto
                        sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Interview results</h3>
                        <p class="text-sm text-gray-500">
                            {{ $resultsApplication->full_name }} &middot; {{ $resultsApplication->position_applied }}
                        </p>
                    </div>
                    <button wire:click="$set('showResultsModal', false)" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="px-5 py-4 space-y-3">
                    @php
                        $verdicts = [
                            'recommend'     => ['Recommends', 'bg-green-100 text-green-800', 'fa-thumbs-up'],
                            'not_recommend' => ['Does not recommend', 'bg-red-100 text-red-800', 'fa-thumbs-down'],
                            'undecided'     => ['Undecided', 'bg-gray-100 text-gray-700', 'fa-circle-question'],
                        ];
                    @endphp

                    @forelse ($resultsRounds as $round)
                        <div class="rounded-lg border p-4 {{ $round->status === 'cancelled' ? 'border-gray-200 bg-gray-50 opacity-70' : 'border-gray-200' }}"
                             wire:key="res-{{ $round->interview_id }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">
                                            Round {{ $round->round }}
                                        </span>
                                        <span class="font-medium text-gray-900">
                                            {{ $round->interviewer_name ?? 'Interviewer removed' }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-0.5">
                                        {{ \Illuminate\Support\Carbon::parse($round->scheduled_at)->format('D j M Y, g:ia') }}
                                        &middot; {{ ucfirst(str_replace('_', ' ', $round->type)) }}
                                        &middot; {{ ucfirst(str_replace('_', ' ', $round->status)) }}
                                    </p>
                                </div>

                                @if ($round->recommendation)
                                    @php [$label, $tone, $icon] = $verdicts[$round->recommendation]; @endphp
                                    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $tone }}">
                                        <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-500">No verdict yet</span>
                                @endif
                            </div>

                            @if (trim((string) $round->hr_notes) !== '')
                                <p class="text-sm text-gray-600 mt-2">
                                    <span class="text-gray-400">Brief given:</span> {{ $round->hr_notes }}
                                </p>
                            @endif

                            @if (trim((string) $round->recommendation_notes) !== '')
                                <div class="mt-2 rounded bg-gray-50 p-3 text-sm text-gray-700">
                                    {{ $round->recommendation_notes }}
                                    @if ($round->recommended_at)
                                        <span class="block text-xs text-gray-500 mt-1">
                                            {{ $round->interviewer_name }},
                                            {{ \Illuminate\Support\Carbon::parse($round->recommended_at)->format('j M Y, g:ia') }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                @if ($round->recommendation)
                                    <p class="mt-2 text-sm text-gray-400">No notes written.</p>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 py-6 text-center">No interviews have been held.</p>
                    @endforelse
                </div>

                <div class="bg-gray-50 px-5 py-3 flex flex-wrap justify-end gap-2">
                    <button wire:click="viewApplication({{ $resultsApplication->application_id }})"
                            class="px-3 py-2 text-sm rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                        Open full application
                    </button>
                    <button wire:click="$set('showResultsModal', false)"
                            class="px-3 py-2 text-sm rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Application Details Modal -->
    @if($showApplicationModal && $selectedApplication)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showApplicationModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Application Details</h3>
                            <p class="text-sm text-gray-500">#{{ $selectedApplication->application_id ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showApplicationModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Applicant Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Applicant Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">Full Name</label>
                                    <p class="font-medium">{{ $selectedApplication->full_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Username</label>
                                    <p class="font-medium">{{ $selectedApplication->username ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Email</label>
                                    <p class="font-medium">{{ $selectedApplication->email ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Current Role</label>
                                    <p class="font-medium">{{ ucfirst($selectedApplication->role ?? 'N/A') }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Application Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Application Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">Position Applied</label>
                                    <p class="font-medium">{{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                                </div>
                                @if (filled($selectedApplication->years_experience ?? null))
                                    <div>
                                        <label class="text-xs text-gray-500">Years of Experience</label>
                                        {{-- The stored value already reads "3-5 years"; this printed
                                             a second "years" after it, and a bare "years" for the
                                             applications that no longer carry the figure at all. --}}
                                        <p class="font-medium">{{ $selectedApplication->years_experience }}</p>
                                    </div>
                                @endif
                                <div>
                                    <label class="text-xs text-gray-500">Application Date</label>
                                    <p class="font-medium">{{ date('M d, Y', strtotime($selectedApplication->application_date ?? now())) }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Status</label>
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'reviewed' => 'bg-blue-100 text-blue-800',
                                            'shortlisted' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'hired' => 'bg-green-100 text-green-800',
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$selectedApplication->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($selectedApplication->status ?? 'pending') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        @include('partials.hr-application-201')
                        {{-- Every round this application has been through, in order.
                             It used to be one block, because the application held one
                             interview - so a second round wrote over the first, and the
                             first interviewer's recommendation ended up printed under
                             the second interviewer's name. --}}
                        @if (count($rounds) > 0)
                            @php
                                $verdicts = [
                                    'recommend'     => ['Recommends', 'bg-green-100 text-green-800', 'fa-thumbs-up'],
                                    'not_recommend' => ['Does not recommend', 'bg-red-100 text-red-800', 'fa-thumbs-down'],
                                    'undecided'     => ['Undecided', 'bg-gray-100 text-gray-700', 'fa-circle-question'],
                                ];
                            @endphp

                            <div class="md:col-span-2 border-t pt-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-medium text-gray-700">
                                        Interviews <span class="text-gray-400">({{ count($rounds) }})</span>
                                    </h4>
                                    <button type="button"
                                            wire:click="openInterviewModal({{ $selectedApplication->application_id }})"
                                            class="text-xs text-red-600 hover:text-red-700 font-medium">
                                        <i class="fas fa-plus mr-1"></i>Add another round
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    @foreach ($rounds as $round)
                                        <div class="rounded-lg border p-4 {{ $round->status === 'cancelled' ? 'border-gray-200 bg-gray-50 opacity-70' : 'border-gray-200' }}">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">
                                                            Round {{ $round->round }}
                                                        </span>
                                                        <span class="font-medium text-gray-900">
                                                            {{ $round->interviewer_name ?? 'Interviewer removed' }}
                                                        </span>
                                                        @if ($round->interviewer_role)
                                                            <span class="text-xs text-gray-500">{{ ucfirst($round->interviewer_role) }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="text-sm text-gray-600 mt-0.5">
                                                        {{ \Illuminate\Support\Carbon::parse($round->scheduled_at)->format('D j M Y, g:ia') }}
                                                        &middot; {{ ucfirst(str_replace('_', ' ', $round->type)) }}
                                                        &middot; {{ ucfirst(str_replace('_', ' ', $round->status)) }}
                                                    </p>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    @if ($round->recommendation)
                                                        @php [$label, $tone, $icon] = $verdicts[$round->recommendation]; @endphp
                                                        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $tone }}">
                                                            <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
                                                        </span>
                                                    @elseif ($round->status !== 'cancelled')
                                                        <span class="text-xs text-gray-500">No recommendation yet</span>
                                                    @endif

                                                    @if ($round->status === 'scheduled')
                                                        <button type="button"
                                                                wire:click="openInterviewModal({{ $selectedApplication->application_id }}, {{ $round->interview_id }})"
                                                                class="text-xs text-gray-500 hover:text-gray-700">Edit</button>
                                                        <button type="button"
                                                                wire:click="cancelInterview({{ $round->interview_id }})"
                                                                class="text-xs text-red-600 hover:text-red-700">Cancel</button>
                                                    @endif
                                                </div>
                                            </div>

                                            @if (trim((string) $round->hr_notes) !== '')
                                                <p class="text-sm text-gray-600 mt-2">
                                                    <span class="text-gray-400">Brief:</span> {{ $round->hr_notes }}
                                                </p>
                                            @endif

                                            @if (trim((string) $round->recommendation_notes) !== '')
                                                <div class="mt-2 rounded bg-gray-50 p-3 text-sm text-gray-700">
                                                    {{ $round->recommendation_notes }}
                                                    @if ($round->recommended_at)
                                                        <span class="block text-xs text-gray-500 mt-1">
                                                            {{ $round->interviewer_name }},
                                                            {{ \Illuminate\Support\Carbon::parse($round->recommended_at)->format('j M Y, g:ia') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <!-- Notes -->
                        @if($selectedApplication->notes ?? false)
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Application Notes</h4>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                {{ $selectedApplication->notes }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6">
                    <button wire:click="$set('showApplicationModal', false)" 
                            class="btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Documents Modal -->
    @if($showDocumentsModal && $selectedApplication)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showDocumentsModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Application Documents</h3>
                            <p class="text-sm text-gray-500">{{ $selectedApplication->full_name ?? 'N/A' }} - {{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showDocumentsModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    @if(count($documents) > 0)
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($documents as $document)
                                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center">
                                                @php
                                                    $fileIcons = [
                                                        'pdf' => 'fas fa-file-pdf text-red-500',
                                                        'doc' => 'fas fa-file-word text-blue-500',
                                                        'docx' => 'fas fa-file-word text-blue-500',
                                                        'xls' => 'fas fa-file-excel text-red-500',
                                                        'xlsx' => 'fas fa-file-excel text-red-500',
                                                        'jpg' => 'fas fa-file-image text-purple-500',
                                                        'jpeg' => 'fas fa-file-image text-purple-500',
                                                        'png' => 'fas fa-file-image text-purple-500',
                                                    ];
                                                    $extension = pathinfo($document->filename, PATHINFO_EXTENSION);
                                                    $fileIcon = $fileIcons[strtolower($extension)] ?? 'fas fa-file text-gray-500';
                                                @endphp
                                                <i class="{{ $fileIcon }} text-2xl mr-3"></i>
                                                <div>
                                                    <div class="font-medium text-gray-900 truncate" title="{{ $document->filename }}">
                                                        {{ $document->filename }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $this->formatFileSize($document->filesize) }} • {{ $document->filetype }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-2">
                                            Uploaded: {{ date('M d, Y h:i A', strtotime($document->uploaded_at)) }}
                                        </div>
                                        <div class="flex gap-2">
                                            <a href="{{ route('applications.document', $document->id) }}" target="_blank" 
                                                    class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100 flex-1">
                                                <i class="fas fa-download mr-1"></i>Download
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-file-alt text-4xl text-gray-300 mb-3"></i>
                            <p class="text-lg text-gray-500">No documents uploaded</p>
                            <p class="text-sm text-gray-400 mt-1">No documents have been uploaded for this application.</p>
                        </div>
                    @endif
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="$set('showDocumentsModal', false)" 
                            class="btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Interview Scheduling Modal -->
    @if($showInterviewModal && $selectedApplication)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showInterviewModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ $editingInterviewId ? 'Reschedule interview' : (count($rounds) > 0 ? 'Schedule another round' : 'Schedule interview') }}
                            </h3>
                            <p class="text-sm text-gray-500">{{ $selectedApplication->full_name ?? 'N/A' }} - {{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showInterviewModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Interview Date -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Interview Date</label>
                                <input type="date" 
                                       wire:model="interviewDate" 
                                       min="{{ date('Y-m-d') }}"
                                       class="form-input">
                                @error('interviewDate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="form-label">Interview Time</label>
                                <input type="time" 
                                       wire:model="interviewTime" 
                                       class="form-input">
                                @error('interviewTime') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        <!-- Interviewer -->
                        <div>
                            <label class="form-label">Interviewer</label>
                            <select wire:model="interviewerId" class="form-input">
                                <option value="">Select Interviewer</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->user_id }}">
                                        {{ $user->full_name }} ({{ ucfirst($user->role) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('interviewerId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Interview Type -->
                        <div>
                            <label class="form-label">Interview Type</label>
                            <select wire:model="interviewType" class="form-input">
                                <option value="in_person">In Person</option>
                                <option value="video">Video Call</option>
                                <option value="phone">Phone Call</option>
                                <option value="technical">Technical Interview</option>
                                <option value="hr">HR Interview</option>
                            </select>
                        </div>
                        
                        <!-- Notes -->
                        <div>
                            <label class="form-label">Interview Notes (Optional)</label>
                            <textarea wire:model="interviewNotes" 
                                      rows="3"
                                      class="form-input"
                                      placeholder="Any special instructions or notes for the interview..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="saveInterviewSchedule" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-calendar-check mr-2"></i>
                        {{ $editingInterviewId ? 'Update schedule' : 'Schedule' }}
                    </button>
                    <button wire:click="$set('showInterviewModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Interview Result Modal -->
    @if($showInterviewResultModal && $selectedApplication)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showInterviewResultModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Interview Results</h3>
                            <p class="text-sm text-gray-500">{{ $selectedApplication->full_name ?? 'N/A' }} - {{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showInterviewResultModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Interview Result -->
                        <div>
                            <label class="form-label">Interview Result</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="inline-flex items-center">
                                    <input type="radio" wire:model="interviewResult" value="passed" class="form-radio text-red-600">
                                    <span class="ml-2 text-red-700">
                                        <i class="fas fa-check-circle mr-1"></i>Passed
                                    </span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" wire:model="interviewResult" value="failed" class="form-radio text-red-600">
                                    <span class="ml-2 text-red-700">
                                        <i class="fas fa-times-circle mr-1"></i>Failed
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Feedback -->
                        <div>
                            <label class="form-label">Interview Feedback</label>
                            <textarea wire:model="interviewFeedback" 
                                      rows="4"
                                      class="form-input"
                                      placeholder="Enter detailed feedback about the interview..."></textarea>
                            @error('interviewFeedback') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Information -->
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="flex">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-700">
                                    <p><strong>Note:</strong></p>
                                    <ul class="mt-1 space-y-1">
                                        <li>• "Passed" will move applicant to Shortlisted</li>
                                        <li>• "Failed" will automatically reject the applicant</li>
                                        <li>• You can still hire or reject shortlisted applicants later</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="saveInterviewResult" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>
                        Save Results
                    </button>
                    <button wire:click="$set('showInterviewResultModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Department Change Modal -->
    @if($showDepartmentModal && $selectedEmployee)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showDepartmentModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Change Employee Department</h3>
                            <p class="text-sm text-gray-500">{{ $selectedEmployee->full_name ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showDepartmentModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Current Department</label>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                @if($selectedEmployee->department_name)
                                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        {{ $selectedEmployee->department_name }}
                                    </span>
                                @else
                                    <span class="text-gray-500">No department assigned</span>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">New Department</label>
                            <select wire:model="newDepartment" class="form-input">
                                <option value="">Select Department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->department_id }}">
                                        {{ $department->department_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="updateDepartment" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Update Department
                    </button>
                    <button wire:click="$set('showDepartmentModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Role Change Modal -->
    @if($showRoleChangeModal && $selectedUserForRoleChange)
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showRoleChangeModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Change User Role</h3>
                            <p class="text-sm text-gray-500">{{ $selectedUserForRoleChange->full_name ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showRoleChangeModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Current Role</label>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <span class="px-3 py-1 rounded-full text-sm font-medium 
                                    {{ $selectedUserForRoleChange->role === 'admin' ? 'bg-red-100 text-red-800' : 
                                       ($selectedUserForRoleChange->role === 'supervisor' ? 'bg-amber-100 text-amber-800' : 
                                       ($selectedUserForRoleChange->role === 'hr' ? 'bg-purple-100 text-purple-800' : 
                                       ($selectedUserForRoleChange->role === 'employee' ? 'bg-red-100 text-red-800' : 
                                       ($selectedUserForRoleChange->role === 'customer' ? 'bg-blue-100 text-blue-800' : 
                                       'bg-indigo-100 text-indigo-800')))) }}">
                                    {{ ucfirst($selectedUserForRoleChange->role ?? 'employee') }}
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">New Role</label>
                            <select wire:model="newRole" class="form-input">
                                <option value="admin">Admin (full system access)</option>
                                <option value="hr">HR (human resources)</option>
                                <option value="leader">Leader</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="employee">Employee</option>
                            </select>
                            
                            <!-- Role descriptions -->
                            <div class="mt-2 text-sm text-gray-600 space-y-1">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-slate-400 rounded-full mr-2"></span>
                                    <span class="font-medium text-red-600">Admin:</span>
                                    <span class="ml-1">Full system access and management</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>
                                    <span class="font-medium text-purple-600">HR:</span>
                                    <span class="ml-1">Human resources and recruitment</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                    <span class="font-medium text-blue-600">Leader:</span>
                                    <span class="ml-1">Staff, with a team under them</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-amber-500 rounded-full mr-2"></span>
                                    <span class="font-medium text-amber-600">Supervisor:</span>
                                    <span class="ml-1">Staff, oversees a section</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full mr-2"></span>
                                    <span class="font-medium text-gray-600">Employee:</span>
                                    <span class="ml-1">Staff</span>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="changeUserRole" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-hr-600 text-base font-medium text-white hover:bg-hr-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-hr-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Change Role
                    </button>
                    <button wire:click="$set('showRoleChangeModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-hr-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <script>
        function viewEmployeeDetails(employeeId) {
            alert('Employee details for ID: ' + employeeId);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                @this.set('showApplicationModal', false);
                @this.set('showResultsModal', false);
                @this.set('showDocumentsModal', false);
                @this.set('showInterviewModal', false);
                @this.set('showInterviewResultModal', false);
                @this.set('showDepartmentModal', false);
                @this.set('showRoleChangeModal', false);
                @this.set('showSalaryModal', false);
            }
        });
    </script>
</div>