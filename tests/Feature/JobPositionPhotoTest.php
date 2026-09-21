<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A photograph on an opening, and the department it belongs to.
 *
 * The openings list was the one part of the careers site that was a wall of
 * text. And departments had no create screen anywhere in the back office - the
 * only insert was a side effect of hiring an applicant - so every department
 * dropdown was empty and every employee read "No Department".
 */
class JobPositionPhotoTest extends TestCase
{
    private array $temporaryPositionIds = [];
    private array $temporaryDepartmentIds = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPositionIds as $id) {
            DB::table('job_positions')->where('position_id', $id)->delete();
        }

        foreach ($this->temporaryDepartmentIds as $id) {
            DB::table('departments')->where('department_id', $id)->delete();
        }

        parent::tearDown();
    }

    public function test_an_opening_can_be_posted_with_a_photograph(): void
    {
        Storage::fake('public');

        Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->set('title', 'Photo Test Role')
            ->set('employment_type', 'full_time')
            ->set('image', UploadedFile::fake()->image('floor.jpg', 1200, 800))
            ->call('save')
            ->assertHasNoErrors();

        $position = DB::table('job_positions')->where('title', 'Photo Test Role')->first();
        $this->temporaryPositionIds[] = $position->position_id;

        $this->assertNotNull($position->image_path, 'the opening saved without its photograph');
        Storage::disk('public')->assertExists($position->image_path);
    }

    public function test_a_photograph_can_be_taken_off_again(): void
    {
        Storage::fake('public');

        $component = Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->set('title', 'Photo Removal Role')
            ->set('employment_type', 'full_time')
            ->set('image', UploadedFile::fake()->image('floor.jpg', 1200, 800))
            ->call('save');

        $position = DB::table('job_positions')->where('title', 'Photo Removal Role')->first();
        $this->temporaryPositionIds[] = $position->position_id;
        $path = $position->image_path;

        $component->call('edit', $position->position_id)->call('removeImage');

        $this->assertNull(
            DB::table('job_positions')->where('position_id', $position->position_id)->value('image_path'),
            'the opening still points at a photograph that was removed'
        );
        Storage::disk('public')->assertMissing($path);
    }

    public function test_something_that_is_not_an_image_is_refused(): void
    {
        Storage::fake('public');

        Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->set('title', 'Bad Upload Role')
            ->set('employment_type', 'full_time')
            ->set('image', UploadedFile::fake()->create('payroll.pdf', 200, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('image');

        $this->assertDatabaseMissing('job_positions', ['title' => 'Bad Upload Role']);
    }

    public function test_the_careers_page_shows_the_photograph(): void
    {
        $id = DB::table('job_positions')->insertGetId([
            'title' => 'Careers Photo Role', 'employment_type' => 'full_time',
            'image_path' => 'job-photos/example.jpg', 'is_open' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->temporaryPositionIds[] = $id;

        $this->get('/careers/jobs')
            ->assertOk()
            ->assertSee('job-photos/example.jpg', false);
    }

    public function test_a_department_can_be_added(): void
    {
        Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->set('newDepartment', 'Test Department')
            ->call('addDepartment')
            ->assertHasNoErrors();

        $id = DB::table('departments')->where('department_name', 'Test Department')->value('department_id');
        $this->assertNotNull($id, 'the department was not created');
        $this->temporaryDepartmentIds[] = $id;
    }

    public function test_two_departments_cannot_share_a_name(): void
    {
        $id = DB::table('departments')->insertGetId([
            'department_name' => 'Duplicate Department', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->temporaryDepartmentIds[] = $id;

        Volt::actingAs($this->hr())
            ->test('hr.positions')
            ->set('newDepartment', 'Duplicate Department')
            ->call('addDepartment')
            ->assertHasErrors('newDepartment');
    }

    /**
     * Deleting it anyway would quietly empty the department on everything that
     * pointed at it, with nothing on the screen to say it had happened.
     */
    public function test_a_department_still_in_use_is_not_removed(): void
    {
        $departmentId = DB::table('departments')->insertGetId([
            'department_name' => 'In Use Department', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->temporaryDepartmentIds[] = $departmentId;

        $positionId = DB::table('job_positions')->insertGetId([
            'title' => 'Role In That Department', 'employment_type' => 'full_time',
            'department_id' => $departmentId, 'is_open' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->temporaryPositionIds[] = $positionId;

        Volt::actingAs($this->hr())->test('hr.positions')->call('deleteDepartment', $departmentId);

        $this->assertDatabaseHas('departments', ['department_id' => $departmentId]);
    }

    private function hr(): User
    {
        return User::where('username', 'hr')->firstOrFail();
    }
}
