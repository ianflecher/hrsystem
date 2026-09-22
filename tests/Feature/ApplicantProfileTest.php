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
                      'applicant_employment', 'applicant_education', 'applicant_profiles'] as $t) {
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
            ->set('d.ever_convicted', '0')
            ->set('d.can_start_immediately', '0')
            ->set('d.days_to_render', '30')
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

        Volt::actingAs($user)
            ->test('applicant.profile')
            ->assertSet('step', 1)
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
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
        Volt::actingAs($this->applicant())
            ->test('applicant.profile')
            ->set('p.surname', 'Cruz')
            ->set('p.first_name', 'Ana')
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
