<?php
namespace Tests\Feature;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LoginErrorTest extends TestCase
{
    public function test_a_failed_login_is_visibly_reported(): void
    {
        $c = Volt::test('auth.applicantlogin')
            ->set('email', 'nobody@example.test')
            ->set('password', 'definitely-wrong')
            ->call('login');

        $html = $c->html();
        fwrite(STDERR, "\n  alert banner shown  : ".(str_contains($html, 'form-alert') ? 'yes' : 'NO'));
        fwrite(STDERR, "\n  message present     : ".(str_contains($html, 'credentials are incorrect') ? 'yes' : 'NO'));
        fwrite(STDERR, "\n  fields marked wrong : ".substr_count($html, 'is-wrong'));
        fwrite(STDERR, "\n\n");

        $c->assertSee('credentials are incorrect');
        $this->assertStringContainsString('role="alert"', $html, 'no visible alert on a failed login');
        $this->assertStringContainsString('is-wrong', $html, 'the fields are not marked');
    }

    public function test_a_failed_registration_is_reported_the_same_way(): void
    {
        // A duplicate email is the one an applicant actually hits.
        $existing = \App\Models\User::first();

        $c = Volt::test('auth.applicantlogin')
            ->set('showLogin', false)
            ->set('full_name', 'Someone Else')
            ->set('email', $existing->email)
            ->set('position', 'Crew Member')
            ->set('password', 'Password!2345')
            ->set('password_confirmation', 'Password!2345')
            ->call('register');

        $c->assertHasErrors('email');
        $this->assertStringContainsString('role="alert"', $c->html(), 'no visible alert on a failed registration');
    }

    public function test_a_clean_form_shows_no_alert(): void
    {
        $html = Volt::test('auth.applicantlogin')->html();
        // "form-alert" also names a CSS rule in the inline stylesheet, so the
        // markup is what is checked, not the class name.
        $this->assertStringNotContainsString('role="alert"', $html, 'alert shown before anything went wrong');
    }
}
