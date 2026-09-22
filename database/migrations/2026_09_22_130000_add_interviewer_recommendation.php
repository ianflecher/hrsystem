<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the interviewing supervisor thought.
 *
 * interviewer_id already said who would conduct the interview, but there was
 * nowhere for them to answer - the assignment was written by HR and read back
 * only by HR, so a supervisor could be given an interview and had no way to
 * report on it.
 *
 * Kept apart from interview_notes, which is HR's own note when scheduling
 * ("ask about the night shift"). This is the supervisor's verdict afterwards,
 * and merging the two would lose which of them said what.
 *
 * The decision itself stays with HR: this is a recommendation, and the status
 * column is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->enum('interviewer_recommendation', ['recommend', 'not_recommend', 'undecided'])
                ->nullable()->after('interview_status');

            $table->text('recommendation_notes')->nullable()->after('interviewer_recommendation');

            // Recorded rather than inferred from updated_at, which moves for
            // any edit at all.
            $table->timestamp('recommended_at')->nullable()->after('recommendation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['interviewer_recommendation', 'recommendation_notes', 'recommended_at']);
        });
    }
};
