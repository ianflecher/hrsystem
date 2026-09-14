<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name', 150);
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->enum('role', ['admin', 'manager', 'employee', 'customer', 'supplier'])->default('employee');
            $table->string('email', 150)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index('role');
        });

        // Departments table
        Schema::create('departments', function (Blueprint $table) {
            $table->id('department_id');
            $table->string('department_name', 100);
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            
            $table->foreign('manager_id')->references('user_id')->on('users')->onDelete('set null');
        });


        Schema::create('employees', function (Blueprint $table) {
            $table->id('employee_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('job_title', 100);
            $table->date('hire_date');
            $table->decimal('salary', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'terminated', 'on_leave'])->default('active');
            $table->timestamps();
            
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('department_id')->on('departments')->onDelete('set null');
            $table->index(['status', 'department_id']);
        });

        // HR Attendance table
        Schema::create('hr_attendance', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'on_leave'])->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('employee_id')->references('employee_id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });


        // HR Payroll table
        Schema::create('hr_payroll', function (Blueprint $table) {
            $table->id('payroll_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('employee_id')->references('employee_id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'period_start', 'period_end']);
            $table->index(['status', 'period_end']);
        });


        // Audit logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action'); // create, update, delete, view
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['table_name', 'record_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('hr_payroll');
        Schema::dropIfExists('hr_attendance');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');
    }
};
