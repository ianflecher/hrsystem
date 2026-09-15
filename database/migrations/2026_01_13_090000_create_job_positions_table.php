<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job openings, so the careers page stops being a hardcoded list.
 *
 * The openings shown to applicants and the positions offered in the
 * application form were two separate hardcoded arrays that had to be kept in
 * step by hand. Both now read from here, and HR can post or close a role
 * without a developer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id('position_id');
            $table->string('title', 150);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship'])
                ->default('full_time');
            $table->text('description')->nullable();

            // Closing a role keeps it out of the careers page and the
            // application form while leaving past applications readable.
            $table->boolean('is_open')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('department_id')->on('departments')->onDelete('set null');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index(['is_open', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_positions');
    }
};
