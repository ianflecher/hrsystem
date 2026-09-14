<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.landing')] class extends Component
{
    public array $portals = [
        [
            'name'  => 'HR Back Office',
            'desc'  => 'Applications, attendance, leave and payroll administration.',
            'route' => 'admin.login',
            'icon'  => 'fas fa-users-cog',
        ],
        [
            'name'  => 'Employee Portal',
            'desc'  => 'Clock in, view payslips and file leave requests.',
            'route' => 'employee.login',
            'icon'  => 'fas fa-id-badge',
        ],
        [
            'name'  => 'Careers',
            'desc'  => 'Browse openings and track your application.',
            'route' => 'applicant.login',
            'icon'  => 'fas fa-briefcase',
        ],
    ];
}; ?>

<div class="hris-portals mx-auto w-full max-w-5xl px-6 py-16">
    <style>
        .hris-portals { --hris-ink: #0f172a; --hris-muted: #475569; --hris-surface: #ffffff; --hris-line: #e2e8f0; --hris-accent: #16a34a; }
        .hris-portals__title { color: var(--hris-ink); }
        .hris-portals__lede  { color: var(--hris-muted); }
        .hris-portals__card {
            background: var(--hris-surface);
            border: 1px solid var(--hris-line);
            border-radius: 0.75rem;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }
        .hris-portals__card:hover {
            border-color: var(--hris-accent);
            box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.15);
            transform: translateY(-2px);
        }
        .hris-portals__icon {
            width: 2.75rem; height: 2.75rem;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 0.5rem; margin-bottom: 1rem;
            background: color-mix(in srgb, var(--hris-accent) 15%, transparent);
            color: var(--hris-accent);
        }
        .hris-portals__name { color: var(--hris-ink); font-size: 1.125rem; font-weight: 600; }
        .hris-portals__desc { color: var(--hris-muted); font-size: .875rem; margin-top: .25rem; flex: 1; }
        .hris-portals__cta  { color: var(--hris-accent); font-size: .875rem; font-weight: 500; margin-top: 1rem; }
        .hris-portals__card:hover .hris-portals__cta { text-decoration: underline; }
    </style>

    <header class="mb-12 text-center">
        <h1 class="hris-portals__title text-3xl font-bold tracking-tight sm:text-4xl">
            Human Resource Information System
        </h1>
        <p class="hris-portals__lede mx-auto mt-3 max-w-2xl text-base">
            Choose the portal that matches your role to sign in.
        </p>
    </header>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($portals as $portal)
            <a href="{{ route($portal['route']) }}" class="hris-portals__card group">
                <span class="hris-portals__icon">
                    <i class="{{ $portal['icon'] }}"></i>
                </span>
                <h2 class="hris-portals__name">{{ $portal['name'] }}</h2>
                <p class="hris-portals__desc">{{ $portal['desc'] }}</p>
                <span class="hris-portals__cta">
                    Sign in <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </span>
            </a>
        @endforeach
    </div>
</div>
