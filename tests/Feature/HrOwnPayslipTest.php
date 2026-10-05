<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** HR staff are employees too: their own payslips open, from the HR sidebar. */
class HrOwnPayslipTest extends TestCase
{
    use DatabaseTransactions;

    public function test_an_hr_officer_opens_their_own_payslips(): void
    {
        $t = bin2hex(random_bytes(4));
        $officer = User::create(['full_name' => 'Officer '.$t, 'username' => 'of'.$t, 'email' => $t.'@example.test',
            'password' => 'Password123!', 'role' => 'hr']);
        DB::table('employees')->insert(['user_id' => $officer->user_id, 'job_title' => 'HR OFFICER',
            'hire_date' => '2025-01-01', 'salary' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($officer)->get(route('employee.payroll'))->assertOk();
        $this->actingAs($officer)->get(route('hr.home'))->assertOk()->assertSee('My payslips');
    }
}
