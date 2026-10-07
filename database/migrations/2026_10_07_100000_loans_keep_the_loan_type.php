<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The loan type as the agency prints it on the billing - "(12 Months)", "Salary Loan". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->string('loan_type', 40)->nullable()->after('application_no');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->dropColumn('loan_type');
        });
    }
};
