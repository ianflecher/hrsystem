<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_period_controls', function (Blueprint $t) {
            $t->id();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status', 20)->default('open')->index();
            $t->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('paid_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('locked_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('locked_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_period_controls');
    }
};
