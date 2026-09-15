<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use App\Models\User;

new #[Layout('components.layouts.employee')] class extends Component
{
    /*
     * Openings come from the job_positions table, which HR manages at
     * /hr/positions. The list below the hero and the position dropdown in the
     * application form both read this one property, so they cannot drift apart
     * the way the two hardcoded arrays they replace did.
     */
    public function openPositions()
    {
        return DB::table('job_positions as p')
            ->leftJoin('departments as d', 'p.department_id', '=', 'd.department_id')
            ->where('p.is_open', true)
            ->select('p.position_id', 'p.title', 'p.employment_type', 'p.description', 'd.department_name')
            ->orderByDesc('p.created_at')
            ->get();
    }

    public array $employmentTypes = [
        'full_time'  => 'Full-time',
        'part_time'  => 'Part-time',
        'contract'   => 'Contract',
        'internship' => 'Internship',
    ];

    public $showLogin = true; // Toggle between login and register
    public $email = '';
    public $password = '';
    public $remember = false;
    
    // Registration fields
    public $full_name = '';
    public $username = '';
    public $password_confirmation = '';
    public $phone = '';
    public $address = '';
    public $position = '';
    public $experience = '';
    public $resume = null;
    
    public function toggleForm()
    {
        $this->showLogin = !$this->showLogin;
        $this->reset(['email', 'password', 'full_name', 'username', 'password_confirmation', 
                      'phone', 'address', 'position', 'experience']);
        $this->resetErrorBag();
    }
    
    public function login()
{
    $this->validate([
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ]);
    
    if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
        $user = Auth::user();
        
        // Check if user is an employee (not customer anymore)
        if ($user->role !== 'employee') { // Changed from 'customer' to 'employee'
            Auth::logout();
            $this->addError('email', 'This account is not authorized as an employee.');
            return;
        }
               
        session()->regenerate();
        return redirect()->route('applicant.index');
    }
    
    $this->addError('email', 'The provided credentials are incorrect.');
}
    
    public function register()
{
    $this->validate([
        'full_name' => ['required', 'string', 'max:150'],
        'username' => ['required', 'string', 'max:100', 'unique:users'],
        'email' => ['required', 'string', 'email', 'max:150', 'unique:users'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
        'phone' => ['required', 'string', 'max:50'],
        'address' => ['required', 'string', 'max:500'],
        'position' => ['required', 'string', 'max:100'],
        'experience' => ['required', 'string', 'max:50'],
    ]);
    
    // Create user - role defaults to 'employee' according to your schema
    $user = User::create([
        'full_name' => $this->full_name,
        'username' => $this->username,
        'email' => $this->email,
        'password' => Hash::make($this->password),
        // role will default to 'employee' as per your DB schema
    ]);
    
    // Create employee record
    DB::table('employees')->insert([
        'user_id' => $user->user_id,
        'job_title' => $this->position,
        'hire_date' => now(),
        'salary' => 0.00, // Default salary
        'status' => 'inactive', // Default status
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Create job application record (optional - for tracking)
    DB::table('job_applications')->insert([
        'user_id' => $user->user_id,
        'position_applied' => $this->position,
        'years_experience' => $this->experience,
        'status' => 'pending', // Since we're creating employee immediately
        'application_date' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Auto login after registration
    Auth::login($user);
    
    session()->flash('success', 'Registration successful! You are now registered as an employee.');
    return redirect()->route('applicant.index');
}
}
?>

<div class="careers-page">
    <div class="careers-page__inner">
        <!-- Hero Section -->
        <div class="careers-hero">
            <style>
                /* This photograph is a bright studio shot, so it takes a light
                   scrim and dark type. The dark treatment used on the staff
                   sign-in would fight the picture rather than sit on it. */
                /* The landing page treatment: one photograph, a navy scrim
                   weighted towards the type, and everything else on glass. */
                .careers-page {
                    position: relative;
                    isolation: isolate;
                    min-height: calc(100vh - 64px);
                    padding: 0 0 64px;
                    background: #0C1626;
                }

                .careers-page::before {
                    content: "";
                    position: absolute;
                    inset: 0 0 auto 0;
                    height: 560px;
                    z-index: -2;
                    background-image: url("{{ asset('hero-careers.jpg') }}");
                    background-size: cover;
                    background-position: center 35%;
                }

                .careers-page::after {
                    content: "";
                    position: absolute;
                    inset: 0 0 auto 0;
                    height: 560px;
                    z-index: -1;
                    background: linear-gradient(100deg,
                            rgba(12, 22, 38, .96) 0%,
                            rgba(12, 22, 38, .92) 34%,
                            rgba(12, 22, 38, .72) 58%,
                            rgba(12, 22, 38, .40) 100%),
                        linear-gradient(to bottom,
                            rgba(12, 22, 38, 0) 55%,
                            rgba(12, 22, 38, 1) 100%);
                }

                .careers-hero {
                    padding: 72px 0 56px;
                }

                .careers-hero__copy { max-width: 34rem; }

                .careers-hero__eyebrow {
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

                .careers-hero__copy h1 {
                    margin: 16px 0 12px;
                    font-family: var(--font-head, "Space Grotesk", system-ui, sans-serif);
                    font-size: clamp(2rem, 4.4vw, 3rem);
                    font-weight: 700;
                    line-height: 1.08;
                    letter-spacing: -0.025em;
                    color: #fff;
                }

                .careers-hero__copy p {
                    margin: 0;
                    color: #A9B4C6;
                    font-size: 1.0625rem;
                    line-height: 1.6;
                }

                /* Panels, matching the portal cards on the landing page. */
                .careers-page .panel {
                    padding: 26px;
                    border-radius: 14px;
                    background: rgba(255, 255, 255, .07);
                    border: 1px solid rgba(255, 255, 255, .16);
                    -webkit-backdrop-filter: blur(12px);
                    backdrop-filter: blur(12px);
                }

                /* Type and controls restated for a dark panel: the inherited
                   Tailwind classes all assume a white card. */
                .careers-page .panel h2 { color: #fff; }
                .careers-page .panel h3 { color: #fff; }
                .careers-page .panel label { color: #D6DDE8 !important; }
                .careers-page .panel p,
                .careers-page .panel span:not([class*="bg-"]) { color: #C6CFDC; }

                .careers-page .panel input,
                .careers-page .panel select,
                .careers-page .panel textarea {
                    background: rgba(255, 255, 255, .06) !important;
                    border-color: rgba(255, 255, 255, .22) !important;
                    color: #fff !important;
                }

                .careers-page .panel input::placeholder,
                .careers-page .panel textarea::placeholder { color: #8795A8 !important; }

                .careers-page .panel input:focus,
                .careers-page .panel select:focus,
                .careers-page .panel textarea:focus {
                    background: rgba(255, 255, 255, .10) !important;
                    border-color: #E31B23 !important;
                    box-shadow: 0 0 0 3px rgba(227, 27, 35, .25) !important;
                }

                .careers-page .panel select option { background: #0C1626; color: #fff; }

                /* The listed openings sit on the panel, so their own borders
                   have to come up off white too. */
                .careers-page .panel .border-gray-200 { border-color: rgba(255, 255, 255, .16) !important; }

                .careers-page__inner {
                    width: 100%;
                    max-width: 72rem;
                    margin: 0 auto;
                    padding: 0 32px;
                }

                .careers-page__footnote { color: #7E8CA0; }

                @media (max-width: 860px) {
                    /* Narrow screens put the type over the middle of the frame,
                       where a left-weighted scrim leaves it unreadable. */
                    .careers-page::after {
                        background: linear-gradient(180deg,
                            rgba(12, 22, 38, .94) 0%,
                            rgba(12, 22, 38, .92) 55%,
                            rgba(12, 22, 38, 1) 100%);
                    }
                    .careers-hero { padding: 48px 0 36px; }
                    .careers-page__inner { padding: 0 20px; }
                }

            </style>

            <div class="careers-hero__copy">
                <span class="careers-hero__eyebrow">Careers</span>
                <h1>Join our team at <span style="color:#F05A60;">Imprint Customs</span></h1>
                <p>
                    Looking for an exciting career opportunity? Apply now to
                    become part of our growing team.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Left Column: Info Cards -->
            <div class="space-y-6">
                <!-- Current Openings -->
                <div class="panel">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Current Openings</h2>
                    @php $openings = $this->openPositions(); @endphp
                    @if (count($openings) === 0)
                        <p class="text-sm text-gray-600">
                            No openings are posted right now. Register anyway and we
                            will keep your application on file.
                        </p>
                    @else
                        <div class="space-y-4">
                            @foreach ($openings as $position)
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-red-300 transition-colors">
                                    <div class="flex justify-between items-start gap-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">{{ $position->title }}</h3>
                                            <p class="text-sm text-gray-600">{{ $position->department_name ?? 'Imprint Customs' }}</p>
                                            @if ($position->description)
                                                <p class="text-sm text-gray-500 mt-1">{{ $position->description }}</p>
                                            @endif
                                        </div>
                                        <span class="shrink-0 px-3 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full">
                                            {{ $employmentTypes[$position->employment_type] ?? $position->employment_type }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

            <!-- Right Column: Auth Form -->
            <div class="panel">
                <!-- Form Toggle -->
                <div class="flex mb-8">
                    <button wire:click="toggleForm" 
                            class="flex-1 py-3 text-center font-medium text-lg {{ $showLogin ? 'bg-red-600 text-white rounded-l-lg' : 'bg-gray-100 text-gray-600 rounded-l-lg' }}">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Login
                    </button>
                    <button wire:click="toggleForm" 
                            class="flex-1 py-3 text-center font-medium text-lg {{ !$showLogin ? 'bg-amber-600 text-white rounded-r-lg' : 'bg-gray-100 text-gray-600 rounded-r-lg' }}">
                        <i class="fas fa-user-plus mr-2"></i>
                        Register
                    </button>
                </div>

                @if($showLogin)
                    <!-- Login Form -->
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Applicant Login</h2>
                        
                        @if(session('success'))
                            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
                                <i class="fas fa-check-circle mr-2"></i>
                                {{ session('success') }}
                            </div>
                        @endif

                        <form wire:submit="login" class="space-y-6">
                            <!-- Email -->
                            <div>
                                <label for="login-email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                    <input wire:model="email" 
                                           id="login-email"
                                           type="email" 
                                           required
                                           class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                           placeholder="you@example.com">
                                </div>
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Password -->
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label for="login-password" class="block text-sm font-medium text-gray-700">
                                        Password
                                    </label>
                                    <a href="#" class="text-sm text-red-600 hover:text-red-700">
                                        Forgot password?
                                    </a>
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input wire:model="password" 
                                           id="login-password"
                                           type="password" 
                                           required
                                           class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                           placeholder="••••••••">
                                </div>
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" 
                                    class="w-full bg-red-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Sign In
                            </button>
                        </form>

                    </div>
                @else
                    <!-- Registration Form -->
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Create Applicant Account</h2>
                        
                        <form wire:submit="register" class="space-y-6">
                            <!-- Personal Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Full Name -->
                                <div>
                                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Full Name *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                        <input wire:model="full_name" 
                                               id="full_name"
                                               type="text" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="John Doe">
                                    </div>
                                    @error('full_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Username -->
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                        Username *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-at text-gray-400"></i>
                                        </div>
                                        <input wire:model="username" 
                                               id="username"
                                               type="text" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="johndoe">
                                    </div>
                                    @error('username')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Email -->
                                <div>
                                    <label for="register-email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Address *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-envelope text-gray-400"></i>
                                        </div>
                                        <input wire:model="email" 
                                               id="register-email"
                                               type="email" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="you@example.com">
                                    </div>
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Phone Number *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-phone text-gray-400"></i>
                                        </div>
                                        <input wire:model="phone" 
                                               id="phone"
                                               type="tel" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="+1234567890">
                                    </div>
                                    @error('phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Address -->
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Address *
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 pt-3 pointer-events-none">
                                        <i class="fas fa-home text-gray-400"></i>
                                    </div>
                                    <textarea wire:model="address" 
                                              id="address"
                                              rows="2"
                                              required
                                              class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                              placeholder="123 Main St, City, Country"></textarea>
                                </div>
                                @error('address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Application Details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Position Applied -->
                                <div>
                                    <label for="position" class="block text-sm font-medium text-gray-700 mb-2">
                                        Position Applied For *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-briefcase text-gray-400"></i>
                                        </div>
                                        <select wire:model="position" 
                                                id="position"
                                                required
                                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent appearance-none">
                                            <option value="">Select Position</option>
                                            {{-- Same source as the openings list above. --}}
                                            @foreach ($this->openPositions() as $position)
                                                <option value="{{ $position->title }}">{{ $position->title }}</option>
                                            @endforeach
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    @error('position')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Experience -->
                                <div>
                                    <label for="experience" class="block text-sm font-medium text-gray-700 mb-2">
                                        Years of Experience *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-chart-line text-gray-400"></i>
                                        </div>
                                        <select wire:model="experience" 
                                                id="experience"
                                                required
                                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent appearance-none">
                                            <option value="">Select Experience</option>
                                            <option value="0-1 years">0-1 years</option>
                                            <option value="1-3 years">1-3 years</option>
                                            <option value="3-5 years">3-5 years</option>
                                            <option value="5-10 years">5-10 years</option>
                                            <option value="10+ years">10+ years</option>
                                        </select>
                                    </div>
                                    @error('experience')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Password -->
                                <div>
                                    <label for="register-password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Password *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input wire:model="password" 
                                               id="register-password"
                                               type="password" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="••••••••">
                                    </div>
                                    @error('password')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Confirm Password -->
                                <div>
                                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirm Password *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input wire:model="password_confirmation" 
                                               id="password_confirmation"
                                               type="password" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="••••••••">
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="terms" 
                                           name="terms" 
                                           type="checkbox" 
                                           required
                                           class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="terms" class="text-gray-700">
                                        I agree to the 
                                        <a href="#" class="text-amber-600 hover:text-amber-700">Terms of Service</a> 
                                        and 
                                        <a href="#" class="text-amber-600 hover:text-amber-700">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" 
                                    class="w-full bg-amber-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-colors">
                                <i class="fas fa-user-plus mr-2"></i>
                                Create Account & Apply
                            </button>
                        </form>

                        <div class="mt-6 text-center text-sm text-gray-600">
                            <p>Already have an account? 
                                <button wire:click="toggleForm" class="text-amber-600 hover:text-amber-700 font-medium">
                                    Sign in here
                                </button>
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Bottom Info -->
        <div class="mt-12 text-center">
            {{-- inline-flex with a fixed gap cannot shrink, so these three
                 items forced ~460px and gave the whole page a horizontal
                 scrollbar on a phone. Wrapping instead. --}}
            <div class="careers-page__footnote flex flex-wrap items-center justify-center gap-x-6 gap-y-2">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-red-600 mr-2"></i>
                    <span>Secure Application Process</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-clock text-red-600 mr-2"></i>
                    <span>24/7 Application Support</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-headset text-red-600 mr-2"></i>
                    <span>HR Support: hr@imprintcustoms.ph</span>
                </div>
            </div>
        </div>
    </div>
</div>