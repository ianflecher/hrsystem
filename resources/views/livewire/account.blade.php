<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * What a signed-in person can change about themselves: their picture, their
 * name, and their password. Everything else about an employee - job title,
 * department, salary, shift - belongs to HR and is not editable here.
 */
new #[Layout('components.layouts.account')] class extends Component
{
    use WithFileUploads;

    public string $full_name = '';
    public string $email = '';

    public $photo;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->full_name = $user->full_name ?? '';
        $this->email = $user->email ?? '';
    }

    public function getUserProperty()
    {
        return Auth::user()->fresh();
    }

    /**
     * The employee record behind the account, when there is one - an applicant
     * signs in here too and has none.
     */
    public function getEmploymentProperty()
    {
        return DB::table('employees as e')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.user_id', Auth::user()->user_id)
            ->select('e.job_title', 'e.hire_date', 'e.status', 'e.shift_start', 'd.department_name')
            ->first();
    }

    public function savePhoto(): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], ['photo' => 'picture']);

        $user = Auth::user();

        // The old file goes, or every change would leave one behind.
        if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $this->photo->store('profile-photos', 'public');

        DB::table('users')->where('user_id', $user->user_id)->update([
            'profile_photo_path' => $path,
            'updated_at'         => now(),
        ]);

        $this->reset('photo');
        session()->flash('success', 'Your picture has been updated.');
    }

    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        DB::table('users')->where('user_id', $user->user_id)->update([
            'profile_photo_path' => null,
            'updated_at'         => now(),
        ]);

        session()->flash('success', 'Your picture has been removed.');
    }

    public function saveName(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email'     => ['required', 'email', 'max:150',
                            Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
        ]);

        DB::table('users')->where('user_id', $user->user_id)->update([
            'full_name'  => $data['full_name'],
            'email'      => $data['email'],
            'updated_at' => now(),
        ]);

        session()->flash('success', 'Your details have been saved.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'That is not your current password.');

            return;
        }

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', 'Choose a password different from your current one.');

            return;
        }

        DB::table('users')->where('user_id', $user->user_id)->update([
            'password'             => Hash::make($this->password),
            // Changing it here satisfies a pending first-password change too.
            'must_change_password' => false,
            'updated_at'           => now(),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        session()->flash('success', 'Your password has been changed.');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name ?: 'User'));

        return strtoupper(substr($parts[0] ?? 'U', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}; ?>

<div class="p-6 md:p-8">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">My account</h1>
            <p class="text-sm text-gray-600 mt-1">
                Your picture, your name and your password. Everything else is kept by HR.
            </p>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Picture ------------------------------------------------------ --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 mb-5">
            <h2 class="text-base font-semibold text-gray-900">Profile picture</h2>
            <p class="text-sm text-gray-600 mt-1 mb-4">
                JPG, PNG or WebP, up to 2&nbsp;MB.
            </p>

            <div class="flex flex-wrap items-center gap-5">
                <div class="shrink-0">
                    {{-- isPreviewable() guards the call: temporaryUrl() throws on a
                         file Livewire cannot show, so picking a PDF by mistake
                         used to produce a 500 rather than the validation
                         message waiting for it below. --}}
                    @if ($photo && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="Preview"
                             class="h-20 w-20 rounded-full object-cover border border-gray-200">
                    @elseif ($this->user->profile_photo_path)
                        <img src="{{ Storage::disk('public')->url($this->user->profile_photo_path) }}"
                             alt="{{ $this->user->full_name }}"
                             class="h-20 w-20 rounded-full object-cover border border-gray-200">
                    @else
                        <div class="h-20 w-20 rounded-full bg-slate-100 border border-gray-200 flex items-center justify-center">
                            <span class="text-xl font-semibold text-slate-500">{{ $this->initials() }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex-1 min-w-[16rem]">
                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-gray-700
                                  file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                                  file:text-sm file:font-semibold file:bg-slate-100 file:text-gray-700
                                  hover:file:bg-slate-200">
                    @error('photo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div wire:loading wire:target="photo" class="mt-2 text-sm text-gray-500">Reading the file...</div>

                    <div class="flex items-center gap-2 mt-3">
                        <button wire:click="savePhoto" @disabled(! $photo) class="btn-primary disabled:opacity-40 disabled:cursor-not-allowed">
                            Save picture
                        </button>
                        @if ($this->user->profile_photo_path)
                            <button wire:click="removePhoto"
                                    wire:confirm="Remove your profile picture?"
                                    class="btn-secondary">Remove</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Name and email ----------------------------------------------- --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 mb-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Your details</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label" for="full_name">Full name</label>
                    <input id="full_name" type="text" wire:model="full_name" class="form-input">
                    @error('full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" wire:model="email" class="form-input">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-4">
                <button wire:click="saveName" class="btn-primary">Save details</button>
            </div>
        </div>

        {{-- Password ------------------------------------------------------ --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 mb-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Change password</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="form-label" for="current_password">Current password</label>
                    <input id="current_password" type="password" wire:model="current_password" class="form-input">
                    @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="password">New password</label>
                    <input id="password" type="password" wire:model="password" class="form-input" placeholder="At least 8 characters">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" class="form-input">
                </div>
            </div>

            <div class="mt-4">
                <button wire:click="savePassword" class="btn-primary">Change password</button>
            </div>
        </div>

        {{-- What HR keeps -------------------------------------------------- --}}
        @if ($this->employment)
            <div class="bg-slate-50 border border-gray-200 rounded-xl p-6">
                <h2 class="text-base font-semibold text-gray-900">Your employment</h2>
                <p class="text-sm text-gray-600 mt-1 mb-4">
                    Kept by HR. Ask them if any of it is wrong.
                </p>

                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Job title</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $this->employment->job_title }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Department</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $this->employment->department_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Shift starts</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">
                            {{ $this->employment->shift_start ? date('g:i A', strtotime($this->employment->shift_start)) : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Since</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">
                            {{ $this->employment->hire_date ? date('M j, Y', strtotime($this->employment->hire_date)) : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>
</div>
