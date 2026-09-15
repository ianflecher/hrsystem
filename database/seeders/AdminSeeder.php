<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('employees')->delete();
        DB::table('departments')->delete();
        DB::table('users')->whereIn('role', ['admin', 'employee'])->delete();

        // --- Back-office accounts -------------------------------------------------
        $staff = [
            [
                'full_name' => 'System Administrator',
                'username'  => 'admin',
                'email'     => 'admin@imprintcustoms.ph',
                'role'      => 'admin',
                'job_title' => 'System Administrator',
                'salary'    => 80000.00,
            ],
            [
                'full_name' => 'HR Manager',
                'username'  => 'hr',
                'email'     => 'hr@imprintcustoms.ph',
                'role'      => 'admin',
                'job_title' => 'HR Manager',
                'salary'    => 65000.00,
            ],
        ];

        // --- Departments ----------------------------------------------------------
        $departmentIds = [];
        foreach (['Human Resources', 'Operations', 'Finance', 'Information Technology'] as $name) {
            $departmentIds[$name] = DB::table('departments')->insertGetId([
                'department_name' => $name,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        foreach ($staff as $person) {
            $userId = DB::table('users')->insertGetId([
                'full_name'  => $person['full_name'],
                'username'   => $person['username'],
                'email'      => $person['email'],
                'password'   => Hash::make('password'),
                'role'       => $person['role'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('employees')->insert([
                'user_id'       => $userId,
                'department_id' => $departmentIds['Human Resources'],
                'job_title'     => $person['job_title'],
                'hire_date'     => '2024-01-01',
                'salary'        => $person['salary'],
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // --- Sample employees so the HR screens are not empty ----------------------
        $employees = [
            ['Maria Santos',   'msantos',  'Operations',             'Shift Supervisor',  32000.00],
            ['Juan Dela Cruz', 'jdelacruz', 'Operations',            'Crew Member',       18000.00],
            ['Ana Reyes',      'areyes',   'Finance',                'Accounting Clerk',  26000.00],
            ['Paolo Garcia',   'pgarcia',  'Information Technology', 'Systems Analyst',   45000.00],
        ];

        foreach ($employees as [$fullName, $username, $department, $jobTitle, $salary]) {
            $userId = DB::table('users')->insertGetId([
                'full_name'  => $fullName,
                'username'   => $username,
                'email'      => $username.'@imprintcustoms.ph',
                'password'   => Hash::make('password'),
                'role'       => 'employee',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('employees')->insert([
                'user_id'       => $userId,
                'department_id' => $departmentIds[$department],
                'job_title'     => $jobTitle,
                'hire_date'     => '2025-03-01',
                'salary'        => $salary,
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        $this->command->info('👑 Admin:    admin / password');
        $this->command->info('👥 HR:       hr / password');
        $this->command->info('🧑 Employee: msantos / password  (and jdelacruz, areyes, pgarcia)');
    }
}
