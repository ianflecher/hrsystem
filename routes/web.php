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

/*
 * Careers is the front door.
 *
 * Most people arriving at this address are looking for work, not for a payslip:
 * staff know where their own portal is and reach it from the header, while a
 * stranger has nowhere else to go. The portal chooser that used to sit here is
 * still at /portals for anyone who wants it.
 *
 * The page is deliberately public - browsing what is open should not require an
 * account, and one is only created at the point of applying.
 */
Volt::route('/', 'careers.index')->name('landing');
Volt::route('/careers', 'careers.index')->name('careers');

// Real pages rather than anchors on one endless scroll: each can be linked to
// on its own, the bar can show where you are, and a page stays a readable
// length.
Volt::route('/careers/who-we-are', 'careers.story')->name('careers.story');
Volt::route('/careers/who-we-hire', 'careers.who')->name('careers.who');
Volt::route('/careers/jobs', 'careers.jobs')->name('careers.jobs');
// Benefits are a section of the home page, not a page of their own - there is
// only one set of them and repeating it invites the two copies to disagree.
// The address stays alive and lands on that section rather than 404ing.
Route::redirect('/careers/benefits', '/#benefits')->name('careers.benefits');
Volt::route('/careers/our-people', 'careers.people')->name('careers.people');
Volt::route('/careers/into-imprint', 'careers.into-imprint')->name('careers.into');
// The page answered to /careers/inside until 2026-09-21. Anything already
// pointing there - a pasted link, a search result - still arrives.
Route::redirect('/careers/inside', '/careers/into-imprint');
Volt::route('/careers/front', 'careers.front')->name('careers.front');

Volt::route('/portals', 'landingpage')->name('portals');

// There is no customer portal in an HRIS - that page came across with the
// e-commerce code. The name stays defined because Laravel redirects guests to
// it, and it points at the chooser, which is the honest answer to "where do I
// sign in?" when the area is not known.
// Laravel sends anybody whose session has expired here, so it must land on the
// portal chooser and not on the careers page - a supervisor who was timed out
// mid-shift needs a way back in, not a job advert.
Route::redirect('/login', '/portals')->name('login');
Volt::route('/admin/login', 'auth.adminlogin')->name('admin.login');
Volt::route('/employee/login', 'auth.employeelogin')->name('employee.login');
Volt::route('/applicant/login', 'auth.applicantlogin')->name('applicant.login');

