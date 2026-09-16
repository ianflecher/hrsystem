<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work immersion: paid in full, with nothing withheld.
 *
 * Somebody on immersion is not a regular employee, so no SSS, PhilHealth,
 * Pag-IBIG or withholding tax comes off their pay - they receive the whole of
 * it. That has to end on a date rather than on somebody remembering, so it is
 * a date and not a flag: once the cutoff starts after it, payroll treats them
 * as regular staff without anybody changing anything.
 *
 * Null means a regular employee, which is everybody until HR says otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->date('immersion_until')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('immersion_until'));
    }
};
