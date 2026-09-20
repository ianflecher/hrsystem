<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->boolean('minimum_wage_earner')->default(false)->after('employment_type');
            $t->decimal('minimum_wage_rate', 12, 2)->nullable()->after('minimum_wage_earner');
            $t->string('bir_rdo_code', 10)->nullable()->after('tin');
            $t->date('birth_date')->nullable()->after('bir_rdo_code');
            $t->index(['minimum_wage_earner', 'work_region']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->dropIndex(['minimum_wage_earner', 'work_region']);
            $t->dropColumn(['minimum_wage_earner', 'minimum_wage_rate', 'bir_rdo_code', 'birth_date']);
        });
    }
};
