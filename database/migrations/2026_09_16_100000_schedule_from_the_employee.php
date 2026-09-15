<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The schedule moves onto the employee, and holidays become the only input.
 *
 * shift_assignments held one row per person per day, so a calendar only existed
 * where somebody had entered one. An employee's shift times and rest days are
 * stable facts about their job, so they live on the employee row and the
 * calendar is drawn from them. What is genuinely per-date is the holidays,
 * which belong to the company rather than to any one person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->time('shift_end')->nullable()->after('shift_start');
            // ISO weekday numbers, comma separated - see App\Support\WorkWeek.
            $t->string('rest_days', 20)->nullable()->after('shift_end');
        });

        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name', 120);
            $t->enum('type', ['regular', 'special'])->default('regular');
            $t->timestamps();
        });

        Schema::dropIfExists('shift_assignments');
    }

    public function down(): void
    {
        Schema::create('shift_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
            $t->date('work_date');
            $t->time('starts_at')->nullable();
            $t->time('ends_at')->nullable();
            $t->boolean('rest_day')->default(false);
            $t->string('label', 80);
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });

        Schema::dropIfExists('holidays');

        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['shift_end', 'rest_days']));
    }
};
