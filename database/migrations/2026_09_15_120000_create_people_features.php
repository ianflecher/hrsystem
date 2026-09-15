<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->string('title', 150);
            $t->string('category', 40);
            $t->string('path');
            $t->string('original_name');
            $t->date('expires_on')->nullable()->index();
            $t->foreignId('uploaded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('overtime_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->unsignedInteger('minutes');
            $t->text('reason');
            $t->string('status', 20)->default('pending')->index();
            $t->decimal('approved_amount', 12, 2)->nullable();
            $t->text('decision_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('payroll_id')->nullable()->constrained('hr_payroll', 'payroll_id')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('shift_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->date('work_date');
            $t->time('starts_at')->nullable();
            $t->time('ends_at')->nullable();
            $t->boolean('rest_day')->default(false);
            $t->string('label', 80);
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 150);
            $t->text('body');
            $t->foreignId('department_id')->nullable()->constrained('departments', 'department_id')->nullOnDelete();
            $t->dateTime('published_at');
            $t->dateTime('expires_at')->nullable();
            $t->boolean('archived')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('announcement_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $t->timestamp('acknowledged_at');
            $t->unique(['announcement_id', 'user_id']);
        });
        Schema::create('employee_checklists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->string('type', 20);
            $t->date('due_on');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checklist_id')->constrained('employee_checklists')->cascadeOnDelete();
            $t->string('title', 200);
            $t->string('owner', 20)->default('hr');
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('performance_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->date('due_on');
            $t->text('self_assessment')->nullable();
            $t->text('feedback')->nullable();
            $t->unsignedTinyInteger('rating')->nullable();
            $t->string('status', 20)->default('open');
            $t->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'period_start', 'period_end'], 'review_period_unique');
        });
        Schema::create('performance_goals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $t->string('title', 200);
            $t->unsignedTinyInteger('progress')->default(0);
            $t->timestamps();
        });
        Schema::create('employee_loans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->string('type', 20);
            $t->decimal('amount', 12, 2);
            $t->decimal('installment', 12, 2);
            $t->date('starts_on');
            $t->text('reason');
            $t->string('status', 20)->default('pending')->index();
            $t->text('decision_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('disbursed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('loan_installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $t->foreignId('payroll_id')->constrained('hr_payroll', 'payroll_id')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['loan_id', 'payroll_id']);
        });
        Schema::table('hr_payroll', function (Blueprint $t) {
            $t->decimal('overtime_pay', 12, 2)->default(0);
            $t->decimal('loan_deduction', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll', fn (Blueprint $t) => $t->dropColumn(['overtime_pay', 'loan_deduction']));
        foreach (['loan_installments', 'employee_loans', 'performance_goals', 'performance_reviews', 'checklist_items', 'employee_checklists', 'announcement_reads', 'announcements', 'shift_assignments', 'overtime_requests', 'employee_documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