/*
|--------------------------------------------------------------------------
| HR back office
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/applications/{id}/resume', [\App\Http\Controllers\ApplicationFileController::class, 'resume'])->whereNumber('id')->name('applications.resume');
    Route::get('/applications/documents/{id}', [\App\Http\Controllers\ApplicationFileController::class, 'document'])->whereNumber('id')->name('applications.document');
    Route::get('/payslip/{id}', \App\Http\Controllers\PayslipController::class)->whereNumber('id')->name('payslip.show');
    Route::get('/hr/reports/download', \App\Http\Controllers\HrReportController::class)->name('people.reports.download');
    Route::post('/people/notices/{id}', [\App\Http\Controllers\PeopleController::class, 'notice'])->whereNumber('id')->name('people.notices.act');
    Route::get('/people/documents/{id}/download', [\App\Http\Controllers\PeopleController::class, 'download'])->whereNumber('id')->name('people.documents.download');
    foreach (['hr', 'employee'] as $portal) {
        Route::get('/'.$portal.'/people/{module}', [\App\Http\Controllers\PeopleController::class, 'index'])->name('people.'.$portal);
        Route::post('/'.$portal.'/people/{module}', [\App\Http\Controllers\PeopleController::class, 'store'])->name('people.'.$portal.'.store');
        Route::post('/'.$portal.'/people/{module}/{id}', [\App\Http\Controllers\PeopleController::class, 'action'])->whereNumber('id')->name('people.'.$portal.'.action');
    }
    // Reachable by any signed-in account, because anyone HR creates lands here
    // before they can reach their own portal.
    Volt::route('/password/change', 'auth.change-password')->name('password.change');

    // Every signed-in person, whichever portal they belong to.
    Volt::route('/account', 'account')->name('account.edit');

    // Volt screens carry no gate of their own - every controller below opens
    // with PeopleAccess::hr(), but these had only 'auth', so any signed-in
    // employee could read the roster, everyone's attendance and leave, and
    // every job application. The middleware goes on the group because Livewire
    // re-applies route middleware to the update requests these components make.
    Route::middleware(\App\Http\Middleware\EnsureHr::class)->group(function () {
        Volt::route('/admin/hr', 'hr.home')->name('hr.home');
        Volt::route('/hr/employees', 'hr.employees')->name('hr.employees');
        Volt::route('/hr/positions', 'hr.positions')->name('hr.positions');
        Volt::route('/hr/applications', 'hr.applications')->name('hr.applications');
        Volt::route('/hr/attendance', 'hr.attendance')->name('hr.attendance');
        Volt::route('/hr/leave', 'hr.leave')->name('hr.leave');
        Volt::route('/hr/payroll', 'hr.payroll')->name('hr.payroll');
    });
    Route::get('/hr/operations/payroll-control', [\App\Http\Controllers\HrOperationsController::class, 'payrollControl'])->name('hr.operations.payroll-control');
    Route::get('/hr/operations/payroll-approval', [\App\Http\Controllers\HrOperationsController::class, 'payrollApproval'])->name('hr.operations.payroll-approval');
    Route::post('/hr/operations/payroll/approve', [\App\Http\Controllers\HrOperationsController::class, 'approvePayroll'])->name('hr.operations.payroll.approve');
    Route::post('/hr/operations/payroll/paid', [\App\Http\Controllers\HrOperationsController::class, 'paidPayroll'])->name('hr.operations.payroll.paid');
    Route::get('/hr/operations/admin-center', [\App\Http\Controllers\HrOperationsController::class, 'adminCenter'])->name('hr.operations.admin-center');
    Route::get('/hr/operations/analytics', [\App\Http\Controllers\HrOperationsController::class, 'analytics'])->name('hr.operations.analytics');
    Route::get('/hr/operations/attendance-exceptions', [\App\Http\Controllers\HrOperationsController::class, 'attendanceExceptions'])->name('hr.operations.attendance-exceptions');
    Route::post('/hr/operations/admin-center/settings', [\App\Http\Controllers\HrOperationsController::class, 'adminSet'])->name('hr.operations.admin-center.settings');
    Route::post('/hr/operations/employee/{id}/lifecycle', [\App\Http\Controllers\HrOperationsController::class, 'lifecycleEvent'])->whereNumber('id')->name('hr.operations.employee.lifecycle');
    Route::post('/hr/operations/anomalies/{id}/resolve', [\App\Http\Controllers\HrOperationsController::class, 'resolveAnomaly'])->whereNumber('id')->name('hr.operations.anomaly.resolve');
    Route::get('/hr/operations/manager', [\App\Http\Controllers\HrOperationsController::class, 'manager'])->name('hr.operations.manager');
    Route::post('/hr/operations/exceptions/{id}/resolve', [\App\Http\Controllers\HrOperationsController::class, 'resolveException'])->whereNumber('id')->name('hr.operations.exception.resolve');
    Route::get('/hr/operations/employee/{id}', [\App\Http\Controllers\HrOperationsController::class, 'employee'])->whereNumber('id')->name('hr.operations.employee');
    Route::get('/hr/operations/inbox', [\App\Http\Controllers\HrOperationsController::class, 'inbox'])->name('hr.operations.inbox');
    Route::get('/hr/operations/approval-center', [\App\Http\Controllers\HrOperationsController::class, 'approvalCenter'])->name('hr.operations.approval-center');
    Route::post('/hr/operations/requests/{id}/decision', [\App\Http\Controllers\HrOperationsController::class, 'requestDecision'])->whereNumber('id')->name('hr.operations.request.decision');

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
    Route::get('/employee/self-service', [\App\Http\Controllers\EmployeeOperationsController::class, 'selfService'])->name('employee.operations.self-service');
    Route::post('/employee/self-service/request', [\App\Http\Controllers\EmployeeOperationsController::class, 'request'])->name('employee.operations.request');
    Route::post('/employee/self-service/attendance-correction', [\App\Http\Controllers\EmployeeOperationsController::class, 'attendanceCorrection'])->name('employee.operations.attendance-correction');
    Route::patch('/employee/self-service/notifications/{id}/read', [\App\Http\Controllers\EmployeeOperationsController::class, 'markRead'])->whereNumber('id')->name('employee.operations.notification.read');
});

/*
|--------------------------------------------------------------------------
| Applicant / careers portal
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Volt::route('/applicant', 'applicant.index')->name('applicant.index');

    // The company profile an applicant sees once they are in: what the place
    // is, rather than only what their application status is.
    Volt::route('/applicant/inside', 'applicant.inside')->name('applicant.inside');
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
