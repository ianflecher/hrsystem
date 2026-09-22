<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three boxes, not one big one.
 *
 * present_address and permanent_address were free-text areas; each becomes
 * street, city and province, which is both easier to type into and easier to
 * read back - a report can show just the city without parsing a paragraph.
 * permanent_same_as_present is untouched; it still copies down the same way.
 *
 * siblings was a textarea of one name per line. Most applicants have a
 * handful, so it becomes three named slots rather than an open list.
 *
 * Nothing had been stored in any of these columns when this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn(['present_address', 'permanent_address', 'siblings']);
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->string('present_street', 200)->nullable()->after('middle_name');
            $table->string('present_city', 100)->nullable()->after('present_street');
            $table->string('present_province', 100)->nullable()->after('present_city');

            $table->string('permanent_street', 200)->nullable()->after('present_province');
            $table->string('permanent_city', 100)->nullable()->after('permanent_street');
            $table->string('permanent_province', 100)->nullable()->after('permanent_city');

            $table->string('sibling_1_name', 150)->nullable()->after('mothers_maiden_name');
            $table->string('sibling_2_name', 150)->nullable()->after('sibling_1_name');
            $table->string('sibling_3_name', 150)->nullable()->after('sibling_2_name');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'present_street', 'present_city', 'present_province',
                'permanent_street', 'permanent_city', 'permanent_province',
                'sibling_1_name', 'sibling_2_name', 'sibling_3_name',
            ]);
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->text('present_address')->nullable()->after('middle_name');
            $table->text('permanent_address')->nullable()->after('present_address');
            $table->text('siblings')->nullable()->after('mothers_maiden_name');
        });
    }
};
