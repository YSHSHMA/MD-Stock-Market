<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07120f">
    <title>Create account · {{ config('app.name', 'Marketboard') }}</title>
    <link rel="stylesheet" href="{{ asset('css/trading.css') }}">
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-story">
            <a class="brand" href="{{ route('register') }}"><span class="brand-mark">M</span><span>market<span class="brand-light">board</span></span></a>
            <div class="auth-story-copy">
                <span class="eyebrow"><span class="live-dot"></span> A BETTER VIEW OF YOUR MONEY</span>
                <h1>Your next<br>chapter <span>starts here.</span></h1>
                <p>Build a thoughtful portfolio, log your trades, and follow your progress with a dashboard made for you.</p>
                <div class="story-stat"><span class="story-stat-icon">◎</span><span>Your data, your decisions<br><strong>Start with a clear view</strong></span></div>
            </div>
            <span class="auth-footnote">Investing involves risk. This app records portfolio activity; it does not place orders with a broker.</span>
        </section>
        <section class="auth-panel">
            <div class="auth-form-wrap">
                <span class="eyebrow">GET STARTED</span>
                <h2>Create your account</h2>
                <p class="auth-muted">It only takes a moment to set up your portfolio.</p>
                <form method="POST" action="{{ route('register.store') }}" class="auth-form">
                    @csrf
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus placeholder="Alex Morgan">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@example.com">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="At least 8 characters">
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Enter your password again">
                    <button class="button button-primary auth-submit" type="submit">Create account <span>→</span></button>
                </form>
                <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
</body>
</html>
