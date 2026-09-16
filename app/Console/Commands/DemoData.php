<?php

namespace App\Console\Commands;

use App\Services\PayrollRun;
use App\Support\PayPeriod;
use App\Support\Tardiness;
use App\Support\WorkWeek;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * A whole system's worth of test data: people, their days, their pay, and
 * everything they can ask HR for.
 *
 * Deliberately not a seeder that runs with `db:seed`. Invented people must
 * never appear because somebody ran the normal setup - they have to be asked
 * for, and they have to be easy to take away again.
 *
 * Everything is marked. Accounts are demo-NNNN / appl-NNNN at @example.test, a
 * domain reserved by RFC 6761 that can never receive mail; departments,
 * openings and holidays carry a "(demo)" suffix. --purge finds them by exactly
 * those markers and removes what hangs off them, so a real colleague entered
 * alongside these is never touched.
 *
 * Leave entitlements are the one thing not marked and not removed: they are
 * company policy rather than test data, so they are only written when the
 * table is empty and are left alone afterwards.
 */
class DemoData extends Command
{
    protected $signature = 'demo:data
                            {--employees=50 : How many employees to create}
                            {--applicants=50 : How many applicants to create}
                            {--purge : Remove every demo record instead}';

    protected $description = 'Create or remove clearly-marked demo data across every part of the system';

    private const PREFIX = 'demo-';
    private const APPLICANT_PREFIX = 'appl-';
    private const DOMAIN = '@example.test';
    private const SUFFIX = ' (demo)';

    private array $first = ['Ana', 'Ben', 'Cris', 'Dina', 'Edu', 'Fe', 'Gino', 'Hazel', 'Ivan', 'Joy',
        'Kim', 'Lito', 'Mika', 'Nilo', 'Ofel', 'Paolo', 'Rica', 'Sam', 'Tina', 'Ursa',
        'Vic', 'Wena', 'Xyla', 'Yuri', 'Zeny', 'Arnel', 'Bea', 'Carlo', 'Dolly', 'Elmer'];

    private array $last = ['Abad', 'Bautista', 'Castro', 'Dizon', 'Estrada', 'Flores', 'Garcia', 'Hidalgo',
        'Ibarra', 'Jimenez', 'Lacson', 'Mendoza', 'Navarro', 'Ocampo', 'Padilla',
        'Quirino', 'Ramos', 'Salazar', 'Tolentino', 'Velasco', 'Yap', 'Zamora'];

    private array $titles = ['Graphic Artist', 'Screen Printing Operator', 'Production Assistant',
        'Sales Officer', 'Accounting Staff', 'Warehouse Crew', 'Quality Checker',
        'Embroidery Operator', 'Cutter', 'Packer'];

    public function handle(): int
    {
        return $this->option('purge') ? $this->purge() : $this->create();
    }

    // ------------------------------------------------------------------ make

    private function create(): int
    {
        $employees = (int) $this->option('employees');
        $applicants = (int) $this->option('applicants');

        if ($employees < 0 || $employees > 2000 || $applicants < 0 || $applicants > 2000) {
            $this->error('Choose counts between 0 and 2000.');

            return self::FAILURE;
        }

        $this->components->info('Building demo data. Everything is marked and removable.');

        $departments = $this->departments();
        $this->openings($departments);
        $holidays = $this->holidays();
        $this->entitlements();

        $people = $this->employees($employees, $departments);
        $this->attendance($people, $holidays);
        $this->leave($people);
        $this->overtime($people);
        $this->loans($people);
        $this->documents($people);
        $this->checklists($people);
        $this->reviews($people);
        $this->payroll($people);
        $this->applicants($applicants);

        $this->newLine();
        $this->components->info('Done.');
        $this->line('  Employees sign in with their username and the password <options=bold>demo-password</>');
        $this->line('  Remove all of it again with: <options=bold>php artisan demo:data --purge</>');

        return self::SUCCESS;
    }

