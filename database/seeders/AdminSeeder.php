<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The two accounts needed to sign in on a fresh install, and nothing else.
 *
 * No sample departments, employees or openings: invented records are hard to
 * tell apart from real ones once they are in the database, and they turn up in
 * headcounts and reports as if they meant something. Everything else is entered
 * through the app.
 *
 * Change these passwords after the first sign-in.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'full_name' => 'System Administrator',
                'username'  => 'admin',
                'email'     => 'admin@imprintcustoms.ph',
                'job_title' => 'System Administrator',
            ],
            [
                'full_name' => 'HR Manager',
                'username'  => 'hr',
                'email'     => 'hr@imprintcustoms.ph',
                'job_title' => 'HR Manager',
            ],
        ];

        foreach ($accounts as $account) {
            if (DB::table('users')->where('username', $account['username'])->exists()) {
                $this->command->info("{$account['username']} already exists - left alone.");

                continue;
            }

            $userId = DB::table('users')->insertGetId([
                'full_name'  => $account['full_name'],
                'username'   => $account['username'],
                'email'      => $account['email'],
                'password'   => Hash::make('password'),
                'role'       => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Salary is left at the column default rather than invented, and
            // the department is set once real departments exist.
            DB::table('employees')->insert([
                'user_id'       => $userId,
                'department_id' => null,
                'job_title'     => $account['job_title'],
                'hire_date'     => now()->toDateString(),
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $this->command->info("Created {$account['username']}.");
        }

        $this->command->warn('Both accounts use the password "password" - change them after signing in.');
    }
}
