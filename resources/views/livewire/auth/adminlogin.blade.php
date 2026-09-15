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

    // DEBUG: Log what we're looking for
    \Log::info('Login attempt:', $credentials);
    
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
        ->first(); // Use first() instead of exists() to see the actual user

    \Log::info('Found user:', $user ? (array)$user : ['not found']);
    
    if (!$user) {
        throw ValidationException::withMessages([
            'username' => __('User not found.'),
        ]);
    }

    // Check if user has admin role
    if ($user->role !== 'admin') {
        \Log::info('User role is not admin:', ['role' => $user->role]);
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
<div class="min-h-screen bg-gradient-to-b from-white to-red-50 py-12 px-4 sm:px-6 lg:px-8" x-data="{ showPassword: false }" x-init="$refs.username.focus()">
    <div class="max-w-md mx-auto">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="mx-auto h-20 w-20 bg-red-600 rounded-full flex items-center justify-center mb-4 overflow-hidden border-2 border-gray-200 shadow-sm">
                <i class="fas fa-crown text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">HR Back Office</h1>
            <p class="text-gray-600">Administrator Access</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 hover:shadow-sm transition-all duration-300">
            <div class="p-8">
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
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition"
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
                                class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition"
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
                            class="w-full bg-red-600 text-white py-3 px-4 rounded-lg hover:from-red-700 hover:to-red-700 transition font-medium flex items-center justify-center gap-2 shadow-sm hover:shadow-sm transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <span wire:loading.remove wire:target="login">
                                <i class="fas fa-sign-in-alt"></i>
                                Access Module
                            </span>
                            <span wire:loading wire:target="login">
                                <i class="fas fa-spinner fa-spin"></i>
                                Verifying Credentials...
                            </span>
                        </button>
                    </div>
                </form>              
            </div>
            
            <!-- Footer -->
            <div class="bg-red-50 px-8 py-4 border-t border-gray-200">
                <div class="flex items-center justify-center">
                    <i class="fas fa-server text-red-600 mr-2"></i>
                    <p class="text-xs text-center text-red-800 font-medium">
                        HRIS - Administrator Authentication
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>