<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

new #[Layout('components.layouts.employee')] class extends Component
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

    // Prepare credentials for authentication
    $credentials = ['password' => $this->password];
    
    // Determine if input is email or username
    if (filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
        $credentials['email'] = $this->username;
    } else {
        $credentials['username'] = $this->username;
    }

    
    // Check if user exists
    $user = DB::table('users')
        ->where(function($query) use ($credentials) {
            if (isset($credentials['email'])) {
                $query->where('email', $credentials['email']);
            } else {
                $query->where('username', $credentials['username']);
            }
        })
        ->whereNull('deleted_at')
        ->first();

    if (!$user) {
        throw ValidationException::withMessages([
            'username' => __('User not found.'),
        ]);
    }

    // Check if user has admin role
    if ($user->role !== 'admin') {
        throw ValidationException::withMessages([
            'username' => __('Access denied. Administrator credentials required.'),
        ]);
    }

    // Attempt authentication
    if (!Auth::attempt($credentials, $this->remember)) {
        throw ValidationException::withMessages([
            'username' => __('Invalid credentials.'),
        ]);
    }

    // Get the authenticated user
    $user = Auth::user();
    
    if ($user->role !== 'admin') {
        Auth::logout();
        throw ValidationException::withMessages([
            'username' => __('Insufficient permissions. Administrator access required.'),
        ]);
    }

    session()->regenerate();

    // Redirect based on username
    return $this->redirectBasedOnUsername($user->username);
}

    private function redirectBasedOnUsername(string $username)
    {
        $redirects = [
            'admin' => route('admin.dashboard'), // Super admin
            'hr'    => route('hr.home'),
        ];

        // Default to admin dashboard if username not found
        return redirect()->to($redirects[$username] ?? route('admin.dashboard'));
    }
}
?>

<div class="admin-hero" x-data="{ showPassword: false }" x-init="$refs.username.focus()">
    <style>
        /* Same treatment as the landing page and the other two sign-ins: one
           photograph edge to edge, a navy scrim weighted towards the type, and
           the form on the same glass as the portal cards. */
        .admin-hero {
            position: relative;
            isolation: isolate;
            min-height: calc(100vh - 64px);
            display: flex;
            align-items: center;
            padding: 48px 0;
            background: #0C1626;
            overflow: hidden;
        }

        .admin-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: url("{{ asset('hero-admin.jpg') }}");
            background-size: cover;
            background-position: center right;
        }

        .admin-hero::after {
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
            .admin-hero::after {
                background: linear-gradient(180deg,
                    rgba(12, 22, 38, .95) 0%,
                    rgba(12, 22, 38, .90) 100%);
            }
        }

        .admin-hero__inner {
            width: 100%;
            max-width: 72rem;
            margin: 0 auto;
            padding: 0 32px;
        }

        .admin-hero__copy { max-width: 30rem; margin-bottom: 26px; }

        .admin-hero__eyebrow {
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

        .admin-hero__title {
            margin: 16px 0 10px;
            color: #fff;
            font-family: var(--font-head, "Space Grotesk", system-ui, sans-serif);
            font-size: clamp(2rem, 4.2vw, 2.9rem);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: -0.025em;
        }

        .admin-hero__lede {
            margin: 0;
            color: #A9B4C6;
            font-size: 1.0625rem;
            line-height: 1.6;
        }

        .admin-hero__card {
            max-width: 27rem;
            padding: 26px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .16);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
        }

        /* The inherited Tailwind classes assume a white card, so grey-700
           labels and grey-300 borders disappear against glass. */
        .admin-hero__card label {
            color: #D6DDE8 !important;
            font-weight: 600;
        }

        .admin-hero__card input[type="text"],
        .admin-hero__card input[type="password"] {
            background: rgba(255, 255, 255, .06) !important;
            border-color: rgba(255, 255, 255, .22) !important;
            color: #fff !important;
        }

        .admin-hero__card input::placeholder { color: #8795A8 !important; }

        .admin-hero__card input[type="text"]:focus,
        .admin-hero__card input[type="password"]:focus {
            background: rgba(255, 255, 255, .10) !important;
            border-color: #E31B23 !important;
            box-shadow: 0 0 0 3px rgba(227, 27, 35, .25) !important;
        }

        .admin-hero__card .absolute { color: #8795A8 !important; }

        .admin-hero__note {
            margin: 22px 0 0;
            max-width: 27rem;
            color: #7E8CA0;
            font-size: .8125rem;
        }

        @media (max-width: 640px) {
            .admin-hero { min-height: 0; padding: 32px 0; }
            .admin-hero__inner { padding: 0 20px; }
            .admin-hero__card { max-width: none; padding: 20px; }
        }
    </style>

    <div class="admin-hero__inner">
        <div class="admin-hero__copy">
            <span class="admin-hero__eyebrow">Imprint Customs PH</span>
            <h1 class="admin-hero__title">HR Back Office</h1>
            <p class="admin-hero__lede">Administrator access</p>
        </div>

        <div class="admin-hero__card">
            <form wire:submit.prevent="login" class="space-y-6">
                                @csrf
                    
                                <!-- Username/Email -->
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                        Admin Username or Email
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            wire:model="username" 
                                            id="username"
                                            x-ref="username"
                                            required
                                            autofocus
                                            placeholder="admin or hr"
                                            class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                                        >
                                        <div class="absolute left-3 top-3 text-gray-400">
                                            <i class="fas fa-user-tie"></i>
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
                                            <i class="fas fa-key"></i>
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
                                            Sign in
                                        </span>
                                        <span wire:loading wire:target="login">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Verifying Credentials...
                                        </span>
                                    </button>
                                </div>
                            </form>
        </div>

        <p class="admin-hero__note">
            <i class="fas fa-lock" style="margin-right:.4rem;"></i>
            HRIS &mdash; administrator authentication
        </p>
    </div>
</div>
