<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contribution and tax figures, as columns.
 *
 * They were only ever written into the notes sentence. A payslip somebody can
 * print has to show each one, and recomputing them later would be wrong the
 * moment a salary changes - what was withheld in March must still read as what
 * was withheld in March.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->decimal('sss', 12, 2)->default(0)->after('time_deduction');
            $t->decimal('philhealth', 12, 2)->default(0)->after('sss');
            $t->decimal('pagibig', 12, 2)->default(0)->after('philhealth');
            $t->decimal('tax', 12, 2)->default(0)->after('pagibig');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll', fn (Blueprint $t) => $t->dropColumn(['sss', 'philhealth', 'pagibig', 'tax']));
    }
};
