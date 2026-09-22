<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An interview is a thing that happens, not a property of an application.
 *
 * job_applications held one interview_date, one interviewer_id, one set of
 * notes - so a second round overwrote the first. Worse, the interviewer's
 * recommendation hung off the same row while interviewer_id moved on, which
 * printed round one's verdict under round two's name: a hiring decision
 * weighed against a recommendation credited to the wrong person.
 *
 * One row per round fixes all of it. Rounds accumulate, each interviewer keeps
 * their own record and their own verdict, and the misattribution becomes
 * impossible rather than merely avoided.
 *
 * The existing interview on every application is carried across first, so
 * nothing already scheduled is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_interviews', function (Blueprint $table) {
            $table->id('interview_id');

            $table->foreignId('application_id')
                ->constrained('job_applications', 'application_id')->cascadeOnDelete();

            // Who conducts it. Kept when the account is deleted rather than
            // taking the interview with it - the round still happened.
            $table->foreignId('interviewer_id')->nullable()
                ->constrained('users', 'user_id')->nullOnDelete();

            $table->unsignedTinyInteger('round')->default(1);

            $table->dateTime('scheduled_at');
            $table->enum('type', ['phone', 'video', 'in_person', 'technical', 'hr'])->default('in_person');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');

            // HR's note when booking it - what they want asked. Distinct from
            // the interviewer's own notes afterwards.
            $table->text('hr_notes')->nullable();

            $table->enum('recommendation', ['recommend', 'not_recommend', 'undecided'])->nullable();
            $table->text('recommendation_notes')->nullable();
            $table->timestamp('recommended_at')->nullable();

            $table->timestamps();

            // The two questions asked of this table: "what rounds has this
            // application had" and "what is waiting for me".
            $table->index(['application_id', 'round']);
            $table->index(['interviewer_id', 'scheduled_at']);
        });

        // Carry across whatever is already scheduled, as round one.
        $existing = DB::table('job_applications')->whereNotNull('interview_date')->get();

        foreach ($existing as $row) {
            DB::table('application_interviews')->insert([
                'application_id' => $row->application_id,
                'interviewer_id' => $row->interviewer_id,
                'round'          => 1,
                'scheduled_at'   => $row->interview_date,
                'type'           => $row->interview_type ?: 'in_person',
                'status'         => $row->interview_status ?: 'scheduled',
                'hr_notes'       => $row->interview_notes,
                'recommendation' => $row->interviewer_recommendation ?? null,
                'recommendation_notes' => $row->recommendation_notes ?? null,
                'recommended_at' => $row->recommended_at ?? null,
                'created_at'     => $row->created_at ?? now(),
                'updated_at'     => now(),
            ]);
        }

        Schema::table('job_applications', function (Blueprint $table) {
            // interviewer_id carries a foreign key to users, and MySQL will not
            // drop a column while a constraint still points through it.
            $table->dropForeign(['interviewer_id']);
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn([
                'interview_date', 'interview_notes', 'interviewer_id', 'interview_type',
                'interview_status', 'interviewer_recommendation', 'recommendation_notes',
                'recommended_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dateTime('interview_date')->nullable()->after('status');
            $table->text('interview_notes')->nullable()->after('interview_date');
            $table->foreignId('interviewer_id')->nullable()->after('interview_notes')
                ->constrained('users', 'user_id')->nullOnDelete();
            $table->enum('interview_type', ['phone', 'video', 'in_person', 'technical', 'hr'])->nullable()->after('interviewer_id');
            $table->enum('interview_status', ['scheduled', 'completed', 'cancelled', 'no_show'])->nullable()->after('interview_type');
            $table->enum('interviewer_recommendation', ['recommend', 'not_recommend', 'undecided'])->nullable()->after('interview_status');
            $table->text('recommendation_notes')->nullable()->after('interviewer_recommendation');
            $table->timestamp('recommended_at')->nullable()->after('recommendation_notes');
        });

        // Only the first round fits back; the rest cannot be represented.
        foreach (DB::table('application_interviews')->orderBy('round')->get()->groupBy('application_id') as $applicationId => $rounds) {
            $first = $rounds->first();

            DB::table('job_applications')->where('application_id', $applicationId)->update([
                'interview_date'   => $first->scheduled_at,
                'interview_notes'  => $first->hr_notes,
                'interviewer_id'   => $first->interviewer_id,
                'interview_type'   => $first->type,
                'interview_status' => $first->status,
                'interviewer_recommendation' => $first->recommendation,
                'recommendation_notes' => $first->recommendation_notes,
                'recommended_at'   => $first->recommended_at,
            ]);
        }

        Schema::dropIfExists('application_interviews');
    }
};
