<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A day's times sent in by somebody the scanner cannot record yet - seasonal
 * staff not enrolled. They count only once their supervisor or HR approves
 * them; nobody's own word goes into payroll unchecked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_log_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->date('date');
            $t->dateTime('time_in');
            $t->dateTime('time_out')->nullable();
            $t->string('note', 255)->nullable();
            $t->string('status', 20)->default('pending');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note', 255)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'date']);
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_log_requests');
    }
};
