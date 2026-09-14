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

Volt::route('/login', 'auth.login')->name('login');
Volt::route('/admin/login', 'auth.adminlogin')->name('admin.login');
Volt::route('/employee/login', 'auth.employeelogin')->name('employee.login');
Volt::route('/applicant/login', 'auth.applicantlogin')->name('applicant.login');

/*
|--------------------------------------------------------------------------
| HR back office
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Volt::route('/admin/hr', 'hr.home')->name('hr.home');
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
