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
                'full_name' => 'HR Supervisor',
                'username'  => 'hr',
                'email'     => 'hr@imprintcustoms.ph',
                'job_title' => 'HR Supervisor',
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
                'password'   => Hash::make('imprint123'),
                'role'       => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // No employee record. These two are sign-ins, not staff on the
            // payroll, and an employees row with no salary is not a harmless
            // placeholder: the payroll control centre counts active employees
            // without pay details as a high-severity exception, and approval
            // refuses while any exception stands. Two seeded rows were quietly
            // blocking every payroll approval on a fresh install.
            //
            // HR creates the real person through the app, which is the same
            // rule the rest of this seeder follows.

            $this->command->info("Created {$account['username']}.");
        }

        $this->command->warn('Both accounts use the password "imprint123" - change them after signing in.');
    }
}
