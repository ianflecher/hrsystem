<!DOCTYPE html>
{{-- No class="dark": the portal's own CSS is light only. The appearance is
     pinned in partials/head.blade.php, where the reason is written down. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
<a class="hr-skip-link" href="#employee-content">Skip to content</a>

<div class="hr-shell" data-portal-shell>
    <aside class="hr-sidebar" id="empSidebar" aria-label="Employee navigation">
        <div class="hr-sidebar__header">
        <a href="{{ route('employee.dashboard') }}" class="hr-sidebar__brand">
            @if(file_exists(public_path('imprint-customs.jpg')))
                <img src="{{ asset('imprint-customs.jpg') }}" alt="Imprint Customs">
            @endif
            <span>
                <span class="hr-sidebar__brand-name">Imprint Customs</span><br>
                <span class="hr-sidebar__brand-sub">Employee Portal</span>
            </span>
        </a>
        <button type="button" class="hr-sidebar__close" data-sidebar-close aria-label="Close navigation"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>

        @php
            $moduleLabels = \App\Http\Controllers\PeopleController::MODULES;
            $navigationGroups = [
                'Overview' => [
                    ['route' => 'employee.dashboard', 'label' => 'Dashboard', 'icon' => 'gauge-high'],
                    ['route' => 'employee.operations.self-service', 'label' => 'Self-Service', 'icon' => 'user-gear'],
                    ['route' => 'people.employee', 'module' => 'announcements', 'icon' => 'bullhorn'],
                ],
                'My employment' => [
                    ['route' => 'employee.interviews', 'label' => 'My interviews', 'icon' => 'user-check'],
                    ['route' => 'people.employee', 'module' => 'documents', 'icon' => 'folder-open'],
                    ['route' => 'people.employee', 'module' => 'checklists', 'icon' => 'list-check'],
                    ['route' => 'people.employee', 'module' => 'reviews', 'icon' => 'star'],
                ],
                'Time & attendance' => [
                    ['route' => 'employee.attendance', 'label' => 'Attendance', 'icon' => 'clock'],
                    ['route' => 'employee.leave', 'label' => 'Leave', 'icon' => 'umbrella-beach'],
                    ['route' => 'people.employee', 'module' => 'overtime', 'icon' => 'stopwatch'],
                    ['route' => 'people.employee', 'module' => 'shifts', 'icon' => 'calendar-days'],
                ],
                'My pay' => [
                    ['route' => 'employee.payroll', 'label' => 'Payroll', 'icon' => 'money-bill-wave'],
                    ['route' => 'people.employee', 'module' => 'loans', 'icon' => 'wallet'],
                ],
            ];
        @endphp
        <nav aria-label="Employee services">
            @foreach($navigationGroups as $group => $items)
                <div class="hr-sidebar__group" role="group" aria-labelledby="emp-nav-group-{{ $loop->index }}">
                    <p class="hr-sidebar__label" id="emp-nav-group-{{ $loop->index }}">{{ $group }}</p>
                    @foreach($items as $item)
                        @php
                            $active = request()->routeIs($item['route']) && (!isset($item['module']) || request()->route('module') === $item['module']);
                            $label = $item['label'] ?? $moduleLabels[$item['module']];
                        @endphp
                        <a href="{{ route($item['route'], isset($item['module']) ? ['module' => $item['module']] : []) }}"
                           class="nav-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
                            <i class="fas fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="hr-sidebar__foot">
            <a href="{{ route('account.edit') }}" class="hr-sidebar__user" title="My account">
                @if(auth()->user()?->profile_photo_path)
                    <img class="hr-sidebar__avatar" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->profile_photo_path) }}" alt="">
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

    <div class="hr-backdrop" id="empBackdrop" aria-hidden="true"></div>

    <div class="hr-main">
        <div class="hr-topbar">
            <button type="button" class="hr-topbar__toggle" data-sidebar-toggle aria-controls="empSidebar" aria-expanded="false" aria-label="Open navigation">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
            <span>Imprint Customs · Employee</span>
        </div>

        <div class="hr-content" id="employee-content" tabindex="-1">
            {{ $slot }}
        </div>
    </div>
</div>



</body>
</html>
