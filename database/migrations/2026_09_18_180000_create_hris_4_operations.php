<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payroll_anomalies')) {
            Schema::create('payroll_anomalies', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->date('period_start');
                $t->string('type', 50);
                $t->string('severity', 15)->default('medium');
                $t->string('message', 255);
                $t->decimal('current_value', 14, 2)->nullable();
                $t->decimal('previous_value', 14, 2)->nullable();
                $t->boolean('resolved')->default(false);
                $t->foreignId('resolved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->timestamp('resolved_at')->nullable();
                $t->timestamps();
                $t->index(['period_start', 'resolved'], 'pa_period_resolved_idx');
                $t->index(['employee_id', 'period_start'], 'pa_employee_period_idx');
            });
        }
        if (!Schema::hasTable('payroll_approval_events')) {
            Schema::create('payroll_approval_events', function (Blueprint $t) {
                $t->id();
                $t->date('period_start');
                $t->date('period_end');
                $t->string('action', 30);
                $t->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->string('ip_address', 45)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['period_start', 'action'], 'pae_period_action_idx');
            });
        }
        if (!Schema::hasTable('employee_assets')) {
            Schema::create('employee_assets', function (Blueprint $t) {
                $t->id();
                $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
                $t->string('asset_type', 80);
                $t->string('asset_tag', 100)->nullable();
                $t->string('serial_number', 120)->nullable();
                $t->string('condition', 30)->default('good');
                $t->date('issued_at')->nullable();
                $t->date('returned_at')->nullable();
                $t->decimal('cost', 12, 2)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['employee_id', 'returned_at'], 'ea_employee_returned_idx');
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('employee_assets');
        Schema::dropIfExists('payroll_approval_events');
        Schema::dropIfExists('payroll_anomalies');
    }
};