    /** @return array<int, int> department ids */
    private function departments(): array
    {
        $names = ['Production', 'Sales', 'Accounting', 'Warehouse'];
        $ids = [];

        foreach ($names as $name) {
            $full = $name.self::SUFFIX;

            $ids[] = DB::table('departments')->where('department_name', $full)->value('department_id')
                ?: DB::table('departments')->insertGetId([
                    'department_name' => $full, 'created_at' => now(), 'updated_at' => now(),
                ]);
        }

        $this->components->twoColumnDetail('Departments', count($ids).' ready');

        return $ids;
    }

    private function openings(array $departments): void
    {
        $openings = [
            ['Screen Printing Operator', 'full_time', true],
            ['Graphic Artist', 'full_time', true],
            ['Warehouse Crew', 'contract', true],
            ['Marketing Intern', 'internship', false],
        ];

        foreach ($openings as [$title, $type, $open]) {
            $full = $title.self::SUFFIX;

            if (DB::table('job_positions')->where('title', $full)->exists()) {
                continue;
            }

            DB::table('job_positions')->insert([
                'title'           => $full,
                'department_id'   => $departments ? $departments[array_rand($departments)] : null,
                'employment_type' => $type,
                'description'     => 'Demo opening for testing the careers page and applications.',
                'is_open'         => $open,
                'created_by'      => DB::table('users')->where('username', 'hr')->value('user_id'),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $this->components->twoColumnDetail('Job openings', count($openings).' ready');
    }

    /** @return array<int, string> holiday dates */
    private function holidays(): array
    {
        // Inside the window attendance covers, so the effect on pay is visible.
        $dates = [
            today()->subDays(20)->toDateString() => 'regular',
            today()->subDays(9)->toDateString()  => 'special',
        ];

        foreach ($dates as $date => $type) {
            DB::table('holidays')->updateOrInsert(['date' => $date], [
                'name' => 'Company Day'.self::SUFFIX, 'type' => $type,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->components->twoColumnDetail('Holidays', count($dates).' ready');

        return array_keys($dates);
    }

    private function entitlements(): void
    {
        if (DB::table('leave_entitlements')->exists()) {
            $this->components->twoColumnDetail('Leave entitlements', 'already set - left alone');

            return;
        }

        foreach ([['vacation', 15, 0], ['sick', 15, 0], ['emergency', 3, 0], ['bereavement', 3, 0]] as [$type, $days, $after]) {
            DB::table('leave_entitlements')->insert([
                'leave_type' => $type, 'days_per_year' => $days, 'after_months' => $after,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->components->twoColumnDetail('Leave entitlements', '4 written (yours to change)');
    }

    /** @return array<int, object> the employee rows created */
    private function employees(int $count, array $departments): array
    {
        $statuses = array_merge(array_fill(0, 12, 'active'), ['on_leave', 'inactive', 'terminated']);
        $roles = array_merge(array_fill(0, 10, 'employee'), ['supervisor', 'leader']);
        $shifts = [['08:00:00', '17:00:00', '7'], ['08:00:00', '17:00:00', '7'],
            ['09:00:00', '18:00:00', '6,7'], ['22:00:00', '06:00:00', '7']];

        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(' Employees  %current%/%max% [%bar%]');

        for ($i = 1; $i <= $count; $i++) {
            $username = self::PREFIX.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            if (! DB::table('users')->where('username', $username)->exists()) {
                [$start, $end, $rest] = $shifts[array_rand($shifts)];

                DB::transaction(function () use ($username, $i, $statuses, $roles, $departments, $start, $end, $rest) {
                    $userId = DB::table('users')->insertGetId([
                        'full_name'  => $this->first[array_rand($this->first)].' '.$this->last[array_rand($this->last)],
                        'username'   => $username,
                        'email'      => $username.self::DOMAIN,
                        'password'   => Hash::make('demo-password'),
                        'role'       => $roles[array_rand($roles)],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('employees')->insert([
                        'user_id'       => $userId,
                        'department_id' => $departments ? $departments[array_rand($departments)] : null,
                        'job_title'     => $this->titles[array_rand($this->titles)],
                        'shift_start'   => $start,
                        'shift_end'     => $end,
                        'rest_days'     => $rest,
                        'biometric_id'  => (string) (9000 + $i),
                        'hire_date'     => now()->subDays(random_int(20, 2200))->toDateString(),
                        'salary'        => random_int(14, 45) * 1000,
                        'status'        => $statuses[array_rand($statuses)],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                });
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $this->demoEmployees();
    }

    /** Attendance across the last three cutoffs, with the usual imperfections. */
    private function attendance(array $people, array $holidays): void
    {
        $from = today()->subDays(45);
        $rows = [];

        foreach ($people as $person) {
            if ($person->status !== 'active') {
                continue;
            }

            for ($day = $from->copy(); $day->lt(today()); $day->addDay()) {
                $date = $day->toDateString();

                if (WorkWeek::restsOn($person->rest_days, $day)) {
                    continue;
                }

                // A quarter of the shop works the holiday, which is what earns
                // the premium - the rest of it stays empty, as it should.
                if (in_array($date, $holidays, true) && random_int(1, 4) !== 1) {
                    continue;
                }

                if ($person->hire_date && $date < substr((string) $person->hire_date, 0, 10)) {
                    continue;
                }

                $roll = random_int(1, 100);

                // 4% of days nobody turns up at all, which payroll charges for.
                if ($roll > 96) {
                    continue;
                }

                $shift = Carbon::parse($date.' '.$person->shift_start);
                $out = Carbon::parse($date.' '.$person->shift_end);

                if ($out->lte($shift)) {
                    $out->addDay();
                }

                // 10% arrive late, 6% leave early, the rest are clean.
                $in = $roll > 86 ? $shift->copy()->addMinutes(random_int(6, 75)) : $shift->copy()->subMinutes(random_int(0, 12));
                $left = $roll > 80 && $roll <= 86 ? $out->copy()->subMinutes(random_int(6, 90)) : $out->copy()->addMinutes(random_int(0, 20));

                $rows[] = [
                    'employee_id' => $person->employee_id,
                    'date'        => $date,
                    'time_in'     => $in->toDateTimeString(),
                    'time_out'    => $left->toDateTimeString(),
                    'status'      => Tardiness::isLate($in, $person->shift_start) ? 'late' : 'present',
                    'notes'       => 'Demo data',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('hr_attendance')->insertOrIgnore($chunk);
        }

        $this->components->twoColumnDetail('Attendance', count($rows).' days over the last 45');
    }

    private function leave(array $people): void
    {
        $types = ['vacation', 'sick', 'emergency', 'unpaid', 'bereavement'];
        $statuses = ['approved', 'approved', 'pending', 'rejected'];
        $made = 0;

        foreach (array_slice($people, 0, 20) as $person) {
            if (DB::table('leaves')->where('employee_id', $person->employee_id)->where('reason', 'like', 'Demo%')->exists()) {
                continue;
            }

            $start = today()->subDays(random_int(0, 40))->addDays(random_int(0, 10));
            $days = random_int(1, 3);
            $status = $statuses[array_rand($statuses)];

            DB::table('leaves')->insert([
                'employee_id'      => $person->employee_id,
                'leave_type'       => $types[array_rand($types)],
                'start_date'       => $start->toDateString(),
                'end_date'         => $start->copy()->addDays($days - 1)->toDateString(),
                'total_days'       => $days,
                'reason'           => 'Demo leave request for testing.',
                'status'           => $status,
                'approved_by'      => $status === 'approved' ? DB::table('users')->where('username', 'hr')->value('user_id') : null,
                'approved_at'      => $status === 'approved' ? now() : null,
                'rejection_reason' => $status === 'rejected' ? 'Demo rejection, for testing the screen.' : null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $made++;
        }

        $this->components->twoColumnDetail('Leave requests', $made.' across every status');
    }

    private function overtime(array $people): void
    {
        $hrId = DB::table('users')->where('username', 'hr')->value('user_id');
        $made = 0;

        foreach (array_slice($people, 0, 15) as $i => $person) {
            if (DB::table('overtime_requests')->where('employee_id', $person->employee_id)->where('reason', 'like', 'Demo%')->exists()) {
                continue;
            }

            $day = today()->subDays(random_int(3, 30));
            $starts = Carbon::parse($day->toDateString().' 18:00:00');
            $minutes = random_int(60, 240);
            $approved = $i % 3 !== 0;

            DB::table('overtime_requests')->insert([
                'employee_id'     => $person->employee_id,
                'starts_at'       => $starts->toDateTimeString(),
                'ends_at'         => $starts->copy()->addMinutes($minutes)->toDateTimeString(),
                'minutes'         => $minutes,
                'reason'          => 'Demo overtime: finishing a print run.',
                'status'          => $approved ? 'approved' : 'pending',
                'approved_amount' => $approved ? round(Tardiness::hourlyRate((float) $person->salary) * ($minutes / 60) * 1.25, 2) : null,
                'reviewed_by'     => $approved ? $hrId : null,
                'reviewed_at'     => $approved ? now() : null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $made++;
        }

        $this->components->twoColumnDetail('Overtime', $made.' requests, approved and pending');
    }

    private function loans(array $people): void
    {
        $hrId = DB::table('users')->where('username', 'hr')->value('user_id');
        $made = 0;

        foreach (array_slice($people, 0, 10) as $i => $person) {
            if (DB::table('employee_loans')->where('employee_id', $person->employee_id)->where('reason', 'like', 'Demo%')->exists()) {
                continue;
            }

            $amount = random_int(2, 20) * 1000;
            $status = ['pending', 'approved', 'active', 'active'][$i % 4];

            DB::table('employee_loans')->insert([
                'employee_id' => $person->employee_id,
                'type'        => $i % 2 ? 'loan' : 'cash_advance',
                'amount'      => $amount,
                'installment' => round($amount / random_int(2, 6), 2),
                // Before the cutoffs payroll generates below, so repayments
                // actually come off a payslip rather than waiting.
                'starts_on'   => today()->subDays(60)->toDateString(),
                'reason'      => 'Demo request, for testing repayments through payroll.',
                'status'      => $status,
                'reviewed_by' => $status === 'pending' ? null : $hrId,
                'disbursed_at'=> $status === 'active' ? now() : null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $made++;
        }

        $this->components->twoColumnDetail('Loans', $made.' at every stage');
    }

    private function documents(array $people): void
    {
        $hrId = DB::table('users')->where('username', 'hr')->value('user_id');
        $made = 0;

        foreach (array_slice($people, 0, 12) as $i => $person) {
            if (DB::table('employee_documents')->where('employee_id', $person->employee_id)->where('original_name', 'like', 'demo-%')->exists()) {
                continue;
            }

            $name = 'demo-contract-'.$person->employee_id.'.txt';
            $path = 'employee-documents/'.$name;

            Storage::disk('local')->put($path, "Demo document for testing the vault.\nNot a real contract.\n");

            DB::table('employee_documents')->insert([
                'employee_id'   => $person->employee_id,
                'title'         => ['Employment contract', 'Government ID', 'Training certificate'][$i % 3],
                'category'      => ['contract', 'id', 'certificate'][$i % 3],
                'path'          => $path,
                'original_name' => $name,
                // A third expire soon, so the expiry alert has something to say.
                'expires_on'    => $i % 3 === 1 ? today()->addDays(random_int(-5, 25))->toDateString() : null,
                'uploaded_by'   => $hrId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $made++;
        }

        $this->components->twoColumnDetail('Documents', $made.' in the vault, some expiring');
    }

    private function checklists(array $people): void
    {
        $tasks = [
            'onboarding' => ['Submit identification and signed contract' => 'employee', 'Complete orientation' => 'employee',
                'Issue equipment' => 'hr', 'Set up work access' => 'hr'],
            'offboarding' => ['Return company equipment' => 'employee', 'Complete work handover' => 'employee',
                'Revoke work access' => 'hr', 'Confirm final pay and clearance' => 'hr'],
        ];

        $made = 0;

        foreach (array_slice($people, 0, 8) as $i => $person) {
            if (DB::table('employee_checklists')->where('employee_id', $person->employee_id)->exists()) {
                continue;
            }

            $type = $i % 3 === 0 ? 'offboarding' : 'onboarding';

            $id = DB::table('employee_checklists')->insertGetId([
                'employee_id' => $person->employee_id,
                'type'        => $type,
                'due_on'      => today()->addDays(random_int(-10, 20))->toDateString(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            foreach ($tasks[$type] as $title => $owner) {
                DB::table('checklist_items')->insert([
                    'checklist_id' => $id,
                    'title'        => $title,
                    'owner'        => $owner,
                    'completed_at' => random_int(1, 3) === 1 ? now() : null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $made++;
        }

        $this->components->twoColumnDetail('Checklists', $made.' part-finished');
    }

    private function reviews(array $people): void
    {
        $made = 0;

        foreach (array_slice($people, 0, 10) as $i => $person) {
            // The table holds one review per person per period, and the notice
            // is written with it.
            if (DB::table('performance_reviews')->where('employee_id', $person->employee_id)->exists()) {
                continue;
            }

            $status = ['draft', 'submitted', 'finalized'][$i % 3];

            $id = DB::table('performance_reviews')->insertGetId([
                'employee_id'     => $person->employee_id,
                'period_start'    => today()->subMonths(6)->startOfMonth()->toDateString(),
                'period_end'      => today()->subMonth()->endOfMonth()->toDateString(),
                'due_on'          => today()->addDays(14)->toDateString(),
                'feedback'        => $status === 'finalized' ? 'Demo feedback from the reviewer.' : null,
                'rating'          => $status === 'finalized' ? random_int(3, 5) : null,
                'status'          => $status,
                'finalized_at'    => $status === 'finalized' ? now() : null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // A notice at each stage of the twin-notice process: one waiting on
            // the employee, one they have answered, one already decided.
            $stage = $i % 3;

            DB::table('employee_notices')->insert([
                'employee_id' => $person->employee_id,
                'occurred_on' => today()->subDays(random_int(5, 60))->toDateString(),
                'type'        => ['lateness', 'absence', 'conduct', 'quality'][$i % 4],
                'allegation'  => 'Demo notice: please explain the record for this date.',
                'respond_by'  => today()->addDays($stage === 0 ? 3 : -2)->toDateString(),
                'issued_by'   => DB::table('users')->where('username', 'hr')->value('user_id'),
                'issued_at'   => now(),
                'explanation' => $stage === 0 ? null : 'Demo explanation, written for testing.',
                'explained_at'=> $stage === 0 ? null : now(),
                'decision'      => $stage === 2 ? 'verbal' : null,
                'decision_notes'=> $stage === 2 ? 'Demo decision, for testing the screen.' : null,
                'decided_by'    => $stage === 2 ? DB::table('users')->where('username', 'hr')->value('user_id') : null,
                'decided_at'    => $stage === 2 ? now() : null,
                'status'      => ['issued', 'explained', 'closed'][$stage],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $made++;
        }

        $this->components->twoColumnDetail('Performance reviews', $made.' at every stage');
    }

    /**
     * Two finished cutoffs, so there are payslips to open, approve, pay and
     * argue with - and the third is left for you to run yourself.
     */
    private function payroll(array $people): void
    {
        $run = new PayrollRun;
        $periods = collect(PayPeriod::recent(4))->slice(1, 2)->values();
        $made = 0;

        foreach ($periods as $index => $period) {
            foreach ($people as $person) {
                if ($run->generate($person->employee_id, $period)) {
                    $made++;
                }
            }

            DB::table('hr_payroll')->where('period_start', $period->start)->where('status', 'calculated')
                ->update(['status' => 'approved', 'updated_at' => now()]);

            // The older one is paid; the newer is left approved but unpaid.
            if ($index === 1) {
                $run->markPaid($period->start);
            }
        }

        $this->corrections();
        $this->components->twoColumnDetail('Payroll', $made.' payslips over two cutoffs');
    }

    private function corrections(): void
    {
        $paid = DB::table('hr_payroll as p')
            ->join('employees as e', 'e.employee_id', '=', 'p.employee_id')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('u.username', 'like', self::PREFIX.'%')
            ->orderByDesc('p.payroll_id')->limit(3)
            ->select('p.payroll_id', 'p.employee_id', 'e.user_id')->get();

        foreach ($paid as $i => $row) {
            if (DB::table('payroll_corrections')->where('payroll_id', $row->payroll_id)->exists()) {
                continue;
            }

            DB::table('payroll_corrections')->insert([
                'payroll_id'   => $row->payroll_id,
                'employee_id'  => $row->employee_id,
                'requested_by' => $row->user_id,
                'status'       => $i === 0 ? 'pending' : ($i === 1 ? 'approved' : 'rejected'),
                'description'  => 'Demo dispute: I was here that day but it is counted as absent.',
                'resolution_notes' => $i === 0 ? null : 'Demo reply from HR.',
                'resolved_by'  => $i === 0 ? null : DB::table('users')->where('username', 'hr')->value('user_id'),
                'resolved_at'  => $i === 0 ? null : now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    private function applicants(int $count): void
    {
        if ($count < 1) {
            return;
        }

        $positions = DB::table('job_positions')->pluck('title')->all()
            ?: ['Screen Printing Operator', 'Graphic Artist'];

        $statuses = ['pending', 'pending', 'reviewed', 'shortlisted', 'rejected', 'hired'];
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(' Applicants %current%/%max% [%bar%]');

        for ($i = 1; $i <= $count; $i++) {
            $username = self::APPLICANT_PREFIX.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            if (DB::table('users')->where('username', $username)->exists()) {
                $bar->advance();

                continue;
            }

            DB::transaction(function () use ($username, $positions, $statuses, $i) {
                $userId = DB::table('users')->insertGetId([
                    'full_name'  => $this->first[array_rand($this->first)].' '.$this->last[array_rand($this->last)],
                    'username'   => $username,
                    'email'      => $username.self::DOMAIN,
                    'password'   => Hash::make('demo-password'),
                    'role'       => 'employee',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $status = $statuses[array_rand($statuses)];
                $interviewed = in_array($status, ['shortlisted', 'hired'], true);

                $applicationId = DB::table('job_applications')->insertGetId([
                    'user_id'          => $userId,
                    'position_applied' => $positions[array_rand($positions)],
                    'years_experience' => random_int(0, 12),
                    'status'           => $status,
                    'application_date' => today()->subDays(random_int(1, 60))->toDateString(),
                    'interview_date'   => $interviewed ? today()->addDays(random_int(1, 10))->toDateString() : null,
                    // Not nullable, and defaulted to in_person by the schema, so
                    // an uninterviewed applicant gets the default rather than null.
                    'interview_type'   => $interviewed ? ['in_person', 'phone', 'video'][random_int(0, 2)] : 'in_person',
                    'interview_status' => $interviewed ? 'scheduled' : null,
                    'notes'            => 'Demo application, for testing the hiring screens.',
                    'resume_data'      => json_encode([
                        'filename' => 'resume.txt',
                        'data'     => base64_encode("Demo resume for {$username}.\nNot a real person.\n"),
                    ]),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                // Every third one also uploaded a file, so the vault has
                // something to carry over when they are hired.
                if ($i % 3 === 0) {
                    $path = 'application_documents/'.$applicationId.'/demo-id.txt';
                    Storage::disk('public')->put($path, "Demo application document.\nNot a real ID.\n");

                    DB::table('application_documents')->insert([
                        'application_id' => $applicationId,
                        'user_id'        => $userId,
                        'filename'       => 'demo-id.txt',
                        'filepath'       => $path,
                        'filetype'       => 'text/plain',
                        'filesize'       => 44,
                        'uploaded_at'    => now(),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /** @return array<int, object> */
    private function demoEmployees(): array
    {
        return DB::table('employees as e')
            ->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->where('u.username', 'like', self::PREFIX.'%')
            ->where('u.email', 'like', '%'.self::DOMAIN)
            ->select('e.*')
            ->orderBy('e.employee_id')
            ->get()->all();
    }

    // ----------------------------------------------------------------- purge

    private function purge(): int
    {
        $userIds = DB::table('users')
            ->where(fn ($q) => $q->where('username', 'like', self::PREFIX.'%')
                ->orWhere('username', 'like', self::APPLICANT_PREFIX.'%'))
            ->where('email', 'like', '%'.self::DOMAIN)
            ->pluck('user_id');

        $employeeIds = DB::table('employees')->whereIn('user_id', $userIds)->pluck('employee_id');

        $files = DB::table('employee_documents')->whereIn('employee_id', $employeeIds)->pluck('path');
        $publicFiles = DB::table('application_documents')->whereIn('user_id', $userIds)->pluck('filepath');

        DB::transaction(function () use ($userIds, $employeeIds) {
            $payrollIds = DB::table('hr_payroll')->whereIn('employee_id', $employeeIds)->pluck('payroll_id');
            $loanIds = DB::table('employee_loans')->whereIn('employee_id', $employeeIds)->pluck('id');
            $reviewIds = DB::table('performance_reviews')->whereIn('employee_id', $employeeIds)->pluck('id');
            $checklistIds = DB::table('employee_checklists')->whereIn('employee_id', $employeeIds)->pluck('id');
            $applicationIds = DB::table('job_applications')->whereIn('user_id', $userIds)->pluck('application_id');

            DB::table('loan_installments')->whereIn('loan_id', $loanIds)->orWhereIn('payroll_id', $payrollIds)->delete();
            DB::table('payroll_corrections')->whereIn('payroll_id', $payrollIds)->delete();
            DB::table('employee_notices')->whereIn('employee_id', $employeeIds)->delete();
            DB::table('checklist_items')->whereIn('checklist_id', $checklistIds)->delete();
            DB::table('application_documents')->whereIn('application_id', $applicationIds)->delete();

            foreach (['employee_loans', 'performance_reviews', 'employee_checklists', 'employee_documents',
                'overtime_requests', 'hr_payroll', 'hr_attendance', 'leaves'] as $table) {
                DB::table($table)->whereIn('employee_id', $employeeIds)->delete();
            }

            DB::table('job_applications')->whereIn('user_id', $userIds)->delete();
            DB::table('employees')->whereIn('user_id', $userIds)->delete();

            // Departments first lose their people, or the foreign key holds.
            DB::table('employees')->whereIn('department_id', function ($q) {
                $q->select('department_id')->from('departments')->where('department_name', 'like', '%'.self::SUFFIX);
            })->update(['department_id' => null]);

            DB::table('job_positions')->where('title', 'like', '%'.self::SUFFIX)->delete();
            DB::table('departments')->where('department_name', 'like', '%'.self::SUFFIX)->delete();
            DB::table('holidays')->where('name', 'like', '%'.self::SUFFIX)->delete();
            DB::table('users')->whereIn('user_id', $userIds)->delete();
        });

        foreach ($files as $path) {
            Storage::disk('local')->delete($path);
        }

        foreach ($publicFiles as $path) {
            Storage::disk('public')->delete($path);
        }

        $this->components->info('Removed '.$userIds->count().' demo accounts and everything attached to them.');
        $this->line('  Leave entitlements were left alone: they are policy, not test data.');

        return self::SUCCESS;
    }
}
