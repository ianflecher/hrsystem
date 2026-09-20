<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('work_schedules')) Schema::create('work_schedules', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('employees','employee_id')->cascadeOnDelete();
            $t->date('work_date'); $t->time('start_time')->nullable(); $t->time('end_time')->nullable();
            $t->unsignedInteger('break_minutes')->default(0); $t->boolean('rest_day')->default(false);
            $t->string('source',30)->default('manual'); $t->timestamps();
            $t->unique(['employee_id','work_date'],'ws_employee_date_unique');
            $t->index(['work_date','rest_day'],'ws_date_rest_idx');
        });
        if (!Schema::hasTable('attendance_corrections')) Schema::create('attendance_corrections', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('employees','employee_id')->cascadeOnDelete();
            $t->date('attendance_date'); $t->time('requested_time_in')->nullable(); $t->time('requested_time_out')->nullable();
            $t->text('reason'); $t->string('status',20)->default('pending'); $t->foreignId('reviewed_by')->nullable()->constrained('users','user_id')->nullOnDelete();
            $t->text('review_note')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->index(['employee_id','status'],'ac_employee_status_idx'); $t->index(['attendance_date','status'],'ac_date_status_idx');
        });
        if (!Schema::hasTable('leave_policies')) Schema::create('leave_policies', function (Blueprint $t) {
            $t->id(); $t->string('code',40)->unique(); $t->string('name',100); $t->decimal('annual_days',8,2)->default(0);
            $t->boolean('paid')->default(true); $t->boolean('carry_forward')->default(false); $t->decimal('carry_forward_limit',8,2)->default(0);
            $t->boolean('requires_attachment')->default(false); $t->boolean('active')->default(true); $t->timestamps();
        });
        if (!Schema::hasTable('employee_leave_balances')) Schema::create('employee_leave_balances', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('employees','employee_id')->cascadeOnDelete();
            $t->foreignId('leave_policy_id')->constrained('leave_policies')->cascadeOnDelete(); $t->unsignedSmallInteger('year');
            $t->decimal('entitled',8,2)->default(0); $t->decimal('used',8,2)->default(0); $t->decimal('adjusted',8,2)->default(0); $t->timestamps();
            $t->unique(['employee_id','leave_policy_id','year'],'elb_employee_policy_year_unique');
        });
        if (!Schema::hasTable('employee_lifecycle_events')) Schema::create('employee_lifecycle_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('employees','employee_id')->cascadeOnDelete();
            $t->string('event_type',50); $t->date('effective_date'); $t->json('details')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users','user_id')->nullOnDelete(); $t->timestamps();
            $t->index(['employee_id','effective_date'],'ele_employee_date_idx'); $t->index('event_type','ele_type_idx');
        });
        if (!Schema::hasTable('hr_settings')) Schema::create('hr_settings', function (Blueprint $t) {
            $t->id(); $t->string('setting_group',50); $t->string('setting_key',100); $t->text('setting_value')->nullable(); $t->boolean('is_encrypted')->default(false); $t->timestamps();
            $t->unique(['setting_group','setting_key'],'hrs_group_key_unique');
        });
        if (!Schema::hasTable('employee_notifications')) Schema::create('employee_notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('employees','employee_id')->cascadeOnDelete();
            $t->string('type',50); $t->string('title',160); $t->text('message'); $t->timestamp('read_at')->nullable(); $t->string('action_url',255)->nullable(); $t->timestamps();
            $t->index(['employee_id','read_at'],'en_employee_read_idx');
        });
        if (!Schema::hasTable('training_courses')) Schema::create('training_courses', function (Blueprint $t) {
            $t->id(); $t->string('code',50)->unique(); $t->string('title',160); $t->text('description')->nullable(); $t->unsignedInteger('validity_days')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
        });
        if (!Schema::hasTable('employee_trainings')) Schema::create('employee_trainings', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained('employees','employee_id')->cascadeOnDelete(); $t->foreignId('training_course_id')->constrained('training_courses')->cascadeOnDelete();
            $t->date('completed_at')->nullable(); $t->date('expires_at')->nullable(); $t->string('status',20)->default('planned'); $t->string('certificate_path',255)->nullable(); $t->timestamps();
            $t->index(['employee_id','status'],'et_employee_status_idx'); $t->index('expires_at','et_expires_idx');
        });
    }

    public function down(): void
    {
        foreach (['employee_trainings','training_courses','employee_notifications','hr_settings','employee_lifecycle_events','employee_leave_balances','leave_policies','attendance_corrections','work_schedules'] as $t) Schema::dropIfExists($t);
    }
};
