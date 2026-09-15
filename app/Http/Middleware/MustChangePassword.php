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
        if ($request->routeIs('password.change') || $request->routeIs('*.logout') || $request->is('livewire/*')) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
