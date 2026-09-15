<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - Imprint Customs PH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-green: #E31B23;
            --dark-green: #17233A;
            --light-green: #FDECEC;
            --forest-green: #0C1626;
            --mint-green: #93A0B4;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #0C1626;
        }
        
        /* Compact Header - Matches Admin Design Exactly */
        .main-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--forest-green) 100%);
            color: white;
            height: 60px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 12px rgba(23, 35, 58, 0.2);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Logo Container - Matches Admin Exactly */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .logo-icon {
            background: transparent;
            padding: 0;
            border-radius: 0;
            color: var(--dark-green);
            font-size: 1.4rem;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .company-name {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: white;
        }
        
        .company-tagline {
            font-size: 0.75rem;
            opacity: 0.9;
            color: var(--mint-green);
        }
        
        /* Desktop Navigation */
        .desktop-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-item {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-item.active {
            background: var(--primary-green);
            color: white;
            box-shadow: 0 2px 8px rgba(227, 27, 35, 0.3);
            animation: gentle-pulse 3s infinite;
        }
        
        @keyframes gentle-pulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(227, 27, 35, 0.3); }
            50% { box-shadow: 0 2px 12px rgba(227, 27, 35, 0.5); }
        }
        
        /* Auth Buttons - Matches Admin Style */
        .btn-auth {
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            white-space: nowrap;
            text-decoration: none;
        }
        
        .btn-login {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-login:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }
        
        .btn-signup {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(227, 27, 35, 0.3);
        }
        
        .btn-signup:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(227, 27, 35, 0.4);
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 8px;
        }
        
        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 999;
            border-radius: 0 0 12px 12px;
        }
        
        .mobile-menu.active {
            transform: translateY(0);
            opacity: 1;
        }
        
        .mobile-nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .mobile-nav-item:hover {
            background: #FDF6F6;
        }
        
        .mobile-nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .mobile-auth-buttons {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }
            
            .desktop-nav {
                display: none;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .company-tagline {
                display: none;
            }
            
            .company-name {
                font-size: 1rem;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu {
                display: none;
            }
        }
    </style>

    @include('partials.theme')
</head>
<body>
    {{-- The HR back office is not linked from anywhere public; it is reached
         at /admin/login by people who already know the address. --}}
    <!-- Compact Header -->
    <header class="main-header">
        <div class="header-content">
            <!-- Logo - Exact match to admin -->
            <a href="{{ route('landing') }}" class="logo-container">
                <div class="logo-icon" style="display: flex; align-items: center; justify-content: center;">
                    @if(file_exists(public_path('imprint-customs.jpg')))
                        <img src="{{ asset('imprint-customs.jpg') }}" alt="Imprint Customs" style="height: 44px; width: 44px; object-fit: contain; background: #fff; border-radius: 50%; padding: 3px;">
                    @else
                        <div style="background: white; color: var(--dark-green); font-weight: bold; padding: 4px 8px; border-radius: 6px;">
                            Imprint Customs
                        </div>
                    @endif
                </div>
                <div class="logo-text">
                    <div class="company-name">Imprint Customs PH</div>
                    <div class="company-tagline">Custom Printed Apparel</div>
                </div>
            </a>

                        <!-- Desktop Navigation -->
            <nav class="desktop-nav">
                <!-- Applicant Button -->
                <a href="{{ route('applicant.login') }}" class="btn-auth" 
                style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
                    <i class="fas fa-user-tie"></i>
                    Applicant
                </a>
                
                <!-- Employee Button -->
                <a href="{{ route('employee.login') }}" class="btn-auth btn-signup">
                    <i class="fas fa-user-plus"></i>
                    Employee
                </a>
            </nav>

            <!-- Mobile Toggle -->
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Menu -->
    <!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <div class="space-y-2">
        <!-- Applicant -->
        <a href="{{ route('applicant.login') }}" class="mobile-nav-item bg-amber-600 text-white">
            <div class="mobile-nav-left">
                <i class="fas fa-user-tie"></i>
                <span>Applicant</span>
            </div>
            <i class="fas fa-chevron-right"></i>
        </a>
        
        <!-- Mobile Auth Buttons -->
        <div class="mobile-auth-buttons">
            
            <!-- Employee -->
            <a href="{{ route('employee.login') }}" class="mobile-nav-item bg-green-600 text-white">
                <div class="mobile-nav-left">
                    <i class="fas fa-user-plus"></i>
                    <span>Employee</span>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
</div>

    <!-- Main Content Area -->
    <main style="padding-top: 60px;">
        {{ $slot }}
    </main>

    <script>
        // Wrap everything in an IIFE to avoid redeclaration
        (function() {
            // Initialize only if not already initialized
            if (window.imprintHeaderInitialized) {
                return;
            }
            
            window.imprintHeaderInitialized = true;
            
            // Mobile menu toggle
            const mobileToggle = document.getElementById('mobileToggle');
            const mobileMenu = document.getElementById('mobileMenu');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    mobileMenu.classList.toggle('active');
                });
            }
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', (e) => {
                if (mobileToggle && mobileMenu && !mobileToggle.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.remove('active');
                }
            });
            
            // Close mobile menu when clicking a link
            if (mobileMenu) {
                document.querySelectorAll('#mobileMenu a, #mobileMenu button').forEach(element => {
                    element.addEventListener('click', () => {
                        mobileMenu.classList.remove('active');
                    });
                });
            }
            
            // Update active nav items based on current URL
            document.addEventListener('DOMContentLoaded', () => {
                const currentPath = window.location.pathname;
                const navItems = document.querySelectorAll('.nav-item, .mobile-nav-item');
                
                navItems.forEach(item => {
                    const href = item.getAttribute('href');
                    if (href && currentPath.includes(href.replace(/\/$/, '')) && href !== '/') {
                        item.classList.add('active');
                    }
                });
            });
        })();
    </script>
</body>
</html>