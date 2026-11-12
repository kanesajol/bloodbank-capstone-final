<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $panel === 'signup' ? 'CRH Blood Bank — Sign Up' : 'CRH Blood Bank — Login' }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/crh-logo.webp') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/global.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login-page.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login-form.css') }}">
</head>
<body>
    <div class="login-page">
        <div class="login-corner-actions">
            <button type="button" class="login-corner-btn" id="guidelines-open" aria-label="System Guidelines &amp; Support" aria-controls="guidelines-modal" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M9.5 9a2.5 2.5 0 1 1 3.2 2.4c-.7.3-1.2.9-1.2 1.6V14"></path>
                    <path d="M12 17h.01"></path>
                </svg>
                <span class="login-corner-tip" role="tooltip">System Guidelines &amp; Support</span>
            </button>
            <a class="login-corner-btn" href="https://crh.doh.gov.ph/" target="_blank" rel="noopener noreferrer" aria-label="Official CRH website">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M3 12h18"></path>
                    <path d="M12 3c2.5 2.8 3.8 5.8 3.8 9s-1.3 6.2-3.8 9c-2.5-2.8-3.8-5.8-3.8-9S9.5 5.8 12 3z"></path>
                </svg>
            </a>
        </div>
        <div class="login-page__container">
            <div class="login-page__branding-spacer"></div>
            <div class="login-page__form-container">
                <div class="login-card {{ $panel === 'signup' ? 'is-signup' : '' }}" id="auth-card">
                    <img src="{{ asset('images/crh-logo.webp') }}" alt="Caraga Regional Hospital Logo" class="logo">

                    <div class="auth-panel {{ $panel === 'signup' ? 'is-hidden' : '' }}" data-panel="login">
                        <div class="auth-panel__body">
                            <h2 class="login-title">Staff Login</h2>
                            <form method="POST" action="{{ route('login.attempt') }}">
                                @csrf
                                <div class="field">
                                    <label for="login-username">Username</label>
                                    <input id="login-username" name="username" value="{{ $panel === 'login' ? old('username') : '' }}" type="text" required autocomplete="username">
                                </div>
                                <div class="field">
                                    <label for="login-password">Password</label>
                                    <div class="password-input">
                                        <input id="login-password" name="password" type="password" required autocomplete="current-password">
                                        <button type="button" class="password-toggle" data-password-toggle aria-controls="login-password" aria-label="Show password" aria-pressed="false">Show</button>
                                    </div>
                                </div>
                                @if ($panel === 'login')
                                    @error('username')
                                        <p class="error">{{ $message }}</p>
                                    @enderror
                                @endif
                                <button type="submit" class="btn-signin">Sign In</button>
                            </form>
                            <p class="auth-switch">No account yet? <a href="{{ route('signup') }}" data-auth-switch="signup">Sign up</a></p>
                        </div>
                    </div>

                    <div class="auth-panel {{ $panel === 'signup' ? '' : 'is-hidden' }}" data-panel="signup">
                        <div class="auth-panel__body">
                            <h2 class="login-title">Staff Sign Up</h2>
                            <form method="POST" action="{{ route('signup.store') }}">
                                @csrf
                                <div class="field">
                                    <label for="fullName">Full Name</label>
                                    <input id="fullName" name="full_name" value="{{ old('full_name') }}" type="text" required autocomplete="name">
                                    @if ($panel === 'signup')
                                        @error('full_name')<p class="error">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                                <div class="field">
                                    <label for="signup-username">Username</label>
                                    <input id="signup-username" name="username" value="{{ old('username') }}" type="text" required autocomplete="username">
                                    @if ($panel === 'signup')
                                        @error('username')<p class="error">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                                <div class="field">
                                    <label for="role">Role / Position</label>
                                    <input id="role" name="role" value="{{ old('role') }}" type="text" required>
                                    @if ($panel === 'signup')
                                        @error('role')<p class="error">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                                <div class="field">
                                    <label for="contact">Contact Number</label>
                                    <input id="contact" name="contact" value="{{ old('contact') }}" type="text" required autocomplete="tel">
                                    @if ($panel === 'signup')
                                        @error('contact')<p class="error">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                                <div class="field">
                                    <label for="signup-password">Password</label>
                                    <div class="password-input">
                                        <input id="signup-password" name="password" type="password" required autocomplete="new-password">
                                        <button type="button" class="password-toggle" data-password-toggle aria-controls="signup-password" aria-label="Show password" aria-pressed="false">Show</button>
                                    </div>
                                    @if ($panel === 'signup')
                                        @error('password')<p class="error">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                                <div class="field">
                                    <label for="passwordConfirmation">Confirm Password</label>
                                    <div class="password-input">
                                        <input id="passwordConfirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                                        <button type="button" class="password-toggle" data-password-toggle aria-controls="passwordConfirmation" aria-label="Show password" aria-pressed="false">Show</button>
                                    </div>
                                </div>
                                <button type="submit" class="btn-signin">Sign Up</button>
                            </form>
                            <p class="auth-switch">Already have an account? <a href="{{ route('login') }}" data-auth-switch="login">Sign in</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="guidelines-modal" class="guidelines-modal" hidden>
        <div class="guidelines-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="guidelines-title">
            <button type="button" class="guidelines-modal__close" data-guidelines-close aria-label="Close">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18"></path>
                </svg>
            </button>
            <h2 id="guidelines-title">CRH Blood Bank — System Guidelines</h2>
            <ol class="guidelines-modal__list">
                <li>
                    <strong>Authorized Access:</strong>
                    This system is strictly for authorized CRH Blood Bank staff.
                </li>
                <li>
                    <strong>Sign Up / Account Access:</strong>
                    If you don't have an account yet, click 'Sign up' on the login screen or request access from the system administrator.
                </li>
                <li>
                    <strong>Blood Bank Records:</strong>
                    After you sign in, use Blood Collection to record donors and donations, Blood Consumption to record units given, and Reports to review those records. Enter each name, MBD or WI date, and address completely.
                </li>
            </ol>
        </div>
    </div>
    <script>
        const authCard = document.getElementById('auth-card');
        const authPanels = [...document.querySelectorAll('.auth-panel')];

        const showAuthPanel = (name) => {
            authCard.classList.toggle('is-signup', name === 'signup');
            authPanels.forEach((panel) => {
                panel.classList.toggle('is-hidden', panel.dataset.panel !== name);
            });
            document.title = name === 'signup' ? 'CRH Blood Bank — Sign Up' : 'CRH Blood Bank — Login';
        };

        document.querySelectorAll('[data-auth-switch]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const name = link.dataset.authSwitch;
                showAuthPanel(name);
                history.pushState({ panel: name }, '', link.href);
            });
        });

        window.addEventListener('popstate', () => {
            showAuthPanel(location.pathname.includes('signup') ? 'signup' : 'login');
        });

        const guidelinesModal = document.getElementById('guidelines-modal');
        const guidelinesOpen = document.getElementById('guidelines-open');

        const openGuidelines = () => {
            guidelinesModal.hidden = false;
            guidelinesOpen.setAttribute('aria-expanded', 'true');
            guidelinesModal.querySelector('[data-guidelines-close]').focus();
        };

        const closeGuidelines = () => {
            if (guidelinesModal.hidden) {
                return;
            }
            guidelinesModal.hidden = true;
            guidelinesOpen.setAttribute('aria-expanded', 'false');
            guidelinesOpen.focus();
        };

        guidelinesOpen.addEventListener('click', openGuidelines);
        guidelinesModal.addEventListener('click', (event) => {
            if (event.target === guidelinesModal) {
                closeGuidelines();
            }
        });
        guidelinesModal.querySelectorAll('[data-guidelines-close]').forEach((button) => {
            button.addEventListener('click', closeGuidelines);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeGuidelines();
            }
        });
    </script>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
</body>
</html>
