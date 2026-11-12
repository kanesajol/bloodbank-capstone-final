<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRH Blood Bank — Sign Up</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/crh-logo.webp') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/global.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login-page.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login-form.css') }}">
</head>
<body>
    <div class="login-page login-page--signup">
        <div class="login-page__container">
            <div class="login-page__branding-spacer"></div>
            <div class="login-page__form-container">
                <div class="login-card login-card--signup">
                    <img src="{{ asset('images/crh-logo.webp') }}" alt="Caraga Regional Hospital Logo" class="logo">
                    <h2 class="login-title">Staff Sign Up</h2>

                    <form method="POST" action="{{ route('signup.store') }}">
                        @csrf
                        <div class="field">
                            <label for="fullName">Full Name</label>
                            <input id="fullName" name="full_name" value="{{ old('full_name') }}" type="text" required autocomplete="name">
                            @error('full_name')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="username">Username</label>
                            <input id="username" name="username" value="{{ old('username') }}" type="text" required autocomplete="username">
                            @error('username')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="role">Role / Position</label>
                            <input id="role" name="role" value="{{ old('role') }}" type="text" required>
                            @error('role')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="contact">Contact Number</label>
                            <input id="contact" name="contact" value="{{ old('contact') }}" type="text" required autocomplete="tel">
                            @error('contact')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label for="password">Password</label>
                            <div class="password-input">
                                <input id="password" name="password" type="password" required autocomplete="new-password">
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Show password" aria-pressed="false">Show</button>
                            </div>
                            @error('password')<p class="error">{{ $message }}</p>@enderror
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
                    <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
</body>
</html>
