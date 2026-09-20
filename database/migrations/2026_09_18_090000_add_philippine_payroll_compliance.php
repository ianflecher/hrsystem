<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            if (!Schema::hasColumn('employees', 'sss_number')) $t->string('sss_number', 30)->nullable()->after('salary');
            if (!Schema::hasColumn('employees', 'philhealth_number')) $t->string('philhealth_number', 30)->nullable()->after('sss_number');
            if (!Schema::hasColumn('employees', 'pagibig_number')) $t->string('pagibig_number', 30)->nullable()->after('philhealth_number');
            if (!Schema::hasColumn('employees', 'tin')) $t->string('tin', 30)->nullable()->after('pagibig_number');
            if (!Schema::hasColumn('employees', 'employment_type')) $t->string('employment_type', 30)->default('regular')->after('tin');
            if (!Schema::hasColumn('employees', 'work_region')) $t->string('work_region', 120)->nullable()->after('employment_type');
        });
        if (Schema::hasColumn('employees', 'work_region') && !collect(Schema::getIndexes('employees'))->contains(fn($i) => ($i['name'] ?? '') === 'employees_work_region_idx')) {
            Schema::table('employees', fn(Blueprint $t) => $t->index('work_region', 'employees_work_region_idx'));
        }

        Schema::table('holidays', function (Blueprint $t) {
            if (!Schema::hasColumn('holidays', 'classification')) $t->string('classification', 30)->default('regular')->after('type');
            if (!Schema::hasColumn('holidays', 'notes')) $t->string('notes', 255)->nullable()->after('classification');
        });
        DB::table('holidays')->where('type', 'special')->update(['classification' => 'special_non_working']);

        Schema::table('hr_payroll', function (Blueprint $t) {
            if (!Schema::hasColumn('hr_payroll', 'basic_pay')) $t->decimal('basic_pay', 12, 2)->default(0)->after('gross_pay');
            if (!Schema::hasColumn('hr_payroll', 'nsd_pay')) $t->decimal('nsd_pay', 12, 2)->default(0)->after('holiday_pay');
            if (!Schema::hasColumn('hr_payroll', 'employer_sss')) $t->decimal('employer_sss', 12, 2)->default(0)->after('sss');
            if (!Schema::hasColumn('hr_payroll', 'employer_ec')) $t->decimal('employer_ec', 12, 2)->default(0)->after('employer_sss');
            if (!Schema::hasColumn('hr_payroll', 'employer_philhealth')) $t->decimal('employer_philhealth', 12, 2)->default(0)->after('philhealth');
            if (!Schema::hasColumn('hr_payroll', 'employer_pagibig')) $t->decimal('employer_pagibig', 12, 2)->default(0)->after('pagibig');
            if (!Schema::hasColumn('hr_payroll', 'taxable_compensation')) $t->decimal('taxable_compensation', 12, 2)->default(0)->after('tax');
            if (!Schema::hasColumn('hr_payroll', 'statutory_rule_version')) $t->string('statutory_rule_version', 60)->nullable()->after('taxable_compensation');
            if (!Schema::hasColumn('hr_payroll', 'statutory_snapshot')) $t->json('statutory_snapshot')->nullable()->after('statutory_rule_version');
        });
        if (collect(Schema::getIndexes('hr_payroll'))->doesntContain(fn($i) => ($i['name'] ?? '') === 'hp_period_kind_status_idx')) {
            Schema::table('hr_payroll', fn(Blueprint $t) => $t->index(['period_start', 'kind', 'status'], 'hp_period_kind_status_idx'));
        }

        if (!Schema::hasTable('payroll_rule_versions')) Schema::create('payroll_rule_versions', function (Blueprint $t) {
            $t->id();
            $t->string('version', 60)->unique();
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->string('jurisdiction', 20)->default('PH');
            $t->json('rules');
            $t->json('sources')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index(['jurisdiction', 'effective_from', 'effective_until'], 'prv_jurisdiction_effective_idx');
        });

        if (!DB::table('payroll_rule_versions')->where('version', config('statutory.version'))->exists()) DB::table('payroll_rule_versions')->insert([
            'version' => config('statutory.version'),
            'effective_from' => '2025-01-01',
            'jurisdiction' => 'PH',
            'rules' => json_encode(config('statutory'), JSON_THROW_ON_ERROR),
            'sources' => json_encode([
                'SSS' => 'https://www.sss.gov.ph/pay-contribution/',
                'PhilHealth' => 'https://www.philhealth.gov.ph/advisories/2025/PA2025-0002.pdf',
                'Pag-IBIG' => 'https://www.pagibigfund.gov.ph/document/pdf/circulars/provident/HDMF%20Circular%20No.%20274%20-%20Revised%20Guidelines%20on%20Pag-IBIG%20Fund%20Membership.pdf',
                'BIR' => 'https://bir-cdn.bir.gov.ph/local/pdf/RR%20No.%2011-2018.pdf',
                'DOLE' => 'https://dole.gov.ph/book-3-conditions-of-employment/',
            ]),
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // This migration is intentionally idempotent because MySQL DDL can partially apply.
        if (Schema::hasTable('payroll_rule_versions')) Schema::dropIfExists('payroll_rule_versions');
        foreach ([
            'basic_pay','nsd_pay','employer_sss','employer_ec','employer_philhealth','employer_pagibig','taxable_compensation','statutory_rule_version','statutory_snapshot'
        ] as $column) {
            if (Schema::hasColumn('hr_payroll', $column)) Schema::table('hr_payroll', fn(Blueprint $t) => $t->dropColumn($column));
        }
        foreach (['classification','notes'] as $column) if (Schema::hasColumn('holidays',$column)) Schema::table('holidays', fn(Blueprint $t) => $t->dropColumn($column));
        foreach (['sss_number','philhealth_number','pagibig_number','tin','employment_type','work_region'] as $column) if (Schema::hasColumn('employees',$column)) Schema::table('employees', fn(Blueprint $t) => $t->dropColumn($column));
    }
};
