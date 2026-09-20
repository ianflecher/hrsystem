<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/*
 * The company profile, for people who have applied.
 *
 * An applicant's only other view of us is a status word on a dashboard. This
 * is the page that answers "what is this place actually like" - what we make,
 * how the work is divided, and what to expect from the hiring itself.
 *
 * The figures are counted from the database rather than typed in, so nothing
 * here can quietly go stale. Pictures are the same optional slots the careers
 * page uses: missing files leave a brand panel, not a broken image.
 */
new #[Layout('components.layouts.applicant')] class extends Component
{
    /*
     * The floor and the figures come from App\Support\CareersStory, which the
     * public "Who we are" page also reads - one account of the company rather
     * than two that can drift.
     */
    public function floorAreas(): array
    {
        return \App\Support\CareersStory::areas();
    }

    public function figures(): array
    {
        return \App\Support\CareersStory::figures();
    }

    /** What happens after you apply, in plain order. */
    public array $steps = [
        ['name' => 'You apply',        'body' => 'Your application lands with HR the moment you submit it. Nothing is lost in an inbox.'],
        ['name' => 'We read it',       'body' => 'Every application is opened and marked reviewed. You will see the status change here.'],
        ['name' => 'Shortlist',        'body' => 'If your experience fits the role, you are shortlisted and we arrange a time to meet.'],
        ['name' => 'Interview',        'body' => 'The date, time, place and who you are meeting all appear on your dashboard.'],
        ['name' => 'Decision',         'body' => 'Hired or not, the answer reaches you here. We would rather tell you than leave you guessing.'],
    ];

    /*
     * The workstation photographs. The list itself lives in
     * App\Support\Workstations because the public careers page shows the same
     * set, and two copies of the captions would drift apart.
     */
    public function workstations(): array
    {
        return \App\Support\Workstations::all();
    }

    public function pic(string $name): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (file_exists(public_path("img/careers/{$name}.{$ext}"))) {
                return asset("img/careers/{$name}.{$ext}");
            }
        }

        return null;
    }
}; ?>

