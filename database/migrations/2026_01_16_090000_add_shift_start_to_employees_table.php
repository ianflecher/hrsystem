<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What time each person is due to start.
 *
 * Lateness has to be measured from something, and nothing in the schema said
 * when a shift began - the clock-in code simply called anyone arriving after
 * 10:00 late, so a 9:45 arrival counted as on time. Production and office staff
 * start at different hours, so this sits on the employee rather than on a
 * company-wide setting.
 *
 * Nullable on purpose: an employee with no shift set cannot be judged late, and
 * that is the right answer until somebody says what their hours are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->time('shift_start')->nullable()->after('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('shift_start');
        });
    }
};
