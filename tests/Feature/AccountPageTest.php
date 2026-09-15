<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The one screen every signed-in person can reach, whichever portal they
 * belong to. It edits only what is theirs - picture, name, password - and
 * nothing HR is responsible for.
 */
class AccountPageTest extends TestCase
{
    private array $createdUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        parent::tearDown();
    }

    private function person(string $role = 'employee'): User
    {
        $n = random_int(100000, 999999);

        $user = User::create([
            'full_name' => 'Before Change',
            'username'  => "acct{$n}",
            'email'     => "acct{$n}@example.test",
            'password'  => 'CurrentPass123',
            'role'      => $role,
        ]);

        DB::table('employees')->insert([
            'user_id'    => $user->user_id,
            'job_title'  => 'Tester',
            'hire_date'  => now()->toDateString(),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createdUserIds[] = $user->user_id;

        return $user->fresh();
    }

    public function test_guests_cannot_reach_it(): void
    {
        $this->get('/account')->assertRedirect();
    }

    public function test_it_opens_for_a_signed_in_person(): void
    {
        $this->actingAs($this->person())->get('/account')->assertOk();
    }

    public function test_someone_can_change_their_own_name(): void
    {
        $user = $this->person();

        Volt::actingAs($user)
            ->test('account')
            ->set('full_name', 'After Change')
            ->call('saveName')
            ->assertHasNoErrors();

        $this->assertSame('After Change', $user->fresh()->full_name);
    }

    public function test_an_email_already_taken_is_refused(): void
    {
        $a = $this->person();
        $b = $this->person();

        Volt::actingAs($a)
            ->test('account')
            ->set('email', $b->email)
            ->call('saveName')
            ->assertHasErrors('email');
    }

    public function test_keeping_your_own_email_is_not_a_clash(): void
    {
        $user = $this->person();

        Volt::actingAs($user)
            ->test('account')
            ->set('full_name', 'Same Email')
            ->set('email', $user->email)
            ->call('saveName')
            ->assertHasNoErrors();
    }

    public function test_a_picture_is_stored_and_can_be_removed(): void
    {
        Storage::fake('public');
        $user = $this->person();

        Volt::actingAs($user)
            ->test('account')
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->call('savePhoto')
            ->assertHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        Volt::actingAs($user->fresh())->test('account')->call('removePhoto');

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_replacing_a_picture_does_not_leave_the_old_file_behind(): void
    {
        Storage::fake('public');
        $user = $this->person();

        Volt::actingAs($user)->test('account')
            ->set('photo', UploadedFile::fake()->image('first.jpg'))
            ->call('savePhoto');
        $first = $user->fresh()->profile_photo_path;

        Volt::actingAs($user->fresh())->test('account')
            ->set('photo', UploadedFile::fake()->image('second.jpg'))
            ->call('savePhoto');
        $second = $user->fresh()->profile_photo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_document_is_not_a_picture(): void
    {
        Storage::fake('public');

        Volt::actingAs($this->person())
            ->test('account')
            ->set('photo', UploadedFile::fake()->create('cv.pdf', 40, 'application/pdf'))
            ->call('savePhoto')
            ->assertHasErrors('photo');
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = $this->person();

        Volt::actingAs($user)
            ->test('account')
            ->set('current_password', 'NotMyPassword')
            ->set('password', 'BrandNewPass456')
            ->set('password_confirmation', 'BrandNewPass456')
            ->call('savePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPass123', $user->fresh()->password));
    }

    public function test_a_correct_current_password_changes_it(): void
    {
        $user = $this->person();

        Volt::actingAs($user)
            ->test('account')
            ->set('current_password', 'CurrentPass123')
            ->set('password', 'BrandNewPass456')
            ->set('password_confirmation', 'BrandNewPass456')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('BrandNewPass456', $user->fresh()->password));
    }

    public function test_changing_it_here_also_clears_a_pending_first_password(): void
    {
        $user = $this->person();
        DB::table('users')->where('user_id', $user->user_id)->update(['must_change_password' => true]);

        Volt::actingAs($user->fresh())
            ->test('account')
            ->set('current_password', 'CurrentPass123')
            ->set('password', 'ChosenByMe789')
            ->set('password_confirmation', 'ChosenByMe789')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertEquals(0, DB::table('users')->where('user_id', $user->user_id)->value('must_change_password'));
    }

    public function test_the_page_does_not_offer_to_edit_what_hr_owns(): void
    {
        $user = $this->person();
        DB::table('employees')->where('user_id', $user->user_id)->update(['salary' => 31337]);

        $page = $this->actingAs($user->fresh())->get('/account');

        // Employment details are shown as read-only context, never as inputs.
        $page->assertSee('Your employment');
        $page->assertSee('Kept by HR');
        $page->assertDontSee('name="salary"', false);
        $page->assertDontSee('wire:model="salary"', false);
    }
}
