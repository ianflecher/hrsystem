<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->string('pay_basis', 20)->default('monthly')->after('salary');
            $t->decimal('daily_rate', 12, 2)->nullable()->after('pay_basis');
            $t->string('wage_order_code', 60)->nullable()->after('work_region');
            $t->index(['pay_basis', 'status']);
        });

        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->decimal('other_taxable_compensation', 12, 2)->default(0)->after('taxable_compensation');
            $t->decimal('mwe_exempt_compensation', 12, 2)->default(0)->after('other_taxable_compensation');
            $t->timestamp('rules_verified_at')->nullable()->after('statutory_snapshot');
            $t->decimal('employer_total_cost', 12, 2)->default(0)->after('employer_pagibig');
            $t->index(['employee_id', 'period_start', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->dropIndex(['employee_id', 'period_start', 'status']);
            $t->dropColumn(['other_taxable_compensation', 'mwe_exempt_compensation', 'rules_verified_at', 'employer_total_cost']);
        });
        Schema::table('employees', function (Blueprint $t) {
            $t->dropIndex(['pay_basis', 'status']);
            $t->dropColumn(['pay_basis', 'daily_rate', 'wage_order_code']);
        });
    }
};
