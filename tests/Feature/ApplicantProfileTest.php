<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The application form - the 201 file somebody fills in once.
 *
 * It keys on user_id rather than on an application, so the thing worth testing
 * is that everything survives a round trip and that the repeatable sets do not
 * accumulate duplicates when saved twice.
 */
class ApplicantProfileTest extends TestCase
{
    private ?int $userId = null;

    protected function tearDown(): void
    {
        if ($this->userId) {
            foreach (['applicant_disclosures', 'applicant_relatives', 'applicant_references',
                      'applicant_siblings', 'applicant_employment', 'applicant_education',
                      'applicant_profiles'] as $t) {
                DB::table($t)->where('user_id', $this->userId)->delete();
            }
            DB::table('users')->where('user_id', $this->userId)->delete();
        }

        parent::tearDown();
    }

    private function applicant(): User
    {
        $n = random_int(100000, 999999);
        $user = User::create([
            'full_name' => 'Profile Test Person',
            'username'  => "proftest{$n}",
            'email'     => "proftest{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);
        $this->userId = $user->user_id;

        return $user;
    }

    /** Everything step one will not let through, so a test can get past it. */
    private function fillStepOne($component)
    {
        return $component
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->set('p.present_street', '12 Rizal St')
            ->set('p.present_city', 'Naga City')
            ->set('p.present_province', 'Camarines Sur')
            ->set('p.permanent_same_as_present', true)
            ->set('p.cellphone', '09171234567')
            ->set('p.email_address', 'ana@example.test')
            ->set('p.date_of_birth', '1998-04-12')
            ->set('p.birthplace', 'Naga City')
            ->set('p.civil_status', 'single')
            ->set('p.mothers_maiden_name', 'Reyes');
    }

    public function test_the_form_renders(): void
    {
        $this->actingAs($this->applicant())->get('/applicant/profile')->assertOk();
    }

    public function test_a_guest_is_turned_away(): void
    {
        $this->get('/applicant/profile')->assertRedirect('/applicant/login');
    }

    public function test_the_surname_and_first_name_are_required(): void
    {
        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->set('p.surname', '')
            ->set('p.first_name', '')
            ->call('save')
            ->assertHasErrors(['p.surname', 'p.first_name']);
    }

    public function test_everything_survives_a_round_trip(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Dela Cruz')
            ->set('p.first_name', 'Juan')
            ->set('p.middle_name', 'N/A')
            ->set('p.present_street', '12 Rizal St')
            ->set('p.civil_status', 'married')
            ->set('p.spouse_surname', 'Dela Cruz')
            ->set('p.sss_number', '34-1234567-8')
            ->set('p.emergency_name', 'Maria Dela Cruz')
            ->set('edu.elementary.school_name', 'Tampaloc Elementary')
            ->set('edu.high_school.school_name', 'Naga High School')
            ->set('jobs.0.company_name', 'Previous Shop')
            ->set('jobs.0.daily_salary', '610')
            ->set('jobs.0.reason_for_leaving', 'Contract ended')
            ->call('save')
            ->assertHasNoErrors();

        $profile = DB::table('applicant_profiles')->where('user_id', $user->user_id)->first();
        $this->assertSame('Dela Cruz', $profile->surname);
        $this->assertSame('married', $profile->civil_status);
        $this->assertSame('34-1234567-8', $profile->sss_number);

        // Only the levels that were filled in are stored.
        $levels = DB::table('applicant_education')->where('user_id', $user->user_id)->pluck('level')->all();
        sort($levels);
        $this->assertSame(['elementary', 'high_school'], $levels);

        $job = DB::table('applicant_employment')->where('user_id', $user->user_id)->first();
        $this->assertSame('Previous Shop', $job->company_name);
        $this->assertEquals(610, $job->daily_salary);
    }

    /**
     * Saving twice must not double the repeatable sets. They are rewritten
     * rather than matched up, and this is what proves it.
     */
    public function test_saving_twice_does_not_duplicate_the_repeatable_sets(): void
    {
        $user = $this->applicant();

        $component = Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Santos')
            ->set('p.first_name', 'Ana')
            ->set('jobs.0.company_name', 'One Shop')
            ->call('save');

        $component->call('save')->call('save');

        $this->assertSame(1, DB::table('applicant_employment')->where('user_id', $user->user_id)->count());
    }

    public function test_the_disclosures_are_recorded_and_declared(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Reyes')
            ->set('p.first_name', 'Pedro')
            ->set('d.has_medical_condition', '1')
            ->set('d.medical_condition_details', 'Asthma')
            ->set('d.takes_maintenance_medication', '0')
            ->set('d.has_relative_employed', '0')
            ->set('d.ever_terminated', '0')
            ->set('d.ever_convicted', '0')
            ->set('d.employed_elsewhere', '0')
            ->set('d.has_employment_bond', '0')
            ->set('d.was_union_member', '0')
            ->set('d.can_start_immediately', '0')
            ->set('d.days_to_render', '30')
            ->set('d.sss_on_file', '1')
            ->set('d.pagibig_on_file', '1')
            ->set('d.philhealth_on_file', '1')
            ->set('d.tin_on_file', '0')
            ->set('d.declared_name', 'Pedro Reyes')
            ->call('declare')
            ->assertHasNoErrors();

        $d = DB::table('applicant_disclosures')->where('user_id', $user->user_id)->first();
        $this->assertEquals(1, $d->has_medical_condition);
        $this->assertSame('Asthma', $d->medical_condition_details);
        $this->assertEquals(0, $d->ever_convicted, 'answered no must not be stored as unanswered');
        $this->assertEquals(30, $d->days_to_render);
        $this->assertNotNull($d->declared_at, 'the declaration was not stamped');
    }

    public function test_it_opens_on_the_first_step_and_shows_only_that_one(): void
    {
        $html = $this->actingAs($this->applicant())->get('/applicant/profile')->getContent();

        $this->assertStringContainsString('Personal details', $html);
        $this->assertStringNotContainsString('Educational background', $html,
            'every step rendered at once - the form is not stepped');
        $this->assertStringNotContainsString('Applicant disclosures', $html);
    }

    public function test_next_saves_and_moves_on(): void
    {
        $user = $this->applicant();

        $this->fillStepOne(
            Volt::actingAs($user)->test('applicant.profile')->assertSet('step', 1)
        )
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 2);

        // Moving on saved, rather than leaving the step behind unrecorded.
        $this->assertSame('Cruz',
            DB::table('applicant_profiles')->where('user_id', $user->user_id)->value('surname'));
    }

    /** A step that will not save must not be walked away from. */
    public function test_next_will_not_move_past_a_validation_failure(): void
    {
        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->set('p.surname', '')
            ->set('p.first_name', '')
            ->call('next')
            ->assertHasErrors('p.surname')
            ->assertSet('step', 1);
    }

    /**
     * Back never validates. Somebody correcting a typo on step one should not
     * be stopped by a field on step four they have not reached yet.
     */
    public function test_back_is_never_blocked(): void
    {
        $this->fillStepOne(Volt::actingAs($this->applicant())->test('applicant.profile'))
            ->call('next')
            ->assertSet('step', 2)
            ->set('p.surname', '')
            ->call('back')
            ->assertSet('step', 1);
    }

    public function test_steps_cannot_run_off_either_end(): void
    {
        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->call('back')
            ->assertSet('step', 1)
            ->call('goToStep', 99)
            ->assertSet('step', 5)
            ->call('goToStep', -3)
            ->assertSet('step', 1);
    }

    public function test_present_and_permanent_address_are_three_fields_each(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Reyes')
            ->set('p.first_name', 'Liza')
            ->set('p.present_street', '12 Rizal St')
            ->set('p.present_city', 'Naga City')
            ->set('p.present_province', 'Camarines Sur')
            ->set('p.permanent_same_as_present', true)
            ->call('save')
            ->assertHasNoErrors();

        $profile = DB::table('applicant_profiles')->where('user_id', $user->user_id)->first();
        $this->assertSame('Naga City', $profile->present_city);
        // Same as above copies each of the three parts, not one combined string.
        $this->assertSame('12 Rizal St', $profile->permanent_street);
        $this->assertSame('Camarines Sur', $profile->permanent_province);
    }

    public function test_the_sibling_count_decides_how_many_boxes_there_are(): void
    {
        $user = $this->applicant();

        $c = Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->set('p.sibling_count', 3);

        $c->assertCount('siblings', 3);

        // Asking for fewer takes the boxes away again.
        $c->set('p.sibling_count', 1)->assertCount('siblings', 1);

        // And the button adds one past the number first given.
        $c->call('addSibling')->assertCount('siblings', 2)->assertSet('p.sibling_count', 2);

        $c->set('siblings.0.name', 'Jose Cruz')
            ->set('siblings.1.name', 'Rosa Cruz')
            ->call('save')
            ->assertHasNoErrors();

        $names = DB::table('applicant_siblings')->where('user_id', $user->user_id)
            ->orderBy('sort_order')->pluck('name')->all();

        $this->assertSame(['Jose Cruz', 'Rosa Cruz'], $names);
        $this->assertEquals(2, DB::table('applicant_profiles')->where('user_id', $user->user_id)->value('sibling_count'));
    }

    public function test_zero_siblings_is_recorded_as_na(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->set('p.sibling_count', 0)
            ->assertCount('siblings', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['N/A'],
            DB::table('applicant_siblings')->where('user_id', $user->user_id)->pluck('name')->all());
        $this->assertEquals(0,
            DB::table('applicant_profiles')->where('user_id', $user->user_id)->value('sibling_count'));
    }

    /**
     * Answering nothing is not answering zero. Somebody who never reached the
     * field must not be recorded as having said they have no siblings.
     */
    public function test_an_unanswered_sibling_count_is_not_na(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([],
            DB::table('applicant_siblings')->where('user_id', $user->user_id)->pluck('name')->all());
        $this->assertNull(
            DB::table('applicant_profiles')->where('user_id', $user->user_id)->value('sibling_count'));
    }
    /**
     * Blank means "does not apply to me" on this form, which is what N/A
     * means, so it is stored rather than left as nothing.
     */
    public function test_blank_text_fields_are_stored_as_na(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->set('p.middle_name', '')
            ->set('p.bank_account_number', '')
            ->set('p.sss_number', '')
            ->set('p.fathers_name', '')
            ->call('save')
            ->assertHasNoErrors();

        $profile = DB::table('applicant_profiles')->where('user_id', $user->user_id)->first();
        $this->assertSame('N/A', $profile->middle_name);
        $this->assertSame('N/A', $profile->bank_account_number);
        $this->assertSame('N/A', $profile->sss_number);
        $this->assertSame('N/A', $profile->fathers_name);

        // A required field is never papered over with N/A - it stays empty and
        // the step it belongs to refuses to move on.
        $this->assertNull($profile->present_street);
        $this->assertNull($profile->emergency_name);
    }

    /**
     * N/A is a word, and a date or a number column cannot hold one. Those stay
     * null instead of failing the insert.
     */
    public function test_blank_dates_and_numbers_stay_null(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->set('p.date_of_birth', '')
            ->set('p.civil_status', '')
            ->set('jobs.0.company_name', 'Some Shop')
            ->set('jobs.0.daily_salary', '')
            ->set('jobs.0.position', '')
            ->call('save')
            ->assertHasNoErrors();

        $profile = DB::table('applicant_profiles')->where('user_id', $user->user_id)->first();
        $this->assertNull($profile->date_of_birth);
        $this->assertNull($profile->civil_status);

        $job = DB::table('applicant_employment')->where('user_id', $user->user_id)->first();
        $this->assertNull($job->daily_salary, 'a decimal column cannot hold N/A');
        $this->assertSame('N/A', $job->position, 'but the text beside it can');
    }

    /**
     * The one place blank must not become N/A: an unanswered yes/no is not the
     * same as an answered one, and the disclosures are signed.
     */
    public function test_unanswered_disclosures_stay_unanswered(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->set('d.ever_convicted', '0')
            ->call('save')
            ->assertHasNoErrors();

        $d = DB::table('applicant_disclosures')->where('user_id', $user->user_id)->first();
        $this->assertEquals(0, $d->ever_convicted, 'answered no');
        $this->assertNull($d->has_medical_condition, 'never answered - must not read as an answer');
        $this->assertNull($d->days_to_render);
        $this->assertNull($d->available_start_date);
    }

    /**
     * The form used to walk all the way to the end untouched, because surname
     * and first name are filled in from the account and nothing else was
     * asked for. With blanks recording N/A that produced a form that looked
     * answered and said nothing.
     */
    public function test_an_empty_step_one_does_not_go_through(): void
    {
        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->call('next')
            ->assertHasErrors(['p.present_street', 'p.cellphone', 'p.date_of_birth', 'p.civil_status'])
            ->assertSet('step', 1);
    }

    public function test_the_emergency_contact_is_required_to_leave_step_two(): void
    {
        $c = $this->fillStepOne(Volt::actingAs($this->applicant())->test('applicant.profile'))
            ->call('next')
            ->assertSet('step', 2);

        $c->call('next')
            ->assertHasErrors(['p.emergency_name', 'p.emergency_contact_no'])
            ->assertSet('step', 2);

        // Government numbers stay optional - a first job may have none of them.
        $c->set('p.emergency_name', 'Maria Cruz')
            ->set('p.emergency_contact_no', '09171234567')
            ->set('p.emergency_relationship', 'Mother')
            ->set('p.emergency_address', '12 Rizal St, Naga City')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 3);
    }

