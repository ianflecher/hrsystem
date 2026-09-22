<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Departments are managed beside the people in them, on the employees screen,
 * not on the applications screen where they used to live.
 *
 * The version this replaced had a trash icon whose handler called confirm() -
 * a JavaScript function, from PHP - so it fatally errored instead of deleting.
 * Had it worked it would have been worse: it set department_id to null for
 * everybody in the department first, so one click could quietly unassign
 * thirty-one people with nothing on screen saying so.
 */
class DepartmentManagementTest extends TestCase
{
    private array $departments = [];
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }

        DB::table('departments')->whereIn('department_id', $this->departments)->delete();

        parent::tearDown();
    }

    private function hr(): User
    {
        return User::where('username', 'hr')->first();
    }

    private function department(string $name): int
    {
        $id = DB::table('departments')->insertGetId([
            'department_name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->departments[] = $id;

        return $id;
    }

    private function employeeIn(int $departmentId): int
    {
        $n = random_int(100000, 999999);
        $u = User::create([
            'full_name' => 'Department Member', 'username' => "dm{$n}",
            'email' => "dm{$n}@example.test", 'password' => 'x', 'role' => 'employee',
        ]);
        $this->made[] = $u->user_id;

        return DB::table('employees')->insertGetId([
            'user_id' => $u->user_id, 'job_title' => 'Operator', 'hire_date' => '2026-01-01',
            'department_id' => $departmentId, 'salary' => 15000, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_departments_are_managed_from_the_employees_screen(): void
    {
        $html = Volt::actingAs($this->hr())->test('hr.employees')->call('toggleDepartments')->html();

        $this->assertStringContainsString('Departments', $html);
        $this->assertStringContainsString('New department name', $html);
    }

    /** And no longer from the applications screen. */
    public function test_the_applications_screen_no_longer_manages_them(): void
    {
        $html = Volt::actingAs($this->hr())->test('hr.applications')->html();

        $this->assertStringNotContainsString('Manage company departments', $html,
            'department management is still on the applications screen');
    }

    public function test_a_department_can_be_added(): void
    {
        $name = 'Test Dept '.random_int(100000, 999999);

        Volt::actingAs($this->hr())->test('hr.employees')
            ->set('newDepartment', $name)
            ->call('addDepartment')
            ->assertHasNoErrors();

        $id = DB::table('departments')->where('department_name', $name)->value('department_id');
        $this->assertNotNull($id, 'the department was not created');
        $this->departments[] = $id;
    }

    public function test_two_departments_cannot_share_a_name(): void
    {
        $name = 'Twice '.random_int(100000, 999999);
        $this->department($name);

        Volt::actingAs($this->hr())->test('hr.employees')
            ->set('newDepartment', $name)
            ->call('addDepartment')
            ->assertHasErrors('newDepartment');

        $this->assertSame(1, DB::table('departments')->where('department_name', $name)->count());
    }

    public function test_an_empty_department_can_be_deleted(): void
    {
        $id = $this->department('Empty '.random_int(100000, 999999));

        Volt::actingAs($this->hr())->test('hr.employees')->call('deleteDepartment', $id);

        $this->assertNull(DB::table('departments')->where('department_id', $id)->first());
    }

    /**
     * The one that matters. Deleting a department with people in it used to
     * unassign them all as a side effect.
     */
    public function test_a_department_with_people_in_it_is_not_deleted(): void
    {
        $id = $this->department('Occupied '.random_int(100000, 999999));
        $employeeId = $this->employeeIn($id);

        Volt::actingAs($this->hr())->test('hr.employees')->call('deleteDepartment', $id);

        $this->assertNotNull(DB::table('departments')->where('department_id', $id)->first(),
            'a department with people in it was deleted');

        // And nobody was quietly unassigned on the way.
        $this->assertEquals($id,
            DB::table('employees')->where('employee_id', $employeeId)->value('department_id'),
            'the employee was unassigned from their department');
    }

    public function test_a_department_can_be_renamed_without_touching_its_people(): void
    {
        $id = $this->department('Before '.random_int(100000, 999999));
        $employeeId = $this->employeeIn($id);
        $after = 'After '.random_int(100000, 999999);

        Volt::actingAs($this->hr())->test('hr.employees')
            ->call('startRename', $id, 'Before')
            ->set('renameDepartmentTo', $after)
            ->call('saveRename')
            ->assertHasNoErrors();

        $this->assertSame($after, DB::table('departments')->where('department_id', $id)->value('department_name'));
        $this->assertEquals($id, DB::table('employees')->where('employee_id', $employeeId)->value('department_id'));
    }

    /** The headcount beside each name is what tells HR whether it is safe. */
    public function test_the_panel_counts_the_people_in_each_department(): void
    {
        $id = $this->department('Counted '.random_int(100000, 999999));
        $this->employeeIn($id);
        $this->employeeIn($id);

        $roll = Volt::actingAs($this->hr())->test('hr.employees')->instance()->departmentRoll();
        $row = $roll->firstWhere('department_id', $id);

        $this->assertNotNull($row);
        $this->assertEquals(2, $row->headcount);
    }

    public function test_only_hr_can_change_departments(): void
    {
        $n = random_int(100000, 999999);
        $u = User::create([
            'full_name' => 'Ordinary Staff', 'username' => "os{$n}",
            'email' => "os{$n}@example.test", 'password' => 'x', 'role' => 'employee',
        ]);
        $this->made[] = $u->user_id;

        $id = $this->department('Guarded '.random_int(100000, 999999));

        Volt::actingAs($u)->test('hr.employees')->call('deleteDepartment', $id)->assertForbidden();

        $this->assertNotNull(DB::table('departments')->where('department_id', $id)->first());
    }
}
