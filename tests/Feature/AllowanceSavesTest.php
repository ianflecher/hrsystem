<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AllowanceSavesTest extends TestCase
{
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $id) {
            DB::table('employees')->where('user_id', $id)->delete();
            DB::table('users')->where('user_id', $id)->delete();
        }
        parent::tearDown();
    }

    /**
     * An offer carries the two figures apart, and accepting it hires them on
     * exactly those. Nobody is hired without accepting.
     */
    public function test_an_accepted_offer_records_basic_and_allowance_separately(): void
    {
        $hr = User::where('username','hr')->first();
        $n = random_int(100000,999999);
        $u = User::create(['full_name'=>'Split Pay','username'=>"sp{$n}",
            'email'=>"sp{$n}@example.test",'password'=>'x','role'=>'employee']);
        $this->made[] = $u->user_id;

        $appId = DB::table('job_applications')->insertGetId([
            'user_id'=>$u->user_id,'position_applied'=>'Operator','years_experience'=>'',
            'status'=>'shortlisted','application_date'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);

        Volt::actingAs($hr)->test('hr.applications')
            ->call('openHireModal', $appId)
            ->set('hireSalary', '18000')
            ->set('hireAllowance', '2000')
            ->set('hireResponsibilities', 'Run the press and check every batch before it leaves.')
            ->set('hireStartsOn', now()->addWeek()->toDateString())
            ->call('confirmHire')
            ->assertHasNoErrors();

        // The offer carries them apart...
        $offer = DB::table('job_offers')->where('application_id', $appId)->first();
        $this->assertEquals(18000, $offer->basic_salary);
        $this->assertEquals(2000, $offer->allowance);

        // ...and only accepting it makes an employee.
        $this->assertNull(DB::table('employees')->where('user_id', $u->user_id)->first(),
            'an offer on its own hired them');

        Volt::actingAs($u)->test('applicant.index')->call('acceptOffer')->assertHasNoErrors();

        $e = DB::table('employees')->where('user_id',$u->user_id)->first();
        fwrite(STDERR, sprintf("\n  accepted on basic %s + allowance %s = %s total", $e->salary, $e->allowance, $e->salary + $e->allowance));

        $this->assertEquals(18000, $e->salary, 'the allowance was folded into basic');
        $this->assertEquals(2000, $e->allowance);

        DB::table('job_offers')->where('application_id',$appId)->delete();
        DB::table('job_applications')->where('application_id',$appId)->delete();
        fwrite(STDERR, "\n\n");
    }
}
