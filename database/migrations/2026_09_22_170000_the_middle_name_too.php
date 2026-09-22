<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Middle names matter here: government forms ask for one, and the masterlist
 * carries it in its own column. Keeping it only inside the run-together
 * full_name meant it could not be put on a BIR or SSS form without a human
 * guessing which word it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('middle_name', 80)->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('middle_name');
        });
    }
};
