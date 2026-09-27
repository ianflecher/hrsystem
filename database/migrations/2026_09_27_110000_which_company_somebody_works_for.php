<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two companies share this system, and nothing recorded which.
 *
 * It showed up as a collision: IC-00014 is Jonathan Aboy in Production at
 * GKLASAM and Jorgia Tuazon at the cafe. Employee numbers restart per company,
 * so the number alone has never been enough to say who somebody is - the cafe
 * sheet told them apart with a space inside the number, which any trim would
 * have collapsed.
 *
 * Backfilled from the prefix already in use. Nullable, because a company can
 * be added later and a blank is honest about not knowing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('company', 60)->nullable()->after('employee_no')->index();
        });

        DB::table('employees')->where('employee_no', 'like', 'CAFE-%')->update(['company' => 'Imprint Cafe']);
        DB::table('employees')->where('employee_no', 'like', 'IC-%')->update(['company' => 'GKLASAM OPC']);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['company']);
            $table->dropColumn('company');
        });
    }
};
