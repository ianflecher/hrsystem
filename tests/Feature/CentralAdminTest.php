<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CentralAdminTest extends TestCase
{
    /** @var array<int, int> */
    private array $temporaryUserIds = [];

    protected function tearDown(): void
    {
        if ($this->temporaryUserIds !== []) {
            DB::table('users')->whereIn('user_id', $this->temporaryUserIds)->delete();
        }

        parent::tearDown();
    }

    public function test_admin_can_open_the_control_center_and_see_all_systems(): void
    {
        Http::fake(fn () => Http::response([], 200));
        $admin = $this->temporaryUser('admin');

        $this->actingAs($admin)
            ->get(route('admin.control-center'))
            ->assertOk()
            ->assertSee('Admin Control Center')
            ->assertSee('HRIS')
            ->assertSee('Imprint Production')
            ->assertSee('Imprint Marketing');

        Http::assertSentCount(3);
    }

    public function test_non_admin_cannot_open_the_control_center(): void
    {
        $hr = $this->temporaryUser('hr');

        $this->actingAs($hr)
            ->get(route('admin.control-center'))
            ->assertForbidden();
    }

    private function temporaryUser(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'username' => 'central-admin-test-'.random_int(100000, 999999),
        ]);
        $this->temporaryUserIds[] = (int) $user->user_id;

        return $user;
    }
}
