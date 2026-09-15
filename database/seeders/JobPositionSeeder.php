<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Starter openings so the careers page is not empty on a fresh install.
 *
 * These are placeholders. Replace them with the real roles from the HR back
 * office at /hr/positions - nothing here needs editing in code.
 */
class JobPositionSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('job_positions')->exists()) {
            $this->command->info('Job openings already present - left alone.');

            return;
        }

        $departments = DB::table('departments')->pluck('department_id', 'department_name');

        $positions = [
            ['Graphic Artist',            'Operations',             'full_time',  'Turn client briefs into print-ready artwork.'],
            ['Screen Printing Operator',  'Operations',             'full_time',  'Run and maintain the press; keep output on spec.'],
            ['Production Assistant',      'Operations',             'part_time',  'Support cutting, pressing, finishing and packing.'],
            ['Sales / Account Officer',   'Operations',             'full_time',  'Handle enquiries and see orders through to delivery.'],
            ['Accounting Staff',          'Finance',                'full_time',  'Invoices, payments and day-to-day bookkeeping.'],
            ['IT Support',                'Information Technology', 'contract',   'Keep the shop floor systems and network running.'],
        ];

        foreach ($positions as [$title, $department, $type, $description]) {
            DB::table('job_positions')->insert([
                'title'           => $title,
                'department_id'   => $departments[$department] ?? null,
                'employment_type' => $type,
                'description'     => $description,
                'is_open'         => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $this->command->info('📢 Seeded '.count($positions).' placeholder openings - replace them at /hr/positions');
    }
}
