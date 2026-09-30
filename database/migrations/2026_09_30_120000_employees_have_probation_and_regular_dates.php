<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day probation started and the day they become regular, as HR's
 * masterlist keeps them. The regular date is what HR watches: somebody close
 * to it needs their evaluation done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->date('probation_date')->nullable()->after('hire_date');
            $t->date('regular_date')->nullable()->after('probation_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->dropColumn(['probation_date', 'regular_date']);
        });
    }
};
