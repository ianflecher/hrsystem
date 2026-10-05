<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A loan that was already being paid before the HRIS took over its
 * deductions: how many months were deducted the old way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->unsignedSmallInteger('months_paid_before')->default(0)->after('installment');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->dropColumn('months_paid_before');
        });
    }
};
