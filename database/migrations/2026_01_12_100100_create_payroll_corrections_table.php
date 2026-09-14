<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll correction requests raised by employees against a payslip.
 *
 * Written from the insert in resources/views/livewire/employee/payroll.blade.php;
 * the upstream project never shipped a migration for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_corrections', function (Blueprint $table) {
            $table->id('correction_id');
            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('requested_by');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('payroll_id')->references('payroll_id')->on('hr_payroll')->onDelete('cascade');
            $table->foreign('employee_id')->references('employee_id')->on('employees')->onDelete('cascade');
            $table->foreign('requested_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_corrections');
    }
};
