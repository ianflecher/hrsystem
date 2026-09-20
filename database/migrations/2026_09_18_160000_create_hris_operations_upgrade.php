<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regional_wage_orders', function (Blueprint $t) {
            $t->id();
            $t->string('region_code', 30)->index();
            $t->string('region_name', 150);
            $t->string('wage_order_code', 60)->index();
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->decimal('daily_minimum_wage', 12, 2);
            $t->decimal('hourly_minimum_wage', 12, 2)->nullable();
            $t->string('classification', 100)->nullable();
            $t->string('source_url', 500)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamps();
            $t->index(['region_code', 'effective_from', 'effective_until'], 'rwo_region_effective_idx');
        });

        Schema::create('payroll_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->date('effective_date');
            $t->string('type', 40);
            $t->decimal('amount', 12, 2)->default(0);
            $t->boolean('taxable')->default(true);
            $t->boolean('recurring')->default(false);
            $t->text('reason');
            $t->string('status', 20)->default('pending')->index();
            $t->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'effective_date', 'status']);
        });

        Schema::create('attendance_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->date('work_date');
            $t->string('type', 40);
            $t->string('severity', 20)->default('medium');
            $t->text('details')->nullable();
            $t->string('status', 20)->default('open')->index();
            $t->foreignId('resolved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->text('resolution_note')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'work_date', 'type']);
        });

        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->string('pay_basis', 20)->default('monthly')->after('period_end');
            $t->decimal('paid_days', 8, 2)->default(0)->after('pay_basis');
            $t->decimal('paid_hours', 10, 2)->default(0)->after('paid_days');
            $t->decimal('base_rate', 12, 2)->default(0)->after('paid_hours');
            $t->decimal('adjustments', 12, 2)->default(0)->after('base_rate');
            $t->index(['period_start', 'pay_basis']);
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->dropIndex(['period_start', 'pay_basis']);
            $t->dropColumn(['pay_basis', 'paid_days', 'paid_hours', 'base_rate', 'adjustments']);
        });
        Schema::dropIfExists('attendance_exceptions');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('regional_wage_orders');
    }
};
