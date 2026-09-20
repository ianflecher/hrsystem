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
    /** @var array<int, int> */
    private array $temporaryUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryUserIds as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        parent::tearDown();
    }

    public static function publicPages(): array
    {
        return [
            // "/" is the careers page, and /careers the same page by its own
            // name. Both are listed because a directory left in public/ with a
            // route's name is served by the web server instead of Laravel, and
            // that turned /careers into a 404 once already.
            'landing'            => ['/'],
            'careers'            => ['/careers'],
            'careers who we are' => ['/careers/who-we-are'],
            'careers who we hire'=> ['/careers/who-we-hire'],
            'careers jobs'       => ['/careers/jobs'],
            'careers our people' => ['/careers/our-people'],
            'careers inside'     => ['/careers/inside'],
            'careers front'      => ['/careers/front'],
            'portal chooser'     => ['/portals'],
            'admin login'        => ['/admin/login'],
            'employee login'     => ['/employee/login'],
            'applicant login'    => ['/applicant/login'],
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

    public function test_the_old_customer_sign_in_points_at_the_chooser(): void
    {
        // /login served a customer portal inherited from the e-commerce code.
        // The name stays defined - Laravel redirects guests to it - but each
        // portal has its own sign-in now, so it hands over to the chooser.
        //
        // The chooser moved to /portals when the careers page took over "/",
        // and this has to follow it: somebody whose session expired needs a way
        // back in, which a careers page is not.
        $this->get('/login')->assertRedirect('/portals');
    }

    public function test_the_old_benefits_address_lands_on_the_home_page(): void
    {
        // Benefits live in a section of the home page. Anybody holding the old
        // link is sent to that section rather than to a 404.
        $this->get('/careers/benefits')->assertRedirect('/#benefits');
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
        $this->actingAs($this->temporaryEmployee())->get($uri)->assertOk();
    }

    public function test_applicant_page_renders_for_signed_in_user(): void
    {
        $this->actingAs($this->temporaryEmployee())->get('/applicant')->assertOk();
    }

    private function userWithUsername(string $username): User
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->markTestSkipped("Seeded user [{$username}] not found; run `php artisan db:seed`.");
        }

        return $user;
    }

    /**
     * The employee screens need somebody with an employee record behind them.
     * The suite makes its own and removes it again rather than depending on
     * sample staff in the database: invented people are indistinguishable from
     * real ones once seeded, and they show up in headcounts.
     */
    private function temporaryEmployee(): User
    {
        $n = random_int(100000, 999999);

        $user = User::create([
            'full_name' => 'Smoke Test Employee',
            'username'  => "smoketest{$n}",
            'email'     => "smoketest{$n}@example.test",
            'password'  => 'Password!2345',
            'role'      => 'employee',
        ]);

        DB::table('employees')->insert([
            'user_id'    => $user->user_id,
            'job_title'  => 'Smoke Test',
            'hire_date'  => now()->toDateString(),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->temporaryUserIds[] = $user->user_id;

        return $user;
    }
}
