<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account whose password somebody else chose cannot go anywhere until it is
 * changed.
 *
 * HR creates staff accounts and hands over a generated first password, so for
 * a short window a second person knows it. This keeps that window as short as
 * the holder's first sign-in.
 *
 * It does nothing at all unless must_change_password is set, and that defaults
 * to false - every account that already existed is unaffected.
 */
class MustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        // The change-password screen itself, and the way out, stay reachable -
        // otherwise the redirect would loop.
        //
        // Livewire's own endpoint has to be let through as well, and it is
        // recognised by the header it sends rather than by its path. This used
        // to test for 'livewire/*', which is not where Livewire serves from:
        // the path carries a generated suffix, /livewire-773c66fa/update here,
        // and the route is named default-livewire.update. So every request the
        // change-password screen made to its own component was redirected back
        // to the change-password page, and Livewire got a page of HTML where
        // it expected JSON. It gave up and reloaded, which is exactly what
        // somebody sees: the form empties itself and says nothing, whatever
        // they type. The screen could not work at all.
        if ($request->routeIs('password.change')
            || $request->routeIs('*.logout')
            || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
