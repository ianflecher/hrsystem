<!DOCTYPE html>
{{-- No class="dark": the portal's own CSS is light only. The appearance is
     pinned in partials/head.blade.php, where the reason is written down. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            // 'media' by default, which let the computer's dark mode turn every
            // card dark on a page that is light either way.
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: '#E31B23',
                        ink: '#17233A',
                    },
                },
            },
        };
    </script>

    <style>
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F4F6F9;
            color: #17202E;
        }

        /* The shell itself - sidebar, main column, mobile topbar - lives in
           partials/theme.blade.php and is shared with the HR back office, so
           both halves of the product are recognisably the same thing. */
        .hr-content { padding: 0; }
    </style>

    @include('partials.theme')
</head>
<body>

<div class="hr-shell">
    <aside class="hr-sidebar" id="empSidebar">
        <a href="{{ route('employee.dashboard') }}" class="hr-sidebar__brand">
            @if(file_exists(public_path('imprint-customs.jpg')))
                <img src="{{ asset('imprint-customs.jpg') }}" alt="Imprint Customs">
            @endif
            <span>
                <span class="hr-sidebar__brand-name">Imprint Customs</span><br>
                <span class="hr-sidebar__brand-sub">Employee Portal</span>
            </span>
        </a>

        <div class="hr-sidebar__label">My work</div>

        <nav>
            <a href="{{ route('employee.dashboard') }}" class="nav-link {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
                <i class="fas fa-gauge-high"></i>Dashboard
            </a>
            <a href="{{ route('employee.attendance') }}" class="nav-link {{ request()->routeIs('employee.attendance*') ? 'active' : '' }}">
                <i class="fas fa-clock"></i>Attendance
            </a>
            <a href="{{ route('employee.payroll') }}" class="nav-link {{ request()->routeIs('employee.payroll*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave"></i>Payroll
            </a>
            <a href="{{ route('employee.leave') }}" class="nav-link {{ request()->routeIs('employee.leave*') ? 'active' : '' }}">
                <i class="fas fa-umbrella-beach"></i>Leave
            </a>

            @foreach(\App\Http\Controllers\PeopleController::MODULES as $key => $label)
                <a href="{{ route('people.employee', $key) }}"
                   class="nav-link {{ request()->routeIs('people.employee') && request()->route('module') === $key ? 'active' : '' }}">
                    <i class="fas fa-{{ ['documents' => 'folder-open', 'overtime' => 'clock', 'shifts' => 'calendar-days', 'checklists' => 'list-check', 'reviews' => 'star', 'loans' => 'wallet'][$key] ?? 'circle' }}"></i>{{ $label }}
                </a>
            @endforeach

            {{-- Under its own heading: the list above is the work, this is the
                 one entry that is about the person doing it. --}}
            <div class="hr-sidebar__label">You</div>

            <a href="{{ route('account.edit') }}" class="nav-link {{ request()->routeIs('account.edit') ? 'active' : '' }}">
                <i class="fas fa-id-card"></i>My account
            </a>
        </nav>

        <div class="hr-sidebar__foot">
            <a href="{{ route('account.edit') }}" class="hr-sidebar__user">
                @if(auth()->user()?->profile_photo)
                    <img class="hr-sidebar__avatar" src="{{ Storage::url(auth()->user()->profile_photo) }}" alt="">
                @else
                    <i class="fas fa-user"></i>
                @endif
                {{ auth()->user()?->full_name ?? 'Employee' }}
            </a>

            <form method="POST" action="{{ route('employee.logout') }}">
                @csrf
                <button type="submit" class="hr-sidebar__logout">
                    <i class="fas fa-right-from-bracket"></i> Logout
                </button>
            </form>
        </div>
    </aside>

    <div class="hr-backdrop" id="empBackdrop" onclick="toggleEmpSidebar()"></div>

    <div class="hr-main">
        <div class="hr-topbar">
            <button class="hr-topbar__toggle" onclick="toggleEmpSidebar()" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
            <span>Imprint Customs · Employee</span>
        </div>

        <div class="hr-content">
            {{ $slot }}
        </div>
    </div>
</div>

<script>
    function toggleEmpSidebar() {
        document.getElementById('empSidebar').classList.toggle('open');
        document.getElementById('empBackdrop').classList.toggle('show');
    }
</script>

</body>
</html>