    public function test_at_least_one_level_of_schooling_is_required(): void
    {
        $c = $this->fillStepOne(Volt::actingAs($this->applicant())->test('applicant.profile'))
            ->call('next')
            ->set('p.emergency_name', 'Maria Cruz')
            ->set('p.emergency_contact_no', '09171234567')
            ->set('p.emergency_relationship', 'Mother')
            ->set('p.emergency_address', '12 Rizal St')
            ->call('next')
            ->assertSet('step', 3);

        $c->call('next')->assertHasErrors('edu')->assertSet('step', 3);

        // Which level is theirs to say; any one of the six will do.
        $c->set('edu.high_school.school_name', 'Naga High School')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 4);
    }

    /** A declaration signed with questions left blank is not a declaration. */
    public function test_every_disclosure_must_be_answered_before_declaring(): void
    {
        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
            ->set('d.declared_name', 'Ana Cruz')
            ->call('declare')
            ->assertHasErrors(['d.has_medical_condition', 'd.ever_convicted', 'd.tin_on_file']);

        $this->assertNull(
            DB::table('applicant_disclosures')->where('user_id', $this->userId)->value('declared_at'),
            'it was signed with questions unanswered');
    }

    /** A typo in a number field must not ask the browser to draw thousands of boxes. */
    public function test_the_sibling_count_is_capped(): void
    {
        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->set('p.sibling_count', 9999)
            ->assertCount('siblings', 20);
    }

    public function test_government_numbers_are_their_own_step(): void
    {
        $html = $this->actingAs($this->applicant())->get('/applicant/profile')->getContent();

        // Step 1 should not carry government numbers or emergency contact any more -
        // those moved to their own step so personal details is not the longest one.
        $this->assertStringNotContainsString('Government numbers', $html);
        $this->assertStringNotContainsString('In case of emergency', $html);

        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->call('goToStep', 2)
            ->assertSee('Government numbers')
            ->assertSee('In case of emergency');
    }

    public function test_certifying_stamps_the_time(): void
    {
        $user = $this->applicant();

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->set('p.surname', 'Lim')
            ->set('p.first_name', 'Grace')
            ->set('p.certified_name', 'Grace Lim')
            ->call('certify')
            ->assertHasNoErrors();

        $profile = DB::table('applicant_profiles')->where('user_id', $user->user_id)->first();
        $this->assertSame('Grace Lim', $profile->certified_name);
        $this->assertNotNull($profile->certified_at);
    }
}
