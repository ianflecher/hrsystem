<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An "other" government loan names its agency - GSIS, a cooperative, ... */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->string('agency', 60)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $t) {
            $t->dropColumn('agency');
        });
    }
};