<div class="profile">
    <style>
        .profile {
            --ink:   #0C1626;
            --muted: #5A6A80;
            --line:  #E3E8EF;
            --brand: #E31B23;
            --wash:  #F6F8FB;
            color: var(--ink);
        }

        .profile h1, .profile h2, .profile h3 { letter-spacing: -0.025em; }

        /* ---------------------------------------------------------- hero */
        .profile__hero {
            position: relative;
            isolation: isolate;
            border-radius: 16px;
            overflow: hidden;
            padding: 84px 44px;
            background: var(--ink);
            margin-bottom: 40px;
        }

        .profile__hero-img { position: absolute; inset: 0; z-index: -2; background-size: cover; background-position: center 45%; }

        .profile__hero-img--empty {
            background:
                radial-gradient(110% 90% at 80% 10%, rgba(227, 27, 35, .40) 0%, transparent 62%),
                linear-gradient(140deg, #0C1626 0%, #17233A 100%);
        }

        .profile__hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(95deg, rgba(12,22,38,.94) 0%, rgba(12,22,38,.78) 52%, rgba(12,22,38,.42) 100%);
        }

        .profile__eyebrow {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #FF6B72;
            margin-bottom: 16px;
        }

        .profile__hero h1 {
            margin: 0;
            max-width: 18ch;
            color: #fff;
            font-size: clamp(2rem, 4.4vw, 3.25rem);
            font-weight: 700;
            line-height: 1.06;
        }

        .profile__hero p {
            margin: 20px 0 0;
            max-width: 46ch;
            color: #C3CDDC;
            font-size: 1.0625rem;
            line-height: 1.65;
        }

        /* --------------------------------------------------------- blocks */
        .profile__block { margin-bottom: 40px; }

        .profile__head { max-width: 46rem; margin-bottom: 26px; }

        .profile__head h2 { margin: 0; font-size: 1.75rem; font-weight: 700; }

        .profile__head p { margin: 12px 0 0; color: var(--muted); font-size: 1rem; line-height: 1.65; }

        .profile__lede {
            font-size: 1.125rem;
            line-height: 1.7;
            color: var(--ink);
            max-width: 62ch;
            margin: 0 0 16px;
        }

        .profile__lede + .profile__lede { color: var(--muted); font-size: 1rem; }

        /* -------------------------------------------------------- figures */
        /* Sized by how many there actually are: filtering out a figure that
           counts zero used to leave empty grey cells in a fixed three-column
           grid. */
        .figures {
            display: grid;
            grid-template-columns: repeat(var(--figure-count, 3), 1fr);
            gap: 1px;
            background: var(--line);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }

        .figure { background: #fff; padding: 28px 24px; text-align: center; }

        .figure b {
            display: block;
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.04em;
            color: var(--brand);
        }

        .figure span { display: block; margin-top: 8px; color: var(--muted); font-size: .875rem; }

        /* ---------------------------------------------------------- floor */
        .floor { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }

        .floor__card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .floor__pic { aspect-ratio: 3 / 2; background-size: cover; background-position: center; background-color: var(--ink); }

        .floor__pic--empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,.22);
            font-size: 2rem;
            background:
                radial-gradient(90% 80% at 70% 20%, rgba(227,27,35,.32) 0%, transparent 60%),
                linear-gradient(140deg, #17233A 0%, #0C1626 100%);
        }

        .floor__body { padding: 20px 22px 24px; }
        .floor__body h3 { margin: 0; font-size: 1.0625rem; font-weight: 650; }
        .floor__body p  { margin: 8px 0 0; color: var(--muted); font-size: .9375rem; line-height: 1.6; }

        /* --------------------------------------------------- workstations */
        /* Smaller tiles than the six shop areas above: this is a survey of the
           whole building, so the point is how many there are, not each one. */
        .stations {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr));
            gap: 14px;
        }

        .station {
            margin: 0;
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            background: var(--ink);
        }

        .station img {
            display: block;
            width: 100%;
            aspect-ratio: 3 / 2;
            object-fit: cover;
            transition: transform .3s ease;
        }

        .station:hover img { transform: scale(1.05); }

        .station figcaption {
            position: absolute;
            inset: auto 0 0 0;
            padding: 24px 12px 10px;
            background: linear-gradient(to top, rgba(12, 22, 38, .93), transparent);
            color: #fff;
            font-size: .8125rem;
            font-weight: 600;
            line-height: 1.3;
        }

        /* ---------------------------------------------------------- steps */
        .steps { list-style: none; margin: 0; padding: 0; counter-reset: step; }

        .steps li {
            display: flex;
            gap: 18px;
            padding: 18px 0;
            border-top: 1px solid var(--line);
        }

        .steps li:first-child { border-top: 0; padding-top: 0; }

        .steps li::before {
            counter-increment: step;
            content: counter(step);
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--ink);
            color: #fff;
            font-size: .8125rem;
            font-weight: 700;
        }

        .steps b    { display: block; font-size: 1rem; font-weight: 650; }
        .steps span { display: block; margin-top: 5px; color: var(--muted); font-size: .9375rem; line-height: 1.6; }

        /* --------------------------------------------------------- notice */
        .notice {
            border: 1px solid #F3C7C9;
            background: #FDF6F6;
            border-radius: 14px;
            padding: 26px 28px;
        }

        .notice h3 { margin: 0 0 10px; font-size: 1.0625rem; font-weight: 700; color: #A3131A; }
        .notice p  { margin: 0 0 10px; color: #6B4A4C; font-size: .9375rem; line-height: 1.65; }
        .notice p:last-child { margin-bottom: 0; }
        .notice b  { color: #A3131A; }

        @media (max-width: 1000px) {
            .floor   { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 720px) {
            .profile__hero { padding: 52px 24px; }
            .floor, .figures { grid-template-columns: 1fr; }
        }
    </style>

    {{-- ------------------------------------------------------------ hero --}}
    @php $hero = $this->pic('hero'); @endphp
    <div class="profile__hero">
        <div class="profile__hero-img {{ $hero ? '' : 'profile__hero-img--empty' }}"
             @if ($hero) style="background-image: url('{{ $hero }}')" @endif></div>

        <span class="profile__eyebrow">About the company</span>
        <h1>Custom apparel, made start to finish under one roof.</h1>
        <p>
            You have applied. Here is the place you applied to &mdash; what we make,
            who makes it, and what happens next with your application.
        </p>
    </div>

    {{-- ---------------------------------------------------------- who we are --}}
    <div class="page-card profile__block">
        <div class="profile__head">
            <h2>Who we are</h2>
        </div>
        <p class="profile__lede">
            Imprint Customs PH is a custom printed apparel shop. Jerseys, uniforms,
            shirts and merchandise &mdash; designed, printed, embroidered, cut, sewn,
            pressed and packed in the same building, by the same team.
        </p>
        <p class="profile__lede">
            That matters for the work: because nothing is sent out to a third party,
            everybody here can see the whole thing come together, and a problem is
            solved by walking ten metres rather than sending an email and waiting.
        </p>

        <div class="figures" style="margin-top: 26px; --figure-count: {{ count($this->figures()) }}">
            @foreach ($this->figures() as $figure)
                <div class="figure">
                    <b>{{ $figure['value'] }}</b>
                    <span>{{ $figure['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---------------------------------------------------- inside the shop --}}
    <div class="page-card profile__block">
        <div class="profile__head">
            <h2>Inside the shop</h2>
            <p>The work moves through the building in this order. Most people start in one of these and learn the others.</p>
        </div>

        <div class="floor">
            @foreach ($this->floorAreas() as $area)
                @php $pic = $this->pic($area['pic']); @endphp
                <div class="floor__card">
                    <div class="floor__pic {{ $pic ? '' : 'floor__pic--empty' }}"
                         @if ($pic) style="background-image: url('{{ $pic }}')" @endif>
                        @unless ($pic) <i class="fas fa-camera"></i> @endunless
                    </div>
                    <div class="floor__body">
                        <h3>{{ $area['name'] }}</h3>
                        <p>{{ $area['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---------------------------------------------------- workstations --}}
    @php $stations = $this->workstations(); @endphp
    @if (count($stations) > 0)
    <div class="page-card profile__block">
        <div class="profile__head">
            <h2>Every workstation</h2>
            <p>
                {{ count($stations) }} places in the building where the work actually
                gets done. You would be at one of them.
            </p>
        </div>

        <div class="stations">
            @foreach ($stations as $station)
                <figure class="station">
                    {{-- Lazy, because this is a couple of dozen photographs and
                         they all sit below the fold. --}}
                    <img src="{{ $station['src'] }}" alt="{{ $station['caption'] }}"
                         loading="lazy" decoding="async">
                    <figcaption>{{ $station['caption'] }}</figcaption>
                </figure>
            @endforeach
        </div>
    </div>
    @endif

    {{-- --------------------------------------------------- what happens next --}}
    <div class="page-card profile__block">
        <div class="profile__head">
            <h2>What happens to your application</h2>
            <p>No stage of this happens silently. Every change shows on your dashboard.</p>
        </div>

        <ul class="steps">
            @foreach ($steps as $step)
                <li>
                    <div>
                        <b>{{ $step['name'] }}</b>
                        <span>{{ $step['body'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- ------------------------------------------------------------ notice --}}
    {{-- Recruitment scams using a real company's name are common here, and the
         people applying for entry-level work are the ones targeted. Saying
         plainly what we will never ask for costs us nothing and is the one
         thing that reliably stops it. --}}
    <div class="page-card profile__block">
        <div class="notice">
            <h3><i class="fas fa-triangle-exclamation" style="margin-right:8px"></i>Beware of hiring scams</h3>
            <p>
                People sometimes pose as recruiters for Imprint Customs to collect
                &ldquo;processing fees&rdquo; or personal financial details.
            </p>
            <p>
                <b>We will never ask you to pay anything to apply, to be interviewed, or to be hired</b>,
                and we will never ask for your bank details, card numbers or an
                online banking password. Official email from us comes from an
                <b>&#64;imprintcustoms.ph</b> address.
            </p>
            <p>
                If somebody asks you for money in our name, do not pay. Tell us at
                hr&#64;imprintcustoms.ph and report it to your local authorities.
            </p>
        </div>
    </div>
</div>
