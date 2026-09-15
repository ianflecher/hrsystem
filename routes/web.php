<?php

use App\Livewire\Actions\LogoutAdmin;
use App\Livewire\Actions\LogoutApplicant;
use App\Livewire\Actions\LogoutEmployee;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Volt::route('/', 'landingpage')->name('landing');

// There is no customer portal in an HRIS - that page came across with the
// e-commerce code. The name stays defined because Laravel redirects guests to
// it, and it points at the chooser, which is the honest answer to "where do I
// sign in?" when the area is not known.
Route::redirect('/login', '/')->name('login');
Volt::route('/admin/login', 'auth.adminlogin')->name('admin.login');
Volt::route('/employee/login', 'auth.employeelogin')->name('employee.login');
Volt::route('/applicant/login', 'auth.applicantlogin')->name('applicant.login');

/*
|--------------------------------------------------------------------------
| HR back office
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Reachable by any signed-in account, because anyone HR creates lands here
    // before they can reach their own portal.
    Volt::route('/password/change', 'auth.change-password')->name('password.change');

    // Every signed-in person, whichever portal they belong to.
    Volt::route('/account', 'account')->name('account.edit');

    Volt::route('/admin/hr', 'hr.home')->name('hr.home');
    Volt::route('/hr/employees', 'hr.employees')->name('hr.employees');
    Volt::route('/hr/positions', 'hr.positions')->name('hr.positions');
    Volt::route('/hr/applications', 'hr.applications')->name('hr.applications');
    Volt::route('/hr/attendance', 'hr.attendance')->name('hr.attendance');
    Volt::route('/hr/leave', 'hr.leave')->name('hr.leave');
    Volt::route('/hr/payroll', 'hr.payroll')->name('hr.payroll');

    // The generic app chrome links "Dashboard" here.
    Route::redirect('/admin', '/admin/hr')->name('admin.dashboard');
});

/*
|--------------------------------------------------------------------------
| Employee self-service
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Volt::route('/employee/dashboard', 'employee.index')->name('employee.dashboard');
    Volt::route('/employee/attendance', 'employee.attendance')->name('employee.attendance');
    Volt::route('/employee/payroll', 'employee.payroll')->name('employee.payroll');
    Volt::route('/employee/leave', 'employee.leave')->name('employee.leave');
});

/*
|--------------------------------------------------------------------------
| Applicant / careers portal
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Volt::route('/applicant', 'applicant.index')->name('applicant.index');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/admin/logout', LogoutAdmin::class)->name('admin.logout');
Route::post('/employee/logout', LogoutEmployee::class)->name('employee.logout');
Route::post('/applicant/logout', LogoutApplicant::class)->name('applicant.logout');

Route::fallback(fn () => redirect()->route('landing'));
