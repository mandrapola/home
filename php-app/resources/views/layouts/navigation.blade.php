<nav class="navbar navbar-expand-lg theme-navbar">
    <style>
        .theme-navbar {
            padding-top: 10px;
            padding-bottom: 10px;
        }
        .theme-navbar .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .theme-navbar .nav-link {
            font-size: 14px;
            font-weight: 500;
            border-radius: 999px;
            padding: 7px 12px !important;
        }
        .theme-navbar .nav-link.active {
            background: #eff5ff;
        }
        .theme-navbar .user-email {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 5px 10px;
            background: var(--chip-bg);
        }
    </style>
    <div class="container">
        <a class="navbar-brand" href="{{ route('dashboard') }}">AiDvor®</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('scenes') ? 'active' : '' }}" href="{{ route('scenes') }}">{{ __('Scenes') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('user.plans.*') ? 'active' : '' }}" href="{{ route('user.plans.index') }}">{{ __('Plans') }}</a>
                </li>
            </ul>
            <div class="d-lg-none mb-2">
                @include('layouts.theme-switcher', ['id' => 'nav_theme_switcher_mobile'])
            </div>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item d-none d-lg-flex align-items-center">
                    @include('layouts.theme-switcher', ['compact' => true, 'id' => 'nav_theme_switcher'])
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
                </li>
                <li class="nav-item text-muted small d-none d-lg-block user-email">{{ Auth::user()->email }}</li>
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Log Out') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
