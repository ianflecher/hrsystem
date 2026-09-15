<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The enrolment number the scanner knows a person by.
 *
 * A ZKTeco device does not know about employee_id - it reports whatever number
 * the person was enrolled under on the device itself. Without this column there
 * is no way to say whose punch is whose, so every log would be unattributable.
 *
 * Nullable, because somebody may be employed before they are enrolled, and
 * unique, because two people sharing an enrolment number would silently merge
 * their attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('biometric_id', 50)->nullable()->unique()->after('shift_start');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['biometric_id']);
            $table->dropColumn('biometric_id');
        });
    }
};
