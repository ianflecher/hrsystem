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


    // The staff portal is for the people it serves: employees and the two
    // tiers above them. Admin and HR sign in through the back office.
    if (!in_array($user->role, ['employee', 'supervisor', 'leader'])) {
        \Log::warning('User role not allowed', ['role' => $user->role]);
        throw ValidationException::withMessages([
            'username' => __('Access denied. This sign-in is for staff accounts.'),
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
            'username' => __('This staff account is not active. Please contact HR.'),
        ]);
    }

    // For authentication, we MUST use the email field
    // Laravel's Auth::attempt() looks for 'email' by default
    $authCredentials = [
        'email' => $user->email, // Use the exact email from database
        'password' => $password
    ];


    if (!Auth::attempt($authCredentials, $this->remember)) {
        \Log::warning('Authentication failed', ['email' => $user->email]);
        throw ValidationException::withMessages([
            'username' => __('Invalid password.'),
        ]);
    }

    
    session()->regenerate();
    
    return redirect()->route('employee.dashboard');
}
}
?>

<div class="staff-hero" x-data="{ showPassword: false }" x-init="$refs.username.focus()">
    <style>
        /* The same treatment as the landing page: one photograph edge to edge,
           a navy scrim weighted towards the type, and the content on glass.
           A white card here would have been a hole punched through the frame. */
        .staff-hero {
            position: relative;
            isolation: isolate;
            min-height: calc(100vh - 64px);
            display: flex;
            align-items: center;
            padding: 48px 0;
            background: #0C1626;
            overflow: hidden;
        }

        .staff-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: url("{{ asset('hero-staff.jpg') }}");
            background-size: cover;
            background-position: center right;
        }

        .staff-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(100deg,
                rgba(12, 22, 38, .97) 0%,
                rgba(12, 22, 38, .94) 34%,
                rgba(12, 22, 38, .74) 56%,
                rgba(12, 22, 38, .42) 100%);
        }

        @media (max-width: 900px) {
            /* Narrow screens put the type over the middle of the frame, where a
               left-weighted scrim leaves it unreadable. */
            .staff-hero::after {
                background: linear-gradient(180deg,
                    rgba(12, 22, 38, .95) 0%,
                    rgba(12, 22, 38, .90) 100%);
            }
        }

        .staff-hero__inner {
            width: 100%;
            max-width: 72rem;
            margin: 0 auto;
            padding: 0 32px;
        }

        .staff-hero__copy { max-width: 30rem; margin-bottom: 26px; }

        .staff-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .3125rem .75rem;
            border-radius: 999px;
            background: rgba(227, 27, 35, .16);
            border: 1px solid rgba(227, 27, 35, .38);
            color: #FCA5A9;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .staff-hero__title {
            margin: 16px 0 10px;
            color: #fff;
            font-family: var(--font-head, "Space Grotesk", system-ui, sans-serif);
            font-size: clamp(2rem, 4.2vw, 2.9rem);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: -0.025em;
        }

        .staff-hero__lede {
            margin: 0;
            color: #A9B4C6;
            font-size: 1.0625rem;
            line-height: 1.6;
        }

        /* The glass panel, matching the portal cards on the landing page. */
        .staff-hero__card {
            max-width: 27rem;
            padding: 26px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .16);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
        }

        /* Form controls, restated for a dark panel. The inherited Tailwind
           classes assume a white card: grey-700 labels and grey-300 borders
           disappear against glass. */
        .staff-hero__card label {
            color: #D6DDE8 !important;
            font-weight: 600;
        }

        .staff-hero__card input[type="text"],
        .staff-hero__card input[type="password"] {
            background: rgba(255, 255, 255, .06);
            border-color: rgba(255, 255, 255, .22);
            color: #fff;
        }

        .staff-hero__card input::placeholder { color: #8795A8; }

        .staff-hero__card input[type="text"]:focus,
        .staff-hero__card input[type="password"]:focus {
            background: rgba(255, 255, 255, .10);
            border-color: #E31B23;
            box-shadow: 0 0 0 3px rgba(227, 27, 35, .25);
        }

        /* The icons inside the fields, and the reveal toggle. */
        .staff-hero__card .absolute { color: #8795A8 !important; }

        .staff-hero__list {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, .14);
        }

        .staff-hero__list h2 {
            margin: 0 0 12px;
            color: #8795A8;
            font-family: var(--font-body, system-ui, sans-serif);
            font-size: .6875rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .staff-hero__list ul { margin: 0; padding: 0; list-style: none; }

        .staff-hero__list li {
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            margin-bottom: .5rem;
            color: #C6CFDC;
            font-size: .875rem;
            line-height: 1.5;
        }

        .staff-hero__list li i {
            color: #F05A60;
            margin-top: .2rem;
            width: 1rem;
            text-align: center;
        }

        .staff-hero__support {
            margin: 22px 0 0;
            max-width: 27rem;
            color: #7E8CA0;
            font-size: .8125rem;
        }

        .staff-hero__support a { color: #A9B4C6; text-decoration: none; }
        .staff-hero__support a:hover { text-decoration: underline; }

        @media (max-width: 640px) {
            .staff-hero { min-height: 0; padding: 32px 0; }
            .staff-hero__inner { padding: 0 20px; }
            .staff-hero__card { max-width: none; padding: 20px; }
        }
    </style>

    <div class="staff-hero__inner">
        <div class="staff-hero__copy">
            <span class="staff-hero__eyebrow">Imprint Customs PH</span>
            <h1 class="staff-hero__title">Staff Portal</h1>
            <p class="staff-hero__lede">Employee, Supervisor &amp; Leader Access</p>
        </div>

        <div class="staff-hero__card">
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
                                            class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
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
                                        <a href="#" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
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
                                            class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
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
                                        class="w-full bg-red-600 text-white py-3 px-4 rounded-lg hover:bg-red-700 transition font-medium flex items-center justify-center gap-2 shadow-sm hover:shadow-sm transform hover:-translate-y-0.5 transition-all duration-200"
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

            <div class="staff-hero__list">
                {{-- What this portal actually does. It used to advertise two
                     roles with three features each: Employee got Attendance,
                     Tasks and Orders; a manager tier that does not exist here
                     got Reports, Approvals and Team. Six claims, one of them
                     true. Tasks and Orders belong to the projects and ERP
                     modules, which are not part of the HRIS, and every staff
                     tier lands on the same dashboard. --}}
                <h2>In this portal</h2>
                <ul>
                    <li><i class="fas fa-clock"></i><span>Clock in and out, and review your attendance</span></li>
                    <li><i class="fas fa-money-bill-wave"></i><span>View your payslips</span></li>
                    <li><i class="fas fa-umbrella-beach"></i><span>File leave requests and track their status</span></li>
                </ul>
            </div>
        </div>

        <p class="staff-hero__support">
            Support: <a href="mailto:hr@imprintcustoms.ph">hr@imprintcustoms.ph</a>
            &nbsp;|&nbsp; IT: <a href="mailto:it@imprintcustoms.ph">it@imprintcustoms.ph</a>
        </p>
    </div>
</div>
