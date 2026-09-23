<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The screen somebody meets on their first sign-in.
 *
 * It was unusable and the whole suite was blind to it. The middleware let
 * Livewire through by matching the path 'livewire/*', which is not where
 * Livewire serves from - the path carries a generated suffix. So every request
 * the screen made to its own component was redirected back to the screen, and
 * Livewire received a page of HTML where it expected JSON. It gave up and
 * reloaded: the form emptied itself and said nothing, whatever was typed.
 *
 * Testing the component directly could never have caught it, because the
 * component was always fine. The test has to go through the middleware.
 */
class ForcedPasswordChangeTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $n = random_int(100000, 999999);

        // Straight to the table: must_change_password is not fillable, so
        // User::create() would silently drop it and the test would pass
        // against an account that was never forced to change anything.
        $id = DB::table('users')->insertGetId([
            'full_name' => 'First Signin', 'first_name' => 'First', 'last_name' => 'Signin',
            'username' => "fs{$n}", 'email' => "fs{$n}@example.test",
            'password' => Hash::make('imprint123'),
            'must_change_password' => true, 'role' => 'employee',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->user = User::find($id);
    }

    protected function tearDown(): void
    {
        DB::table('employees')->where('user_id', $this->user->user_id)->delete();
        DB::table('users')->where('user_id', $this->user->user_id)->delete();

        parent::tearDown();
    }

    public function test_the_flag_is_actually_set(): void
    {
        $this->assertTrue((bool) $this->user->must_change_password,
            'the fixture never forced a password change, so nothing below proves anything');
    }

    public function test_they_are_sent_to_the_screen_before_anywhere_else(): void
    {
        $this->actingAs($this->user)->get('/employee/dashboard')
            ->assertRedirect(route('password.change'));
    }

    /**
     * The one that was broken. Livewire's own endpoint must reach the
     * component rather than being bounced back to the page.
     */
    public function test_livewires_request_is_not_redirected_away(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Livewire', 'true')
            ->post('/some-other-page');

        $this->assertNotEquals(302, $response->getStatusCode(),
            'a Livewire request was redirected to the password screen, so the '
            .'component can never be reached and the form does nothing');
    }

    public function test_a_wrong_current_password_says_so(): void
    {
        $page = Volt::actingAs($this->user)->test('auth.change-password')
            ->set('current_password', 'notthepassword')
            ->set('password', 'chosenbythem99')
            ->set('password_confirmation', 'chosenbythem99')
            ->call('save');

        $page->assertHasErrors('current_password');
        $this->assertStringContainsString('not the password you were given', $page->html());

        // And nothing changed.
        $this->assertTrue(Hash::check('imprint123',
            DB::table('users')->where('user_id', $this->user->user_id)->value('password')));
    }

    public function test_the_new_password_cannot_be_the_one_they_were_given(): void
    {
        Volt::actingAs($this->user)->test('auth.change-password')
            ->set('current_password', 'imprint123')
            ->set('password', 'imprint123')
            ->set('password_confirmation', 'imprint123')
            ->call('save')
            ->assertHasErrors('password');

        $this->assertTrue((bool) DB::table('users')->where('user_id', $this->user->user_id)
            ->value('must_change_password'), 'they got past it without changing anything');
    }

    public function test_a_mismatched_confirmation_says_so(): void
    {
        Volt::actingAs($this->user)->test('auth.change-password')
            ->set('current_password', 'imprint123')
            ->set('password', 'chosenbythem99')
            ->set('password_confirmation', 'somethingelse99')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_a_short_password_says_so(): void
    {
        Volt::actingAs($this->user)->test('auth.change-password')
            ->set('current_password', 'imprint123')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_changing_it_clears_the_flag_and_lets_them_through(): void
    {
        Volt::actingAs($this->user)->test('auth.change-password')
            ->set('current_password', 'imprint123')
            ->set('password', 'chosenbythem99')
            ->set('password_confirmation', 'chosenbythem99')
            ->call('save')
            ->assertHasNoErrors();

        $row = DB::table('users')->where('user_id', $this->user->user_id)->first();

        $this->assertTrue(Hash::check('chosenbythem99', $row->password), 'the password did not change');
        $this->assertFalse((bool) $row->must_change_password, 'they would be asked again forever');
    }

    /** Somebody typing a handed-over password needs to see what they typed. */
    public function test_each_field_can_be_revealed(): void
    {
        $html = $this->actingAs($this->user)->get(route('password.change'))->assertOk()->getContent();

        // The buttons, not the CSS rules that style them.
        $this->assertSame(3, substr_count($html, 'class="pwc__eye"'),
            'every password field should have its own reveal button');
        $this->assertSame(3, substr_count($html, 'type="password"'),
            'the fields should start hidden');
    }
}
