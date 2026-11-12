<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRH Blood Bank')</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/crh-logo.webp') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/global.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layout-shell.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/module-page.css') }}">
    <link rel="stylesheet" href="{{ asset('css/behavior.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-responsive.css') }}">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar" id="sidebar">
            <script>
                try {
                    if (window.matchMedia('(min-width: 901px)').matches && localStorage.getItem('crh-sidebar') === 'collapsed') {
                        document.getElementById('sidebar').classList.add('sidebar--collapsed');
                    }
                } catch (e) {}
            </script>
            <div class="sidebar__brand">
                <img src="{{ asset('images/crh-logo.webp') }}" alt="Caraga Regional Hospital Logo" class="sidebar__logo">
                <div class="sidebar__brand-text" data-sidebar-label>
                    <span class="sidebar__title">CRH Blood Bank</span>
                    <span class="sidebar__subtitle">Staff Dashboard</span>
                </div>
            </div>

            <nav class="sidebar__nav">
                <a href="{{ route('blood-collection') }}" class="sidebar__item {{ request()->routeIs('blood-collection') ? 'sidebar__item--active' : '' }}" title="Blood Collection">
                    <span class="sidebar__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3c-2 4-6 6-6 10a6 6 0 1 0 12 0c0-4-4-6-6-10z"/></svg></span>
                    <span class="sidebar__label" data-sidebar-label>Blood Collection</span>
                </a>

                <a href="{{ route('blood-consumption') }}" class="sidebar__item {{ request()->routeIs('blood-consumption') ? 'sidebar__item--active' : '' }}" title="Blood Consumption">
                    <span class="sidebar__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 12h6M12 9v6"/></svg></span>
                    <span class="sidebar__label" data-sidebar-label>Blood Consumption</span>
                </a>

                <a href="{{ route('reports') }}" class="sidebar__item {{ request()->routeIs('reports') ? 'sidebar__item--active' : '' }}" title="Reports">
                    <span class="sidebar__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V4M4 20h16M8 16V10M12 16V7M16 16v-4"/></svg></span>
                    <span class="sidebar__label" data-sidebar-label>Reports</span>
                </a>

                <a href="{{ route('staff-accounts') }}" class="sidebar__item {{ request()->routeIs('staff-accounts') ? 'sidebar__item--active' : '' }}" title="Staff Accounts">
                    <span class="sidebar__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                    <span class="sidebar__label" data-sidebar-label>Staff Accounts</span>
                </a>

                <a href="{{ route('staff-log') }}" class="sidebar__item {{ request()->routeIs('staff-log') ? 'sidebar__item--active' : '' }}" title="Staff Log">
                    <span class="sidebar__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></span>
                    <span class="sidebar__label" data-sidebar-label>Staff Log</span>
                </a>
            </nav>
        </aside>

        <div class="dashboard__content">
            <header class="header">
                <button type="button" class="header__icon-btn" aria-label="Toggle sidebar" id="sidebar-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="header__actions">
                    <div class="header__user" id="user-menu-wrap">
                        <button type="button" class="header__user-btn" id="user-menu-btn" aria-expanded="false" aria-haspopup="true">
                            <span class="header__avatar">{{ auth()->user()->avatar_initials }}</span>
                            <span class="header__username">{{ auth()->user()->name }}</span>
                            <svg class="header__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>

                        <div class="header__dropdown" id="user-menu" hidden>
                            <a href="{{ route('staff-profile') }}" class="header__dropdown-item">Staff Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="header__dropdown-item header__dropdown-item--danger">Sign Out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dashboard__main">
                @if (session('success'))
                    <p class="flash-banner flash-banner--success">{{ session('success') }}</p>
                @endif
                @if (session('error'))
                    <p class="flash-banner flash-banner--error">{{ session('error') }}</p>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <div id="delete-confirm-modal" class="modal-overlay" hidden>
        <div class="modal-card" role="dialog" aria-labelledby="deleteConfirmTitle">
            <div class="modal-header">
                <h3 id="deleteConfirmTitle" class="modal-title">Delete Record</h3>
                <button type="button" class="modal-close" data-modal-close="delete-confirm-modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <p class="delete-confirm-message">Are you sure you want to delete this record? This action cannot be undone.</p>
            <p class="delete-confirm-name" id="delete-confirm-name"></p>
            <form id="delete-confirm-form" method="POST" action="">
                @csrf
                @method('DELETE')
                <input type="hidden" name="return_to" value="">
                <input type="hidden" name="mode" value="">
                <input type="hidden" name="return_location" value="">
                <input type="hidden" name="return_year" value="">
                <div class="form-actions">
                    <button type="button" class="btn-cancel" data-modal-close="delete-confirm-modal">Cancel</button>
                    <button type="submit" class="btn-submit">Delete</button>
                </div>
            </form>
        </div>
    </div>
    <script src="{{ asset('js/ui.js') }}?v=mobile-drawer"></script>
    <script>
        (function () {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const url = @json(route('staff.presence'));
            const userId = @json(auth()->id());
            const ping = () => {
                if (!token) return;
                fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    keepalive: true,
                }).catch(() => {});
            };
            ping();
            setInterval(ping, 30000);

            const prefix = 'crh-draft:' + userId + ':';
            document.querySelectorAll('form').forEach((form) => {
                const method = (form.getAttribute('method') || '').toLowerCase();
                if (method !== 'post') return;
                if (form.querySelector('[name="_method"]')) return;
                const action = form.getAttribute('action') || '';
                if (action.includes('/logout') || action.includes('/presence')) return;
                const key = prefix + action;
                try {
                    const saved = JSON.parse(localStorage.getItem(key) || 'null');
                    if (saved && typeof saved === 'object') {
                        Object.entries(saved).forEach(([name, value]) => {
                            const field = form.elements.namedItem(name);
                            if (!field || field.type === 'hidden' || field.type === 'password' || field.type === 'file') return;
                            if (field.value) return;
                            field.value = value;
                        });
                    }
                } catch (error) {}
                const save = () => {
                    const data = {};
                    new FormData(form).forEach((value, name) => {
                        if (typeof value !== 'string' || name === '_token' || name === 'password') return;
                        data[name] = value;
                    });
                    try { localStorage.setItem(key, JSON.stringify(data)); } catch (error) {}
                };
                form.addEventListener('input', save);
                form.addEventListener('change', save);
                form.addEventListener('submit', () => {
                    try { localStorage.removeItem(key); } catch (error) {}
                });
            });
        })();
    </script>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
    @stack('scripts')
</body>
</html>
