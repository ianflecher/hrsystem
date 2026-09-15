<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a payslip has to record for holiday pay and the 13th month.
 *
 * time_deduction: what lateness, undertime, absence and unpaid leave took off.
 * It was only ever written into the notes, and the 13th month is a twelfth of
 * the basic salary *earned* - so days not worked have to be subtractable as a
 * figure rather than read back out of a sentence.
 *
 * holiday_pay: the premium for working a holiday, kept apart from basic pay
 * for the same reason - it is excluded from the 13th month base, as overtime
 * already is.
 *
 * kind: a 13th month payment is itself a payslip, and it must not count
 * towards next year's 13th month. Marking the row keeps both in one ledger
 * without one feeding the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->decimal('holiday_pay', 12, 2)->default(0)->after('overtime_pay');
            $t->decimal('time_deduction', 12, 2)->default(0)->after('holiday_pay');
            $t->string('kind', 20)->default('regular')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll', fn (Blueprint $t) => $t->dropColumn(['holiday_pay', 'time_deduction', 'kind']));
    }
};
