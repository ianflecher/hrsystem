<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Basic pay and allowance, kept apart.
 *
 * employees.salary was the whole package, so a payslip could only ever show
 * one figure and the contributions were worked out on all of it. Splitting
 * them matters beyond presentation: an allowance within the de minimis
 * ceilings is paid free of tax and sits outside the SSS, PhilHealth and
 * Pag-IBIG base, while basic pay carries all three.
 *
 * employees.salary keeps its meaning - basic, and the contribution base - so
 * nothing already calculated changes. The allowance starts at zero, which is
 * what everybody implicitly had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('allowance', 12, 2)->default(0)->after('salary');
        });

        Schema::table('hr_payroll', function (Blueprint $table) {
            // What was actually paid as allowance for this period, held on the
            // payslip rather than recomputed from the employee later: the
            // figure can change, and an old payslip must not change with it.
            $table->decimal('allowance', 12, 2)->default(0)->after('basic_pay');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('allowance'));
        Schema::table('hr_payroll', fn (Blueprint $table) => $table->dropColumn('allowance'));
    }
};
