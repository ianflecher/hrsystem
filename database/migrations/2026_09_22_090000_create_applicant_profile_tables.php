<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The application form: everything asked of somebody applying for a job.
 *
 * Until now an application carried a position, a line about years of
 * experience and whatever files were uploaded. Everything HR actually needs -
 * the name in parts, both addresses, the government numbers, where somebody
 * went to school and who they worked for - was gathered on paper.
 *
 * All six tables key on user_id rather than on an application, so the form is
 * filled once and stays with the person. Somebody who applies twice does not
 * retype it, and when they are hired the whole 201 file is already there -
 * the same way DocumentVault already adopts their application documents.
 *
 * SENSITIVE UNDER THE DATA PRIVACY ACT. applicant_disclosures holds health
 * conditions, medication and criminal history. Access belongs to HR alone,
 * and this is the table to think hardest about before anybody else is given a
 * login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id('profile_id');
            $table->foreignId('user_id')->unique()->constrained('users', 'user_id')->cascadeOnDelete();

            // Kept in parts. A single full_name cannot be sorted by surname,
            // and payroll and government forms all want them separately.
            $table->string('surname', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();

            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->boolean('permanent_same_as_present')->default(false);

            $table->string('cellphone', 40)->nullable();
            $table->string('email_address', 150)->nullable();

            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 60)->nullable();

            $table->date('date_of_birth')->nullable();
            $table->string('birthplace', 150)->nullable();

            $table->enum('civil_status', ['single', 'married', 'live_in', 'widowed', 'separated'])->nullable();
            $table->string('spouse_surname', 100)->nullable();
            $table->string('spouse_first_name', 100)->nullable();
            $table->string('spouse_middle_name', 100)->nullable();

            $table->string('fathers_name', 150)->nullable();
            $table->string('mothers_maiden_name', 150)->nullable();
            $table->text('siblings')->nullable();

            // Held here as well as on employees: an applicant has no employee
            // row yet, and these are asked for on the form.
            $table->string('sss_number', 30)->nullable();
            $table->string('pagibig_number', 30)->nullable();
            $table->string('philhealth_number', 30)->nullable();
            $table->string('tin', 30)->nullable();

            $table->string('emergency_name', 150)->nullable();
            $table->string('emergency_contact_no', 40)->nullable();
            $table->string('emergency_relationship', 80)->nullable();
            $table->text('emergency_address')->nullable();

            // "Signature over printed name", as much as a web form can manage:
            // the name they typed and the moment they certified it.
            $table->string('certified_name', 150)->nullable();
            $table->timestamp('certified_at')->nullable();

            $table->timestamps();
        });

        Schema::create('applicant_education', function (Blueprint $table) {
            $table->id('education_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();

            // junior_high and senior_high for anyone schooled under K-12, and
            // high_school on its own for everybody before it. A form that only
            // offered the split would have older applicants inventing answers.
            $table->enum('level', ['elementary', 'junior_high', 'senior_high',
                                   'high_school', 'vocational', 'tertiary']);
            $table->string('school_name', 180)->nullable();
            $table->string('course', 180)->nullable();
            $table->string('year_from', 9)->nullable();
            $table->string('year_to', 9)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'level']);
        });

        Schema::create('applicant_employment', function (Blueprint $table) {
            $table->id('employment_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();

            $table->string('company_name', 180);
            $table->text('company_address')->nullable();
            $table->string('position', 150)->nullable();
            // Month and year only - nobody remembers the day they started a
            // job from six years ago, and a date picker demanding one invites
            // a made-up answer.
            $table->string('date_from', 20)->nullable();
            $table->string('date_to', 20)->nullable();
            $table->text('reason_for_leaving')->nullable();
            $table->decimal('daily_salary', 12, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('applicant_references', function (Blueprint $table) {
            $table->id('reference_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('contact_no', 40)->nullable();
            $table->string('position_company', 180)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('applicant_relatives', function (Blueprint $table) {
            $table->id('relative_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 80)->nullable();
            $table->string('department', 100)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('applicant_disclosures', function (Blueprint $table) {
            $table->id('disclosure_id');
            $table->foreignId('user_id')->unique()->constrained('users', 'user_id')->cascadeOnDelete();

            // 1. Health. Nullable rather than defaulted: "not answered yet" and
            // "answered no" are different things on a form somebody signs.
            $table->boolean('has_medical_condition')->nullable();
            $table->text('medical_condition_details')->nullable();
            $table->boolean('takes_maintenance_medication')->nullable();
            $table->text('maintenance_medication_details')->nullable();

            // 2. Relatives employed here - the names go in applicant_relatives.
            $table->boolean('has_relative_employed')->nullable();

            // 3. Prior employment and legal.
            $table->boolean('ever_terminated')->nullable();
            $table->text('ever_terminated_details')->nullable();
            $table->boolean('ever_convicted')->nullable();
            $table->text('ever_convicted_details')->nullable();
            $table->boolean('employed_elsewhere')->nullable();
            $table->boolean('has_employment_bond')->nullable();
            $table->text('employment_bond_details')->nullable();
            $table->boolean('was_union_member')->nullable();
            $table->string('union_position', 120)->nullable();
            $table->boolean('can_start_immediately')->nullable();
            $table->unsignedSmallInteger('days_to_render')->nullable();
            $table->date('available_start_date')->nullable();

            // 4. Which government numbers they already hold.
            $table->boolean('sss_on_file')->nullable();
            $table->boolean('pagibig_on_file')->nullable();
            $table->boolean('philhealth_on_file')->nullable();
            $table->boolean('tin_on_file')->nullable();

            $table->string('declared_name', 150)->nullable();
            $table->timestamp('declared_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['applicant_disclosures', 'applicant_relatives', 'applicant_references',
                  'applicant_employment', 'applicant_education', 'applicant_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
