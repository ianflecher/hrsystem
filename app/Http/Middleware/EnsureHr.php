<?php

namespace App\Http\Middleware;

use App\Support\PeopleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The back office is for HR and admin.
 *
 * Every controller under /hr opens with PeopleAccess::hr(), but the Volt
 * screens - the employee list, positions, applications, attendance and leave,
 * and the HR dashboard itself - had only 'auth' on them. Any signed-in
 * account, including an ordinary employee, could open all six and read the
 * roster, everyone's attendance and leave, and every job application.
 *
 * This sits on the route group instead of in each mount(), because Livewire
 * re-applies a route's middleware to the update requests a component makes
 * afterwards. A guard in mount() would only cover the first page load.
 */
class EnsureHr
{
    public function handle(Request $request, Closure $next): Response
    {
        PeopleAccess::hr();

        return $next($request);
    }
}
