<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the company's payslip prints line by line: a basic pay adjustment,
 * legal and special holiday pay apart, overtime hours and minutes late.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->decimal('basic_adjustment', 12, 2)->default(0)->after('basic_pay');
            $t->decimal('legal_holiday_pay', 12, 2)->default(0)->after('holiday_pay');
            $t->decimal('special_holiday_pay', 12, 2)->default(0)->after('legal_holiday_pay');
            $t->decimal('overtime_hours', 8, 2)->default(0)->after('overtime_pay');
            $t->unsignedInteger('late_minutes')->default(0)->after('time_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->dropColumn(['basic_adjustment', 'legal_holiday_pay', 'special_holiday_pay', 'overtime_hours', 'late_minutes']);
        });
    }
};
