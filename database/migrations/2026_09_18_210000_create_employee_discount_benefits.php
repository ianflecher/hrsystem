<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('employee_discount_policies')) {
            Schema::create('employee_discount_policies', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120)->default('Imprint Customs Employee Product Discount');
                $t->decimal('discount_percent', 5, 2)->default(50);
                $t->date('effective_from')->nullable();
                $t->date('effective_until')->nullable();
                $t->boolean('active')->default(true);
                $t->text('eligible_products')->nullable();
                $t->text('exclusions')->nullable();
                $t->text('usage_rules')->nullable();
                $t->foreignId('updated_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->timestamps();
                $t->index(['active', 'effective_from'], 'edp_active_effective_idx');
            });
        }

        if (!Schema::hasTable('employee_discount_usages')) {
            Schema::create('employee_discount_usages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
                $t->foreignId('policy_id')->nullable()->constrained('employee_discount_policies')->nullOnDelete();
                $t->date('purchase_date');
                $t->string('reference_no', 80)->nullable();
                $t->decimal('gross_amount', 12, 2)->default(0);
                $t->decimal('discount_amount', 12, 2)->default(0);
                $t->decimal('net_amount', 12, 2)->default(0);
                $t->string('status', 20)->default('recorded');
                $t->text('notes')->nullable();
                $t->foreignId('recorded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->timestamps();
                $t->index(['employee_id', 'purchase_date'], 'edu_employee_date_idx');
                $t->index(['policy_id', 'status'], 'edu_policy_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_discount_usages');
        Schema::dropIfExists('employee_discount_policies');
    }
};
