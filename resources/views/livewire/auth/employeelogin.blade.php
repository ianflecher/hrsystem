<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

new #[Layout('components.layouts.landing')] class extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function login()
{
    $this->validate();

    // Clean inputs
    $usernameInput = trim($this->username);
    $password = $this->password;
    
    // Debug: Let's see what we're looking for
    \Log::info('Login attempt', [
        'input' => $this->username,
        'trimmed' => $usernameInput,
        'is_email' => filter_var($usernameInput, FILTER_VALIDATE_EMAIL)
    ]);

    // Find user by checking both email and username
    $user = DB::table('users')
        ->where(function($query) use ($usernameInput) {
            // Try email first
            $query->where('email', $usernameInput);
            
            // Also try username (in case user entered username instead of email)
            $query->orWhere('username', $usernameInput);
        })
        ->first(); // Remove role filter temporarily for debugging

    if (!$user) {
        \Log::warning('User not found', ['username' => $usernameInput]);
        throw ValidationException::withMessages([
            'username' => __('No account found with these credentials.'),
        ]);
    }

    \Log::info('User found', [
        'user_id' => $user->user_id,
        'email' => $user->email,
        'username' => $user->username,
        'role' => $user->role
    ]);

    // Check if user has employee or manager role
    if (!in_array($user->role, ['employee', 'manager'])) {
        \Log::warning('User role not allowed', ['role' => $user->role]);
        throw ValidationException::withMessages([
            'username' => __('Access denied. Employee or Manager credentials required.'),
        ]);
    }

    // Check if they have an active employee record
    $employee = DB::table('employees')
        ->where('user_id', $user->user_id)
        ->where('status', 'active')
        ->first();

    if (!$employee) {
        \Log::warning('No active employee record', ['user_id' => $user->user_id]);
        throw ValidationException::withMessages([
            'username' => __('Employee/Manager account is not active. Please contact HR.'),
        ]);
    }

    // For authentication, we MUST use the email field
    // Laravel's Auth::attempt() looks for 'email' by default
    $authCredentials = [
        'email' => $user->email, // Use the exact email from database
        'password' => $password
    ];

    \Log::info('Attempting authentication', ['email' => $user->email]);

    if (!Auth::attempt($authCredentials, $this->remember)) {
        \Log::warning('Authentication failed', ['email' => $user->email]);
        throw ValidationException::withMessages([
            'username' => __('Invalid password.'),
        ]);
    }

    \Log::info('Login successful', ['user_id' => $user->user_id]);
    
    session()->regenerate();
    
    return redirect()->route('employee.dashboard');
}
}
?>
<div class="auth-split" x-data="{ showPassword: false }" x-init="$refs.username.focus()">
    <style>
        /* Two columns rather than a form floating on the photograph: inputs
           need a dependable surface behind them, and a scrim strong enough to
           make a form legible would have buried the picture anyway. */
        .auth-split {
            min-height: calc(100vh - 64px);
            display: grid;
            grid-template-columns: 1fr;
        }

        .auth-split__form {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            background: #fff;
        }

        .auth-split__inner { width: 100%; max-width: 26rem; }

        .auth-split__aside {
            position: relative;
            display: none;
            background-image: url("{{ asset('hero-staff.jpg') }}");
            background-size: cover;
            background-position: center right;
        }

        /* The photograph is lit dark on its left edge, so the caption sits
           there and the scrim only has to deepen what is already dark. */
        .auth-split__aside::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(80deg,
                rgba(12, 22, 38, .90) 0%,
                rgba(12, 22, 38, .55) 45%,
                rgba(12, 22, 38, .20) 100%);
        }

        .auth-split__caption {
            position: absolute;
            inset: auto 0 0 0;
            z-index: 1;
            padding: 44px 44px 48px;
            color: #fff;
        }

        .auth-split__caption h2 {
            margin: 0 0 8px;
            font-family: var(--font-head, "Space Grotesk", system-ui, sans-serif);
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .auth-split__caption p {
            margin: 0;
            max-width: 26rem;
            color: #A9B4C6;
            font-size: .9375rem;
            line-height: 1.6;
        }

        @media (min-width: 1024px) {
            .auth-split { grid-template-columns: 1fr 1.05fr; align-items: start; }

            /* The form column is taller than the viewport, and a grid stretches
               both columns to match it - which pushed the photograph's caption
               below the fold. Pinning the picture keeps it, and the caption, in
               view while the form scrolls beside it. */
            .auth-split__aside {
                display: block;
                position: sticky;
                top: 64px;
                height: calc(100vh - 64px);
            }
        }
    </style>

    <div class="auth-split__form">
        <div class="auth-split__inner">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="mx-auto h-20 w-20 bg-red-500 rounded-full flex items-center justify-center mb-4 overflow-hidden border-2 border-gray-200 shadow-sm">
                <i class="fas fa-users text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Staff Portal</h1>
            <p class="text-gray-600">Employee & Manager Access</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 hover:shadow-sm transition-all duration-300">
            <div class="p-8">
                <form wire:submit.prevent="login" class="space-y-6">
                    @csrf
                    
                    <!-- Username/Email -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Staff ID / Email
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model="username" 
                                id="username"
                                x-ref="username"
                                required
                                autofocus
                                placeholder="staff@imprintcustoms.ph"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-id-badge"></i>
                            </div>
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                            <a href="#" class="text-sm text-red-600 hover:text-red-700 font-medium">
                                Forgot password?
                            </a>
                        </div>
                        <div class="relative">
                            <input 
                                :type="showPassword ? 'text' : 'password'"
                                wire:model="password" 
                                id="password"
                                required
                                placeholder="••••••••"
                                class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-lock"></i>
                            </div>
                            <button 
                                type="button" 
                                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600" 
                                @click="showPassword = !showPassword"
                            >
                                <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            class="w-full bg-red-600 text-white py-3 px-4 rounded-lg hover:from-red-700 hover:to-indigo-700 transition font-medium flex items-center justify-center gap-2 shadow-sm hover:shadow-sm transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <span wire:loading.remove wire:target="login">
                                <i class="fas fa-sign-in-alt"></i>
                                Sign In
                            </span>
                            <span wire:loading wire:target="login">
                                <i class="fas fa-spinner fa-spin"></i>
                                Verifying...
                            </span>
                        </button>
                    </div>
                </form>

                {{-- What this portal actually does.

                     It used to advertise two roles with three features each:
                     Employee got Attendance, Tasks and Orders; Manager got
                     Reports, Approvals and Team. Six claims, one of them true.
                     Tasks and Orders belong to the projects and ERP modules,
                     which are not part of the HRIS, and there is no manager
                     view at all - both roles land on the same dashboard. --}}
                <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">
                        In this portal
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-start gap-2 text-sm text-gray-700">
                            <i class="fas fa-clock text-red-600 mt-0.5 w-4 text-center"></i>
                            <span>Clock in and out, and review your attendance</span>
                        </li>
                        <li class="flex items-start gap-2 text-sm text-gray-700">
                            <i class="fas fa-money-bill-wave text-red-600 mt-0.5 w-4 text-center"></i>
                            <span>View your payslips</span>
                        </li>
                        <li class="flex items-start gap-2 text-sm text-gray-700">
                            <i class="fas fa-umbrella-beach text-red-600 mt-0.5 w-4 text-center"></i>
                            <span>File leave requests and track their status</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
                <div class="flex items-center justify-center">
                    <i class="fas fa-building text-red-600 mr-2"></i>
                    <p class="text-xs text-center text-red-800 font-medium">
                        Imprint Customs Staff Portal
                    </p>
                </div>
            </div>
        </div>

        <!-- Support Information -->
        <div class="mt-8 text-center">
            <div class="inline-flex items-center gap-2 text-sm text-gray-500">
                <i class="fas fa-headset"></i>
                <span>Support: <span class="font-medium">hr@imprintcustoms.ph</span> | IT: <span class="font-medium">it@imprintcustoms.ph</span></span>
            </div>
        </div>
        </div>
    </div>

    <div class="auth-split__aside">
        <div class="auth-split__caption">
            <h2>Your workday, in one place</h2>
            <p>
                Clock in, check your payslips and file leave without chasing
                anyone for a form.
            </p>
        </div>
    </div>
</div>