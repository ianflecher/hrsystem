<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A raise is a change to the whole pay package, not just the basic. The
 * allowance is taxable and part of the Pag-IBIG base, so a history row that
 * omits it does not say what somebody was actually paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salary_history', function (Blueprint $table) {
            $table->decimal('allowance', 12, 2)->default(0)->after('salary');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_history', function (Blueprint $table) {
            $table->dropColumn('allowance');
        });
    }
};
