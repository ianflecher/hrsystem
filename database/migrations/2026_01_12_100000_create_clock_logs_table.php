<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing table for the employee clock in / clock out widget.
 *
 * The employee and HR attendance screens query this table, but the upstream
 * project (ianflecher/tgif) never shipped a migration for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clock_logs', function (Blueprint $table) {
            $table->id('clock_log_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_logs');
    }
};
