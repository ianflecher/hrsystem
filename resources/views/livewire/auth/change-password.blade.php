<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

new #[Layout('components.layouts.landing')] class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function save()
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'That is not the password you were given.');

            return null;
        }

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', 'Choose a password different from the one you were given.');

            return null;
        }

        DB::table('users')->where('user_id', $user->user_id)->update([
            'password'             => Hash::make($this->password),
            'must_change_password' => false,
            'updated_at'           => now(),
        ]);

        session()->flash('success', 'Password changed.');

        // Back to whichever portal this account belongs to.
        return redirect()->route(
            in_array($user->role, ['admin', 'hr'], true) ? 'hr.home' : 'employee.dashboard'
        );
    }
}; ?>

<div class="pwc">
    <style>
        .pwc {
            position: relative;
            isolation: isolate;
            min-height: calc(100vh - 64px);
            display: flex;
            align-items: center;
            padding: 48px 0;
            background: #0C1626;
            overflow: hidden;
        }

        .pwc::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: url("{{ asset('hero-staff.jpg') }}");
            background-size: cover;
            background-position: center right;
        }

        .pwc::after {
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
            .pwc::after {
                background: linear-gradient(180deg,
                    rgba(12, 22, 38, .95) 0%, rgba(12, 22, 38, .90) 100%);
            }
        }

        .pwc__inner { width: 100%; max-width: 72rem; margin: 0 auto; padding: 0 32px; }
        .pwc__copy { max-width: 30rem; margin-bottom: 26px; }

        .pwc__eyebrow {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .3125rem .75rem; border-radius: 999px;
            background: rgba(227, 27, 35, .16);
            border: 1px solid rgba(227, 27, 35, .38);
            color: #FCA5A9; font-size: .75rem; font-weight: 600;
            letter-spacing: .04em; text-transform: uppercase;
        }

        .pwc__title {
            margin: 16px 0 10px; color: #fff;
            font-family: var(--font-head, "Space Grotesk", system-ui, sans-serif);
            font-size: clamp(1.875rem, 4vw, 2.6rem); font-weight: 700;
            line-height: 1.1; letter-spacing: -0.025em;
        }

        .pwc__lede { margin: 0; color: #A9B4C6; font-size: 1.0625rem; line-height: 1.6; }

        .pwc__card {
            max-width: 27rem; padding: 26px; border-radius: 14px;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .16);
            -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
        }

        .pwc__card label {
            display: block; margin-bottom: .375rem;
            color: #D6DDE8; font-size: .8125rem; font-weight: 600;
        }

        .pwc__card input {
            width: 100%; padding: .625rem .75rem; border-radius: 8px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .22);
            color: #fff; font-size: .875rem;
        }

        .pwc__card input::placeholder { color: #8795A8; }

        .pwc__card input:focus {
            outline: none;
            background: rgba(255, 255, 255, .10);
            border-color: var(--accent, #2563eb);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .25);
        }

        .pwc__field { margin-bottom: 16px; }
        .pwc__error { margin-top: .375rem; color: #FCA5A9; font-size: .8125rem; }

        .pwc__submit {
            width: 100%; margin-top: 4px; padding: .625rem 1.125rem;
            border-radius: 8px; border: 1px solid var(--brand, #E31B23);
            background: var(--brand, #E31B23); color: #fff;
            font-size: .875rem; font-weight: 600; cursor: pointer;
        }

        .pwc__submit:hover { background: var(--brand-hover, #B5141A); border-color: var(--brand-hover, #B5141A); }

        @media (max-width: 640px) {
            .pwc { min-height: 0; padding: 32px 0; }
            .pwc__inner { padding: 0 20px; }
            .pwc__card { max-width: none; padding: 20px; }
        }
    </style>

    <div class="pwc__inner">
        <div class="pwc__copy">
            <span class="pwc__eyebrow">One more step</span>
            <h1 class="pwc__title">Choose your own password</h1>
            <p class="pwc__lede">
                The password you were given was set by someone else, so it has to
                be replaced before you go any further.
            </p>
        </div>

        <div class="pwc__card">
            <form wire:submit.prevent="save">
                @csrf

                <div class="pwc__field">
                    <label for="current_password">The password you were given</label>
                    <input id="current_password" type="password" wire:model="current_password" autofocus>
                    @error('current_password') <p class="pwc__error">{{ $message }}</p> @enderror
                </div>

                <div class="pwc__field">
                    <label for="password">New password</label>
                    <input id="password" type="password" wire:model="password" placeholder="At least 8 characters">
                    @error('password') <p class="pwc__error">{{ $message }}</p> @enderror
                </div>

                <div class="pwc__field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation">
                </div>

                <button type="submit" class="pwc__submit">Save and continue</button>
            </form>
        </div>
    </div>
</div>
