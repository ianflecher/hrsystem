<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Inactive" said nothing about why somebody left. Leavers are now resigned,
 * terminated or AWOL, each with a reason and a last day in
 * employee_separations. Everybody already inactive resigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employees MODIFY status ENUM('active','inactive','resigned','terminated','on_leave','awol') NOT NULL DEFAULT 'active'");

        foreach (DB::table('employees')->where('status', 'inactive')->get(['employee_id', 'updated_at']) as $e) {
            DB::table('employees')->where('employee_id', $e->employee_id)->update(['status' => 'resigned']);
            if (! DB::table('employee_separations')->where('employee_id', $e->employee_id)->exists()) {
                DB::table('employee_separations')->insert([
                    'employee_id' => $e->employee_id,
                    'separation_date' => substr((string) $e->updated_at, 0, 10) ?: now()->toDateString(),
                    'reason' => 'Not recorded - marked inactive before reasons were kept',
                    'status' => 'resigned', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('employees')->where('status', 'resigned')->update(['status' => 'inactive']);
        DB::statement("ALTER TABLE employees MODIFY status ENUM('active','inactive','terminated','on_leave','awol') NOT NULL DEFAULT 'active'");
    }
};
