<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Appended to the whole web group rather than one route group, so no
        // corner of the app is a way round it. It does nothing unless
        // must_change_password is set, and that defaults to false.
        $middleware->web(append: [
            \App\Http\Middleware\MustChangePassword::class,
        ]);

        // Each portal has its own sign-in screen, so send guests to the one
        // that matches the area they tried to reach.
        $middleware->redirectGuestsTo(function (Request $request) {
            return match (true) {
                $request->is('employee/*'), $request->is('employee')   => route('employee.login'),
                $request->is('applicant/*'), $request->is('applicant') => route('applicant.login'),
                $request->is('admin/*'), $request->is('admin'),
                $request->is('hr/*')                                   => route('admin.login'),
                default                                                => route('login'),
            };
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
