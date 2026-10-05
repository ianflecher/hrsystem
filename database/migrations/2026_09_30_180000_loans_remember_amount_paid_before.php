<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was already deducted before the HRIS took the loan over, as an amount
 * rather than a count of months.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->decimal('paid_before', 12, 2)->default(0)->after('installment');
        });
        \Illuminate\Support\Facades\DB::statement('UPDATE employee_loans SET paid_before = months_paid_before * installment WHERE months_paid_before > 0');
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->dropColumn('months_paid_before');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->unsignedSmallInteger('months_paid_before')->default(0)->after('installment');
            $t->dropColumn('paid_before');
        });
    }
};
