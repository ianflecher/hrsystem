{{-- The shape every error page takes.

     Deliberately plain and self-contained: an error page is sometimes the
     thing being served when something else is broken, so it depends on no
     build step, no CDN and no database. If it needs Tailwind to be compiled
     or the network to be up, it will fail exactly when it is needed.

     The way back is chosen for whoever is reading it. A stranger who mistypes
     a URL wants the careers page; somebody signed in wants their own portal,
     not to be dropped at a job advert. --}}
@php
    $user = auth()->user();

    [$backLabel, $backUrl] = match (true) {
        ! $user                                  => ['Careers', route('landing')],
        in_array($user->role, ['admin', 'hr'])   => ['HR back office', route('hr.home')],
        default                                  => ['My portal', route('employee.dashboard')],
    };

    // An applicant has an employee role but no employee record, so the staff
    // dashboard would only turn them away again.
    if ($user && $user->role === 'employee'
        && ! \Illuminate\Support\Facades\DB::table('employees')->where('user_id', $user->user_id)->exists()) {
        [$backLabel, $backUrl] = ['My application', route('applicant.index')];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &mdash; Imprint Customs</title>

    <style>
        :root { --ink: #0C1626; --red: #E31B23; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #E6EBF2;
            background:
                radial-gradient(1100px 500px at 15% -10%, rgba(227, 27, 35, .18), transparent 60%),
                linear-gradient(160deg, #0C1626 0%, #0a1220 100%);
        }

        .card {
            width: 100%;
            max-width: 32rem;
            text-align: center;
        }

        .mark {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            padding: .4rem .85rem;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 999px;
            background: rgba(255, 255, 255, .05);
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #A9B4C6;
        }

        .code {
            margin: 22px 0 0;
            font-size: clamp(4rem, 18vw, 7rem);
            font-weight: 800;
            line-height: .9;
            letter-spacing: -.04em;
            color: #fff;
        }

        h1 {
            margin: 14px 0 0;
            font-size: clamp(1.25rem, 4.5vw, 1.6rem);
            font-weight: 700;
            color: #fff;
        }

        p {
            margin: 12px auto 0;
            max-width: 26rem;
            font-size: .975rem;
            line-height: 1.6;
            color: #A9B4C6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 26px;
        }

        a.button, button.button {
            padding: .7rem 1.2rem;
            border: 1px solid transparent;
            border-radius: 10px;
            font: inherit;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .primary { background: var(--red); color: #fff; }
        .quiet {
            background: rgba(255, 255, 255, .07);
            border-color: rgba(255, 255, 255, .16);
            color: #E6EBF2;
        }

        .foot {
            margin-top: 28px;
            font-size: .78rem;
            color: #6B7A90;
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="mark">Imprint Customs &middot; HRIS</span>

        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>

        @yield('body')

        <div class="actions">
            <a class="button primary" href="{{ $backUrl }}">{{ $backLabel }}</a>
            <button class="button quiet" type="button" onclick="history.back()">Go back</button>
        </div>

        <p class="foot">@yield('foot')</p>
    </main>
</body>
</html>
