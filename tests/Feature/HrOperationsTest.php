<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The HR operations routes exist and are named.
 *
 * Deliberately no RefreshDatabase. It used to be on this class and it was
 * emptying the company's development database on every run: the suite points
 * at tgif_hris (the HR screens use MySQL-only SQL, so there is no separate
 * test database), and RefreshDatabase migrates that database from scratch.
 * The seeded hr and admin accounts went with it, which is why twenty-five
 * tests in the other suites failed and twenty-three skipped - they look for
 * an hr user that had just been deleted out from under them.
 *
 * These assertions touch no tables at all, so nothing here needs a database.
 */
class HrOperationsTest extends TestCase
{
    public function test_hr_operations_routes_are_registered(): void
    {
        $this->assertNotNull(route('hr.operations.payroll-control'));
        $this->assertNotNull(route('hr.operations.inbox'));
    }
}
