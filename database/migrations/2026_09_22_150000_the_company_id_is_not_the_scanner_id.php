<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IC-00001 is the number on somebody's ID card. The biometric id is whatever
 * the scanner enrolled them as. They are not the same thing and one cannot
 * stand in for the other: the masterlist number is assigned by HR and the
 * scanner number by the device, and a person can have one without the other.
 *
 * Holding the company number in biometric_id would have made every imported
 * employee look enrolled on a scanner that has never seen them, and the first
 * real enrolment would have collided with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_no', 40)->nullable()->unique()->after('user_id');
        });

        // Anything already imported went into the wrong column.
        DB::table('employees')->whereNotNull('biometric_id')->where('biometric_id', 'like', 'IC-%')
            ->update([
                'employee_no'  => DB::raw('biometric_id'),
                'biometric_id' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('employees')->whereNotNull('employee_no')->whereNull('biometric_id')
            ->update(['biometric_id' => DB::raw('employee_no')]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['employee_no']);
            $table->dropColumn('employee_no');
        });
    }
};
