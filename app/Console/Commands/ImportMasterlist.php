<?php

namespace App\Console\Commands;

use App\Services\SalaryHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates staff accounts from the employee masterlist.
 *
 * Kept as a command rather than a one-off script because it will be run again:
 * the masterlist is the source of truth for who works here, and people join.
 * Run twice it updates rather than duplicates, matching on the employee number,
 * so a corrected spreadsheet can simply be re-imported.
 *
 * It does not set pay. The masterlist has no salary column, so everybody
 * arrives on zero and payroll deliberately skips them until somebody sets it.
 * That is safer than guessing a figure, but it does mean the import is only
 * half the job.
 */
class ImportMasterlist extends Command
{
    protected $signature = 'hris:import-masterlist
        {file : the JSON produced from the spreadsheet}
        {--commit : write it; without this nothing is saved}';

    protected $description = 'Create or update employee accounts from the masterlist';

    /** Every new account's first password; it must be replaced at first sign-in. */
    public const PASSWORD = 'imprint123';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $people = $data['people'] ?? [];

        if (! $people) {
            $this->error('No people in that file.');

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');

        if (! $commit) {
            $this->warn('DRY RUN - nothing will be written. Add --commit to save.');
        }

        // Departments first: an employee cannot point at one that is not there.
        $departments = $this->departments($people, $commit);

        $created = $updated = $skipped = 0;
        $problems = [];

        foreach ($people as $p) {
            try {
                $result = $commit
                    ? DB::transaction(fn () => $this->upsert($p, $departments))
                    : 'would create';

                $result === 'updated' ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $skipped++;
                $problems[] = "{$p['employee_no']} {$p['full_name']}: ".$e->getMessage();
            }
        }

        $this->newLine();
        $this->line(sprintf('  %-22s %d', $commit ? 'created' : 'would create', $created));

        if ($updated) {
            $this->line(sprintf('  %-22s %d', 'updated', $updated));
        }

        if ($problems) {
            $this->newLine();
            $this->error('could not import '.count($problems).':');
            foreach ($problems as $problem) {
                $this->line('   - '.$problem);
            }
        }

        if ($commit) {
            $this->newLine();
            $this->warn('Everybody is on a zero salary, so payroll will skip them all.');
            $this->line('  Set basic pay and allowance per person, or per department, before the next run.');
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string,int> department name => id */
    private function departments(array $people, bool $commit): array
    {
        $wanted = array_filter(array_unique(array_column($people, 'department')));
        $existing = DB::table('departments')->pluck('department_id', 'department_name')->all();
        $byLower = [];

        foreach ($existing as $name => $id) {
            $byLower[mb_strtolower($name)] = $id;
        }

        $map = [];

        foreach ($wanted as $name) {
            $key = mb_strtolower($name);

            if (isset($byLower[$key])) {
                $map[$name] = $byLower[$key];

                continue;
            }

            $this->line("  new department: {$name}");

            if ($commit) {
                $map[$name] = DB::table('departments')->insertGetId([
                    'department_name' => $name, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $map;
    }

    /** @return string 'created' or 'updated' */
    private function upsert(array $p, array $departments): string
    {
        // The employee number is what identifies somebody across re-imports.
        // Names get corrected and people marry; the number does not change.
        // It is not the scanner's id - that is assigned by the device, and
        // somebody can have one without the other.
        $employee = DB::table('employees')->where('employee_no', $p['employee_no'])->first();

        // The name is kept in parts as well as whole: a staff list is read by
        // surname, and the surname cannot be recovered from the full name
        // afterwards - suffixes and multi-word middle names both defeat it.
        $userFields = [
            'full_name'  => $p['full_name'],
            'first_name' => $p['first_name_display'] ?? null,
            'last_name'  => $p['last_name_display'] ?? null,
            'email'      => $p['email'],
            'role'       => 'employee',
            'updated_at' => now(),
        ];

        if ($employee) {
            DB::table('users')->where('user_id', $employee->user_id)->update($userFields);
            $userId = $employee->user_id;
        } else {
            $userId = DB::table('users')->insertGetId($userFields + [
                'username'             => $p['username'],
                'password'             => Hash::make(self::PASSWORD),
                // They cannot reach anything until they have replaced the
                // password everybody was handed.
                'must_change_password' => true,
                'created_at'           => now(),
            ]);
        }

        $employeeFields = [
            'user_id'            => $userId,
            'employee_no'        => $p['employee_no'],
            'job_title'          => $p['job_title'],
            'department_id'      => $departments[$p['department']] ?? null,
            'hire_date'          => $p['hire_date'],
            'birth_date'         => $p['birth_date'],
            'gender'             => $p['gender'] ?: null,
            'contact_number'     => $p['contact'] ?: null,
            'address'            => $p['address'] ?: null,
            'bank_account'       => $p['bank_account'] ?: null,
            'tin'                => $p['tin'] ?: null,
            'sss_number'         => $p['sss'] ?: null,
            'philhealth_number'  => $p['philhealth'] ?: null,
            'pagibig_number'     => $p['pagibig'] ?: null,
            'employment_type'    => $p['employment_type'],
            'status'             => 'active',
            'updated_at'         => now(),
        ];

        if ($employee) {
            // Pay is never touched by a re-import: the masterlist does not
            // carry it, and a blank column must not wipe somebody's salary.
            DB::table('employees')->where('employee_id', $employee->employee_id)->update($employeeFields);

            return 'updated';
        }

        DB::table('employees')->insert($employeeFields + [
            'salary'     => 0,
            'allowance'  => 0,
            'pay_basis'  => 'monthly',
            'created_at' => now(),
        ]);

        return 'created';
    }
}
