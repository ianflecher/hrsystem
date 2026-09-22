<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The offer, and the candidate's answer to it.
 *
 * Hiring went straight from HR pressing a button to an active employee on
 * payroll. Nobody asked the person. They were told the role and the pay on
 * the day they turned up, or over the phone, and the system recorded a
 * decision it had never actually been given.
 *
 * An offer is a thing with two sides: what is being offered - the title, the
 * basic, the allowance, what the job involves, when it starts - and whether it
 * was taken. Both belong in the record, because "they accepted on the 22nd on
 * these terms" is the question asked later, and a status column cannot answer
 * it.
 *
 * Offers are kept when they are declined or superseded rather than deleted: a
 * candidate who turned down 18,000 and accepted 20,000 a week later is a
 * history worth having.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id('offer_id');

            $table->foreignId('application_id')
                ->constrained('job_applications', 'application_id')->cascadeOnDelete();

            $table->string('job_title', 150);

            // The terms as offered, held here rather than read from the
            // employee record later: the employee record changes with every
            // raise, and what somebody agreed to must not change with it.
            $table->enum('pay_basis', ['monthly', 'daily', 'hourly'])->default('monthly');
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->decimal('allowance', 12, 2)->default(0);

            $table->foreignId('department_id')->nullable()
                ->constrained('departments', 'department_id')->nullOnDelete();

            // What the job actually involves. Somebody is agreeing to this, so
            // it is part of the offer and not a note beside it.
            $table->text('responsibilities')->nullable();

            $table->date('starts_on')->nullable();

            $table->enum('status', ['sent', 'accepted', 'declined', 'withdrawn'])->default('sent');

            $table->foreignId('sent_by')->nullable()
                ->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            // Why, in the candidate's own words. Useful when the answer is no.
            $table->text('response_note')->nullable();

            $table->timestamps();

            $table->index(['application_id', 'status']);
        });

        // An application can now be waiting on the candidate rather than on HR.
        DB::statement("ALTER TABLE job_applications MODIFY status
            ENUM('pending','reviewed','shortlisted','offered','rejected','hired') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('job_applications')->where('status', 'offered')->update(['status' => 'shortlisted']);

        DB::statement("ALTER TABLE job_applications MODIFY status
            ENUM('pending','reviewed','shortlisted','rejected','hired') NOT NULL DEFAULT 'pending'");

        Schema::dropIfExists('job_offers');
    }
};
