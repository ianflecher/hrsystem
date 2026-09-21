<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photograph for an opening.
 *
 * The careers site is almost entirely photographs, and the openings list was
 * the one part of it that was a wall of text. A picture of the room the job is
 * actually done in tells an applicant more about the work than the job title
 * does - which is the whole argument the rest of the site makes.
 *
 * Nullable, and the list falls back to the pathway photograph when it is unset,
 * so an opening posted in a hurry still looks like the rest of the site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
