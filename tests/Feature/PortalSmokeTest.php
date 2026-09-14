<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Renders every screen in the three portals, for a guest and for a signed-in
 * user, so a broken Blade reference or a missing table fails loudly.
 */
class PortalSmokeTest extends TestCase
{
    public static function publicPages(): array
    {
        return [
            'landing'         => ['/'],
            'login'           => ['/login'],
            'admin login'     => ['/admin/login'],
            'employee login'  => ['/employee/login'],
            'applicant login' => ['/applicant/login'],
        ];
    }

    public static function hrPages(): array
    {
        return [
            'hr home'         => ['/admin/hr'],
            'hr applications' => ['/hr/applications'],
            'hr attendance'   => ['/hr/attendance'],
            'hr leave'        => ['/hr/leave'],
            'hr payroll'      => ['/hr/payroll'],
        ];
    }

    public static function employeePages(): array
    {
        return [
            'employee dashboard'  => ['/employee/dashboard'],
            'employee attendance' => ['/employee/attendance'],
            'employee payroll'    => ['/employee/payroll'],
            'employee leave'      => ['/employee/leave'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_render(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    #[DataProvider('hrPages')]
    public function test_hr_pages_redirect_guests(string $uri): void
    {
        $this->get($uri)->assertRedirect('/admin/login');
    }

    #[DataProvider('employeePages')]
    public function test_employee_pages_redirect_guests(string $uri): void
    {
        $this->get($uri)->assertRedirect('/employee/login');
    }

    public function test_applicant_page_redirects_guests(): void
    {
        $this->get('/applicant')->assertRedirect('/applicant/login');
    }

    #[DataProvider('hrPages')]
    public function test_hr_pages_render_for_hr_user(string $uri): void
    {
        $this->actingAs($this->userWithUsername('hr'))->get($uri)->assertOk();
    }

    #[DataProvider('employeePages')]
    public function test_employee_pages_render_for_employee(string $uri): void
    {
        $this->actingAs($this->userWithUsername('msantos'))->get($uri)->assertOk();
    }

    public function test_applicant_page_renders_for_signed_in_user(): void
    {
        $this->actingAs($this->userWithUsername('msantos'))->get('/applicant')->assertOk();
    }

    private function userWithUsername(string $username): User
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->markTestSkipped("Seeded user [{$username}] not found; run `php artisan db:seed`.");
        }

        return $user;
    }
}
