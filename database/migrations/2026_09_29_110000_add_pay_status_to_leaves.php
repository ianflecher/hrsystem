<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (! Schema::hasColumn('leaves', 'pay_status')) {
                $table->string('pay_status', 10)->default('paid')->after('leave_type');
            }
        });

        DB::table('leaves')
            ->where('leave_type', 'unpaid')
            ->update(['pay_status' => 'unpaid']);
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (Schema::hasColumn('leaves', 'pay_status')) {
                $table->dropColumn('pay_status');
            }
        });
    }
};
