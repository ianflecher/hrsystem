<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the agency's billing statement lists for each loan: the application
 * number, the check (DV) it was released on and its date, the loan value, and
 * the term - first and last amortization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->string('application_no', 40)->nullable()->after('type');
            $t->string('check_no', 40)->nullable()->after('application_no');
            $t->date('check_date')->nullable()->after('check_no');
            $t->decimal('loan_value', 12, 2)->nullable()->after('check_date');
            $t->date('term_from')->nullable()->after('loan_value');
            $t->date('term_to')->nullable()->after('term_from');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->dropColumn(['application_no', 'check_no', 'check_date', 'loan_value', 'term_from', 'term_to']);
        });
    }
};
