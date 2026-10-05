<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The late penalty taken from a day-rated person's basic pay - an hour for
 * 6-15 minutes late, half a day from 16 - kept so the payslip can print it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->decimal('late_deduction', 12, 2)->default(0)->after('late_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->dropColumn('late_deduction');
        });
    }
};
