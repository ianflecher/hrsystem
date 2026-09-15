<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Test employees, for trying the Employees screen at a realistic size.
 *
 * Deliberately not a seeder that runs with `db:seed`: invented people must
 * never appear because somebody ran the normal setup. They have to be asked
 * for, and they are easy to take away again.
 *
 * Every row is marked so it can never be mistaken for a real colleague:
 * usernames start with "demo-" and emails end in @example.test, a domain
 * reserved by RFC 6761 that can never receive mail. --purge finds them by
 * exactly that marker.
 */
class DemoEmployees extends Command
{
    protected $signature = 'demo:employees
                            {--count=100 : How many to create}
                            {--purge : Remove every demo employee instead}';

    protected $description = 'Create or remove clearly-marked test employees';

    private const PREFIX = 'demo-';
    private const DOMAIN = '@example.test';

    public function handle(): int
    {
        if ($this->option('purge')) {
            return $this->purge();
        }

        return $this->create((int) $this->option('count'));
    }

    private function purge(): int
    {
        $ids = DB::table('users')
            ->where('username', 'like', self::PREFIX.'%')
            ->where('email', 'like', '%'.self::DOMAIN)
            ->pluck('user_id');

        if ($ids->isEmpty()) {
            $this->info('No demo employees to remove.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids) {
            DB::table('leaves')->whereIn('employee_id', function ($q) use ($ids) {
                $q->select('employee_id')->from('employees')->whereIn('user_id', $ids);
            })->delete();

            DB::table('hr_attendance')->whereIn('employee_id', function ($q) use ($ids) {
                $q->select('employee_id')->from('employees')->whereIn('user_id', $ids);
            })->delete();

            DB::table('employees')->whereIn('user_id', $ids)->delete();
            DB::table('users')->whereIn('user_id', $ids)->delete();
        });

        $this->info('Removed '.$ids->count().' demo employees.');

        return self::SUCCESS;
    }

    private function create(int $count): int
    {
        if ($count < 1 || $count > 2000) {
            $this->error('Choose a count between 1 and 2000.');

            return self::FAILURE;
        }

        $departmentIds = DB::table('departments')->pluck('department_id')->all();

        if (! $departmentIds) {
            $this->warn('No departments exist, so these will have none. Add some first if you want that column populated.');
        }

        $first = ['Ana', 'Ben', 'Cris', 'Dina', 'Edu', 'Fe', 'Gino', 'Hazel', 'Ivan', 'Joy',
                  'Kim', 'Lito', 'Mika', 'Nilo', 'Ofel', 'Paolo', 'Rica', 'Sam', 'Tina', 'Ursa'];
        $last = ['Abad', 'Bautista', 'Castro', 'Dizon', 'Estrada', 'Flores', 'Garcia', 'Hidalgo',
                 'Ibarra', 'Jimenez', 'Lacson', 'Mendoza', 'Navarro', 'Ocampo', 'Padilla',
                 'Quirino', 'Ramos', 'Salazar', 'Tolentino', 'Velasco'];

        $titles = ['Graphic Artist', 'Screen Printing Operator', 'Production Assistant',
                   'Sales Officer', 'Accounting Staff', 'Warehouse Crew', 'Quality Checker',
                   'Embroidery Operator', 'Cutter', 'Packer'];

        $statuses = ['active', 'active', 'active', 'active', 'on_leave', 'inactive', 'terminated'];
        $roles = ['employee', 'employee', 'employee', 'employee', 'supervisor', 'leader'];

        $created = 0;
        $bar = $this->output->createProgressBar($count);

        for ($i = 1; $i <= $count; $i++) {
            $username = self::PREFIX.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            if (DB::table('users')->where('username', $username)->exists()) {
                $bar->advance();

                continue;
            }

            $name = $first[array_rand($first)].' '.$last[array_rand($last)];

            DB::transaction(function () use ($username, $name, $titles, $statuses, $roles, $departmentIds, &$created) {
                $userId = DB::table('users')->insertGetId([
                    'full_name'  => $name,
                    'username'   => $username,
                    'email'      => $username.self::DOMAIN,
                    'password'   => Hash::make('demo-password'),
                    'role'       => $roles[array_rand($roles)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('employees')->insert([
                    'user_id'       => $userId,
                    'department_id' => $departmentIds ? $departmentIds[array_rand($departmentIds)] : null,
                    'job_title'     => $titles[array_rand($titles)],
                    'hire_date'     => now()->subDays(random_int(30, 2200))->toDateString(),
                    'salary'        => random_int(14, 65) * 1000,
                    'status'        => $statuses[array_rand($statuses)],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $created++;
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Created {$created} demo employees.");
        $this->line('  Every one is marked: username demo-NNNN, email @example.test');
        $this->line('  Remove them all with: php artisan demo:employees --purge');

        return self::SUCCESS;
    }
}
