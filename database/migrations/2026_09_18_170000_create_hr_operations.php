<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payroll_exceptions')) {
            Schema::create('payroll_exceptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->date('period_start');
                $t->date('period_end');
                $t->string('code', 40);
                $t->string('severity', 15)->default('medium');
                $t->string('message', 255);
                $t->boolean('resolved')->default(false);
                $t->foreignId('resolved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->timestamp('resolved_at')->nullable();
                $t->timestamps();
                $t->index(['period_start', 'resolved'], 'pe_period_resolved_idx');
                $t->index(['employee_id', 'period_start'], 'pe_employee_period_idx');
            });
        }

        if (!Schema::hasTable('employee_salary_history')) {
            Schema::create('employee_salary_history', function (Blueprint $t) {
                $t->id();
                $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
                $t->decimal('salary', 12, 2);
                $t->string('pay_basis', 20)->default('monthly');
                $t->decimal('daily_rate', 12, 2)->nullable();
                $t->date('effective_from');
                $t->date('effective_until')->nullable();
                $t->string('reason', 255)->nullable();
                $t->foreignId('changed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->timestamps();
                $t->index(['employee_id', 'effective_from'], 'esh_employee_effective_idx');
            });
        }

        if (!Schema::hasTable('employee_requests')) {
            Schema::create('employee_requests', function (Blueprint $t) {
                $t->id();
                $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
                $t->string('type', 40);
                $t->string('status', 20)->default('pending');
                $t->date('request_date')->nullable();
                $t->text('details')->nullable();
                $t->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->text('review_note')->nullable();
                $t->timestamp('reviewed_at')->nullable();
                $t->timestamps();
                $t->index(['status', 'type'], 'er_status_type_idx');
                $t->index(['employee_id', 'status'], 'er_employee_status_idx');
            });
        }

        if (!Schema::hasTable('employee_separations')) {
            Schema::create('employee_separations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
                $t->date('separation_date');
                $t->string('reason', 120);
                $t->string('status', 20)->default('open');
                $t->decimal('final_pay', 12, 2)->nullable();
                $t->timestamp('cleared_at')->nullable();
                $t->timestamp('finalized_at')->nullable();
                $t->foreignId('processed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['status', 'separation_date'], 'es_status_date_idx');
                $t->unique(['employee_id', 'separation_date'], 'es_employee_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_separations');
        Schema::dropIfExists('employee_requests');
        Schema::dropIfExists('employee_salary_history');
        Schema::dropIfExists('payroll_exceptions');
    }
};
