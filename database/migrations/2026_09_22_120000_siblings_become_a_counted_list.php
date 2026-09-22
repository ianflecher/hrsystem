<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ask how many siblings, then give them that many boxes.
 *
 * Three fixed slots guessed at the answer: somebody with one sibling saw two
 * empty boxes, somebody with five ran out. The count is asked first and the
 * names follow it, so the form fits the family rather than the other way
 * round.
 *
 * sibling_count is kept alongside the names because it is an answer in its
 * own right - "four siblings" is true even before the names are typed, and it
 * is what the form reopens with.
 *
 * Nothing had been stored in the three columns this replaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn(['sibling_1_name', 'sibling_2_name', 'sibling_3_name']);
            $table->unsignedTinyInteger('sibling_count')->nullable()->after('mothers_maiden_name');
        });

        Schema::create('applicant_siblings', function (Blueprint $table) {
            $table->id('sibling_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_siblings');

        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn('sibling_count');
            $table->string('sibling_1_name', 150)->nullable()->after('mothers_maiden_name');
            $table->string('sibling_2_name', 150)->nullable()->after('sibling_1_name');
            $table->string('sibling_3_name', 150)->nullable()->after('sibling_2_name');
        });
    }
};
