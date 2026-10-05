<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LogoutAdmin
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke()
    {
        // An HR officer signed in through the employee portal, so goes back there.
        $officer = Auth::user()?->role === 'hr';
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        return redirect()->route($officer ? 'employee.login' : 'admin.login');
    }
}