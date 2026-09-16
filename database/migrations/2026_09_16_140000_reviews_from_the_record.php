<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A review made of what actually happened, and the notices that go with it.
 *
 * The inherited version asked the employee to set progress percentages on
 * goals and write a self-assessment. Nobody was going to do that twice a year,
 * and none of it was connected to anything the system already knew.
 *
 * What replaces it follows the twin-notice rule this company actually has to
 * observe: HR issues a Notice to Explain, the employee answers it in writing,
 * and HR records a decision. Those notices, plus the attendance the system
 * already holds, plus an overall grade, are the review.
 *
 * One table carries both notices. The explanation and the decision are not
 * separate events - they are the same case - and a Notice of Termination is
 * the second half of a case that must have started with a Notice to Explain,
 * so it hangs off the first by parent_id rather than standing alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();

            // Which of the two notices this is: the Notice to Explain that
            // opens a case, or the Notice of Termination that can only follow
            // one - parent_id is what ties the second to the first, so a
            // dismissal always has the explanation it came from attached.
            $t->string('kind', 10)->default('nte');
            $t->foreignId('parent_id')->nullable()->constrained('employee_notices')->nullOnDelete();
            $t->date('effective_on')->nullable();

            // The notice itself.
            $t->date('occurred_on');
            $t->string('type', 30);
            $t->text('allegation');
            // A Notice to Explain carries a deadline to answer; a Notice of
            // Termination carries an effective date instead.
            $t->date('respond_by')->nullable();
            $t->foreignId('issued_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('issued_at')->nullable();

            // The employee's written answer, in their own words.
            $t->text('explanation')->nullable();
            $t->timestamp('explained_at')->nullable();

            // The second notice: what was decided, and by whom.
            $t->string('decision', 30)->nullable();
            $t->text('decision_notes')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();

            $t->string('status', 20)->default('issued');
            $t->timestamps();

            $t->index(['employee_id', 'occurred_on']);
            $t->index(['kind', 'status']);
        });

        Schema::dropIfExists('performance_goals');

        Schema::table('performance_reviews', function (Blueprint $t) {
            $t->dropColumn('self_assessment');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', fn (Blueprint $t) => $t->text('self_assessment')->nullable());

        Schema::create('performance_goals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $t->string('title', 200);
            $t->unsignedTinyInteger('progress')->default(0);
            $t->timestamps();
        });

        Schema::dropIfExists('employee_notices');
    }
};
