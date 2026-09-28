<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Official business: somebody working away from the office - an event, a
 * client site - where there is no scanner. The day is worked, not absent,
 * and not late or short either, because the scanner never saw it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE hr_attendance MODIFY status ENUM('present','absent','late','half_day','on_leave','official_business') NOT NULL DEFAULT 'present'");
    }

    public function down(): void
    {
        DB::table('hr_attendance')->where('status', 'official_business')->update(['status' => 'present']);
        DB::statement("ALTER TABLE hr_attendance MODIFY status ENUM('present','absent','late','half_day','on_leave') NOT NULL DEFAULT 'present'");
    }
};
