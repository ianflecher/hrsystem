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

    /*
     * The team photograph behind the hero. Optional on purpose: drop a file at
     * public/hero-team.jpg and the hero uses it, otherwise the same layout
     * renders on the flat navy below and nothing breaks.
     */
    public function heroImage(): ?string
    {
        return file_exists(public_path('hero-team.jpg'))
            ? asset('hero-team.jpg')
            : null;
    }
}; ?>

<div class="hero" @if ($this->heroImage()) style="--hero-image: url('{{ $this->heroImage() }}')" @endif>
    <style>
        .hero {
            --ink-on-dark:   #ffffff;
            --muted-on-dark: #A9B4C6;
            --brand:         #E31B23;
            --brand-hover:   #B5141A;
            --navy:          #0C1626;

            position: relative;
            isolation: isolate;
            min-height: calc(100vh - 64px);
            display: flex;
            align-items: center;
            padding: 56px 0;
            background: var(--navy);
            overflow: hidden;
        }

        /* The photograph, and a scrim over it. Text sits on the left, so the
           scrim is heaviest there and thins out across the image rather than
           greying the whole picture down uniformly. */
        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: var(--hero-image, none);
            background-size: cover;
            background-position: center right;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                linear-gradient(100deg,
                    rgba(12, 22, 38, .97) 0%,
                    rgba(12, 22, 38, .93) 34%,
                    rgba(12, 22, 38, .70) 56%,
                    rgba(12, 22, 38, .45) 100%);
        }

        @media (max-width: 900px) {
            /* Narrow screens put the text over the middle of the photo, where
               a left-weighted scrim leaves it unreadable. */
            .hero::after {
                background: linear-gradient(180deg,
                    rgba(12, 22, 38, .93) 0%,
                    rgba(12, 22, 38, .88) 100%);
            }
        }

        .hero__inner {
            width: 100%;
            max-width: 72rem;
            margin: 0 auto;
            padding: 0 32px;
        }

        .hero__copy { max-width: 34rem; }

        .hero__eyebrow {
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

        .hero__title {
            margin: 18px 0 12px;
            color: var(--ink-on-dark);
            font-family: var(--font-head, "Space Grotesk", system-ui, sans-serif);
            font-size: clamp(2rem, 4.6vw, 3.25rem);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: -0.025em;
        }

        .hero__lede {
            margin: 0;
            color: var(--muted-on-dark);
            font-size: 1.0625rem;
            line-height: 1.6;
            max-width: 30rem;
        }

        .hero__portals {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 36px;
        }

        /* Glass panels rather than white cards: on a photograph a solid white
           block reads as a hole punched through the image. */
        .hero__card {
            flex: 1 1 16rem;
            max-width: 19rem;
            display: flex;
            flex-direction: column;
            padding: 22px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .16);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            text-decoration: none;
            transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
        }

        .hero__card:hover {
            background: rgba(255, 255, 255, .12);
            border-color: rgba(227, 27, 35, .65);
            transform: translateY(-2px);
        }

        .hero__icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            margin-bottom: 16px;
            background: var(--brand);
            color: #fff;
            font-size: .95rem;
        }

        .hero__name {
            margin: 0;
            color: var(--ink-on-dark);
            font-size: 1.0625rem;
            font-weight: 600;
        }

        .hero__desc {
            margin: 6px 0 0;
            flex: 1;
            color: var(--muted-on-dark);
            font-size: .875rem;
            line-height: 1.55;
        }

        .hero__cta {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 20px;
            color: #fff;
            font-size: .875rem;
            font-weight: 600;
        }

        .hero__cta i { transition: transform .15s ease; }
        .hero__card:hover .hero__cta i { transform: translateX(3px); }

        @media (max-width: 640px) {
            .hero { min-height: 0; padding: 40px 0; }
            .hero__inner { padding: 0 20px; }
            .hero__card { flex: 1 1 100%; max-width: none; }
        }
    </style>

    <div class="hero__inner">
        <div class="hero__copy">
            <span class="hero__eyebrow">Imprint Customs PH</span>
            <h1 class="hero__title">Human Resource Information System</h1>
            <p class="hero__lede">
                Attendance, payslips, leave and hiring &mdash; in one place for
                the whole team.
            </p>
        </div>

        <div class="hero__portals">
            @foreach ($portals as $portal)
                <a href="{{ route($portal['route']) }}" class="hero__card">
                    <span class="hero__icon"><i class="{{ $portal['icon'] }}"></i></span>
                    <h2 class="hero__name">{{ $portal['name'] }}</h2>
                    <p class="hero__desc">{{ $portal['desc'] }}</p>
                    <span class="hero__cta">Sign in <i class="fas fa-arrow-right"></i></span>
                </a>
            @endforeach
        </div>
    </div>
</div>
