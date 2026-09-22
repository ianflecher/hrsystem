<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * End-to-end cover for the careers portal: register, sign in, track the
 * application, and attach supporting documents.
 */
class ApplicantPortalTest extends TestCase
{
    private array $createdUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $id) {
            DB::table('application_documents')->where('user_id', $id)->delete();
            DB::table('job_applications')->where('user_id', $id)->delete();
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        parent::tearDown();
    }

    private function uniqueSuffix(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function test_applicant_can_register_and_lands_on_the_portal(): void
    {
        $n = $this->uniqueSuffix();

        // Name, email, position, password. Contact details and history are
        // asked once on the applicant's own details form, not twice.
        Volt::test('auth.applicantlogin')
            ->set('full_name', 'Test Applicant')
            ->set('email', "applicant{$n}@example.test")
            ->set('password', 'Password!2345')
            ->set('password_confirmation', 'Password!2345')
            ->set('position', 'Crew Member')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('applicant.index'));

        $user = User::where('email', "applicant{$n}@example.test")->first();
        $this->assertNotNull($user, 'registration should create the user');
        $this->createdUserIds[] = $user->user_id;

        $this->assertDatabaseHas('job_applications', [
            'user_id'          => $user->user_id,
            'position_applied' => 'Crew Member',
            'status'           => 'pending',
        ]);

        $this->assertDatabaseHas('employees', [
            'user_id' => $user->user_id,
            'status'  => 'inactive',
        ]);
    }

    /**
     * users.username is required and unique but no longer asked for, so it is
     * generated from the name. Two people called the same thing must both get
     * through.
     */
    public function test_the_username_is_generated_and_never_collides(): void
    {
        $made = [];

        foreach ([1, 2] as $round) {
            $n = $this->uniqueSuffix();

            Volt::test('auth.applicantlogin')
                ->set('full_name', 'Maria Santos')
                ->set('email', "maria{$round}{$n}@example.test")
                ->set('password', 'Password!2345')
                ->set('password_confirmation', 'Password!2345')
                ->set('position', 'Crew Member')
                ->call('register')
                ->assertHasNoErrors();

            $user = User::where('email', "maria{$round}{$n}@example.test")->first();
            $this->createdUserIds[] = $user->user_id;
            $made[] = $user->username;
        }

        $this->assertSame('maria.santos', $made[0]);
        $this->assertNotSame($made[0], $made[1], 'the same name must not collide');
    }

    public function test_applicant_can_sign_in(): void
    {
        $n = $this->uniqueSuffix();
        $user = $this->makeApplicant($n);

        Volt::test('auth.applicantlogin')
            ->set('email', $user->email)
            ->set('password', 'Password!2345')
            ->set('remember', true)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('applicant.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_portal_shows_the_application_status(): void
    {
        $n = $this->uniqueSuffix();
        $user = $this->makeApplicant($n);

        $this->actingAs($user)
            ->get('/applicant')
            ->assertOk()
            ->assertSee('Crew Member');
    }

    /**
     * The timeline has a step for the details form between applying and being
     * reviewed, because HR reads those details when they review. It reports
     * three states: not started, started, and signed.
     */
    public function test_the_timeline_reports_the_details_form(): void
    {
        $n = $this->uniqueSuffix();
        $user = $this->makeApplicant($n);

        // Nothing filled in yet.
        $this->actingAs($user)->get('/applicant')
            ->assertSee('Your details')
            ->assertSee('Fill in your details');

        // Begun, but not signed off.
        DB::table('applicant_profiles')->insert([
            'user_id' => $user->user_id, 'surname' => 'Applicant', 'first_name' => 'Test',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/applicant')
            ->assertSee('started filling in your details')
            ->assertSee('Finish and sign them');

        // Certified and declared.
        DB::table('applicant_profiles')->where('user_id', $user->user_id)
            ->update(['certified_name' => 'Test Applicant', 'certified_at' => now()]);
        DB::table('applicant_disclosures')->insert([
            'user_id' => $user->user_id, 'declared_name' => 'Test Applicant', 'declared_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/applicant')
            ->assertSee('details are complete')
            ->assertSee('Signed and submitted');

        DB::table('applicant_disclosures')->where('user_id', $user->user_id)->delete();
        DB::table('applicant_profiles')->where('user_id', $user->user_id)->delete();
    }

    public function test_applicant_can_upload_a_supporting_document(): void
    {
        Storage::fake('public');

        $n = $this->uniqueSuffix();
        $user = $this->makeApplicant($n);

        $application = DB::table('job_applications')->where('user_id', $user->user_id)->first();

        Volt::actingAs($user)
            ->test('applicant.index')
            ->set('newDocuments', [UploadedFile::fake()->create('resume.pdf', 64, 'application/pdf')])
            ->call('uploadDocuments')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->application_id,
            'user_id'        => $user->user_id,
            'filename'       => 'resume.pdf',
        ]);

        $stored = DB::table('application_documents')
            ->where('user_id', $user->user_id)
            ->value('filepath');

        Storage::disk('public')->assertExists($stored);
    }

    private function makeApplicant(string $n): User
    {
        $user = User::create([
            'full_name' => 'Test Applicant',
            'username'  => "applicant{$n}",
            'email'     => "applicant{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);

        $this->createdUserIds[] = $user->user_id;

        DB::table('job_applications')->insert([
            'user_id'          => $user->user_id,
            'position_applied' => 'Crew Member',
            'years_experience' => '2',
            'status'           => 'pending',
            'application_date' => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return $user;
    }
}
