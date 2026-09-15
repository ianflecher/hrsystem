<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.landing')] class extends Component
{
    /*
     * Staff-facing portals only. The HR back office is deliberately absent:
     * it is reached at /admin/login by people who already know the address,
     * rather than being advertised to every visitor who lands here.
     */
    public array $portals = [
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

<div class="hris-portals">
    <style>
        .hris-portals {
            --ink: #17202E;
            --muted: #566172;
            --surface: #ffffff;
            --line: #E5E9F0;
            --brand: #E31B23;
            --brand-soft: #FDECEC;

            /* Fill the space under the header instead of stranding the cards
               at the top of a tall empty page.
               The layout already spends vertical room above us: main carries
               pt-16 (64px) to clear the fixed header, and the Flux wrapper adds
               p-6 (24px) -> lg:p-8 (32px) top and bottom. Subtracting exactly
               that is what keeps the page from growing a scrollbar. */
            min-height: calc(100vh - 112px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 24px 0 40px;
        }

        @media (min-width: 1024px) {
            .hris-portals { min-height: calc(100vh - 128px); }
        }

        .hris-portals__head { text-align: center; margin-bottom: 40px; }

        .hris-portals__title {
            color: var(--ink);
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 10px;
        }

        .hris-portals__lede {
            color: var(--muted);
            font-size: 1rem;
            margin: 0;
        }

        /* Flex, not a fixed 3-column grid: the row stays centred whether there
           are two portals or three. */
        .hris-portals__grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 24px;
            width: 100%;
            max-width: 56rem;
            margin: 0 auto;
        }

        .hris-portals__card {
            flex: 1 1 18rem;
            max-width: 22rem;
            display: flex;
            flex-direction: column;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 28px;
            text-decoration: none;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .hris-portals__card:hover {
            border-color: var(--brand);
            box-shadow: 0 10px 28px rgba(19, 30, 51, .10);
            transform: translateY(-2px);
        }

        .hris-portals__icon {
            width: 46px; height: 46px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 12px;
            margin-bottom: 18px;
            background: var(--brand-soft);
            color: var(--brand);
            font-size: 1.05rem;
        }

        .hris-portals__name { color: var(--ink); font-size: 1.15rem; font-weight: 600; margin: 0; }
        .hris-portals__desc { color: var(--muted); font-size: .9rem; line-height: 1.55; margin: 6px 0 0; flex: 1; }

        .hris-portals__cta {
            color: var(--brand);
            font-size: .9rem;
            font-weight: 600;
            margin-top: 22px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .hris-portals__cta i { transition: transform .15s ease; }
        .hris-portals__card:hover .hris-portals__cta i { transform: translateX(3px); }

        @media (max-width: 640px) {
            .hris-portals { padding: 16px 0 32px; min-height: 0; }
            .hris-portals__card { flex: 1 1 100%; max-width: none; }
        }
    </style>

    <div class="hris-portals__head">
        <h1 class="hris-portals__title">Human Resource Information System</h1>
        <p class="hris-portals__lede">Choose the portal that matches your role to sign in.</p>
    </div>

    <div class="hris-portals__grid">
        @foreach ($portals as $portal)
            <a href="{{ route($portal['route']) }}" class="hris-portals__card">
                <span class="hris-portals__icon"><i class="{{ $portal['icon'] }}"></i></span>
                <h2 class="hris-portals__name">{{ $portal['name'] }}</h2>
                <p class="hris-portals__desc">{{ $portal['desc'] }}</p>
                <span class="hris-portals__cta">Sign in <i class="fas fa-arrow-right"></i></span>
            </a>
        @endforeach
    </div>
</div>
