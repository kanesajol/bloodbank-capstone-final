<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRH Blood Bank — Login</title>
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
        <div class="login-page__container">
            <div class="login-page__branding-spacer"></div>
            <div class="login-page__form-container">
                <div class="login-card">
                    <img src="{{ asset('images/crh-logo.webp') }}" alt="Caraga Regional Hospital Logo" class="logo">
                    <h2 class="login-title">Staff Login</h2>

                    <form method="POST" action="{{ route('login.attempt') }}">
                        @csrf
                        <div class="field">
                            <label for="username">Username</label>
                            <input id="username" name="username" value="{{ old('username') }}" type="text" required autocomplete="username">
                        </div>
                        <div class="field">
                            <label for="password">Password</label>
                            <div class="password-input">
                                <input id="password" name="password" type="password" required autocomplete="current-password">
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Show password" aria-pressed="false">Show</button>
                            </div>
                        </div>
                        @error('username')
                            <p class="error">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="btn-signin">Sign In</button>
                    </form>
                    <p class="auth-switch">No account yet? <a href="{{ route('signup') }}">Sign up</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
</body>
</html>
