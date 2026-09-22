<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Imprint Customs Careers') - Imprint Customs PH</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom green theme for career portal -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        career: {
                            50: '#FEF2F2',
                            100: '#FDECEC',
                            200: '#FBD5D7',
                            300: '#F7AEB2',
                            400: '#F05A60',
                            500: '#E31B23',
                            600: '#C8161D',
                            700: '#A61217',
                            800: '#8E1016',
                            900: '#6E0C11',
                        },
                        accent: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            200: '#fde68a',
                            300: '#fcd34d',
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --career-green: #E31B23;
            --career-dark-green: #17233A;
            --career-light-green: #FDECEC;
            --career-amber: #f59e0b;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #FDF6F6 50%);
            min-height: 100vh;
        }
        
        /* Header */
        .applicant-header {
            background: linear-gradient(135deg, var(--career-dark-green) 0%, #0C1626 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 12px rgba(227, 27, 35, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }
        
        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .company-name {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: white;
        }
        
        .company-tagline {
            font-size: 0.85rem;
            opacity: 0.9;
            color: #93A0B4;
        }
        
        /* Navigation */
        .applicant-nav {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .nav-link.active {
            background: var(--career-green);
            color: white;
            box-shadow: 0 2px 8px rgba(227, 27, 35, 0.3);
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: white;
            border-radius: 2px;
        }
        
        /* User Actions */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }
        
        /* Main Content */
        .applicant-content {
            max-width: 1400px;
            margin: 0.75rem auto 2rem;
            padding: 0 2rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #FDF6F6;
            border-left: 4px solid var(--career-green);
            color: #0C1626;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-left: 4px solid var(--career-amber);
            color: #92400e;
        }
        
        .alert-info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        
        /* Page Cards */
        .page-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--career-green);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--career-dark-green) 0%, var(--career-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-reviewed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-shortlisted {
            background: #FDECEC;
            color: #0C1626;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-hired {
            background: #FDF6F6;
            color: #17233A;
            border: 2px solid #93A0B4;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .applicant-header {
                padding: 0.75rem 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .applicant-nav {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .nav-link {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .user-actions {
                width: 100%;
                justify-content: center;
            }
            
            .applicant-content {
                padding: 0 1rem;
            }
            
            .page-card {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .company-name {
                font-size: 1.2rem;
            }
            
            .company-tagline {
                display: none;
            }
            
            .applicant-nav {
                gap: 0.25rem;
            }
            
            .nav-link {
                padding: 0.35rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .user-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .logout-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>

    @include('partials.theme')
</head>
<body class="bg-gray-50">

<!-- Main Header -->
<header class="applicant-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('applicant.index') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('imprint-customs.jpg')))
                    <img src="{{ asset('imprint-customs.jpg') }}" alt="Imprint Customs" style="height: 44px; width: 44px; object-fit: contain; background: #fff; border-radius: 50%; padding: 3px;">
                @else
                    <div style="background: white; color: var(--career-dark-green); font-weight: bold; padding: 8px 12px; border-radius: 8px; font-size: 1.2rem;">
                        Imprint Customs
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Imprint Customs PH</div>
                <div class="company-tagline">Career Portal</div>
            </div>
        </a>

        <!-- Navigation -->
        @auth
            <nav class="applicant-nav">
                <a href="{{ route('applicant.index') }}"
                   class="nav-link {{ request()->routeIs('applicant.index') ? 'active' : '' }}">
                    <i class="fas fa-file-lines mr-2"></i>My application
                </a>
                <a href="{{ route('applicant.profile') }}"
                   class="nav-link {{ request()->routeIs('applicant.profile') ? 'active' : '' }}">
                    <i class="fas fa-id-card mr-2"></i>My details
                </a>
                <a href="{{ route('applicant.inside') }}"
                   class="nav-link {{ request()->routeIs('applicant.inside') ? 'active' : '' }}">
                    <i class="fas fa-building mr-2"></i>About the company
                </a>
            </nav>
        @endauth

        <!-- User Actions -->
        <div class="user-actions">
            @auth
                <div class="user-badge">
                    <i class="fas fa-user-circle text-career-200"></i>
                    <span>{{ Auth::user()->full_name ?? Auth::user()->name ?? 'Applicant' }}</span>
                </div>
                <form method="POST" action="{{ route('applicant.logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </button>
                </form>
            @else
                <a href="{{ route('applicant.login') }}" class="nav-link">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </a>
                <a href="{{ route('applicant.login') }}" class="nav-link" style="background: var(--career-amber);">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </a>
            @endauth
        </div>
    </div>
</header>

<!-- Main Content Area -->
<main class="applicant-content">
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
        </div>
    @endif
    
    @if(session('warning'))
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            {{ session('warning') }}
        </div>
    @endif
    
    @if(session('info'))
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            {{ session('info') }}
        </div>
    @endif
    
    <!-- Page Content -->
    {{ $slot }}
</main>

<!-- JavaScript -->
<script>
    // Highlight active navigation link
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPath || (href !== '/' && currentPath.includes(href.replace(/\/$/, '')))) {
                link.classList.add('active');
            }
        });
        
        // Mobile menu toggle (if needed in future)
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>

@stack('scripts')
</body>
</html>