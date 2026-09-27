<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A working day is six punches, not two.
 *
 * The record held only the first and the last, so both breaks were invisible
 * and the hours were worked out by subtracting a fixed break_minutes from the
 * shift - an assumption, applied whether somebody took the break or not.
 *
 * time_in and time_out keep their meaning: the first in and the final out.
 * Everything that already reads them - payroll, the night differential,
 * holiday pay, the reports - is untouched. The four new columns sit between.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance', function (Blueprint $table) {
            $table->dateTime('lunch_in')->nullable()->after('time_in');
            $table->dateTime('lunch_out')->nullable()->after('lunch_in');
            $table->dateTime('cb_in')->nullable()->after('lunch_out');
            $table->dateTime('cb_out')->nullable()->after('cb_in');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance', function (Blueprint $table) {
            $table->dropColumn(['lunch_in', 'lunch_out', 'cb_in', 'cb_out']);
        });
    }
};
