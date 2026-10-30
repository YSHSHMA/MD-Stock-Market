<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07120f">
    <title>Sign in · {{ config('app.name', 'Marketboard') }}</title>
    <link rel="stylesheet" href="{{ asset('css/trading.css') }}">
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-story">
            <a class="brand" href="{{ route('login') }}"><span class="brand-mark">T</span><span>treddy</span></a>
            <div class="auth-story-copy">
                <span class="eyebrow"><span class="live-dot"></span> YOUR PORTFOLIO, IN FOCUS</span>
                <h1>Make every<br>move <span>count.</span></h1>
                <p>A clearer view of the positions you hold, the trades you record, and how your portfolio is moving.</p>
                <div class="story-stat"><span class="story-stat-icon">↗</span><span>Track your portfolio<br><strong>All in one place</strong></span></div>
            </div>
            <span class="auth-footnote">Investing involves risk. This app records portfolio activity; it does not place orders with a broker.</span>
        </section>
        <section class="auth-panel">
            <div class="auth-form-wrap">
                <span class="eyebrow">WELCOME BACK</span>
                <h2>Sign in to your account</h2>
                <p class="auth-muted">Your portfolio is right where you left it.</p>
                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="you@example.com">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Your password">
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                    <label class="remember-row"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in</span></label>
                    <button class="button button-primary auth-submit" type="submit">Sign in <span>→</span></button>
                </form>
                <p class="auth-switch">New to Treddy? <a href="{{ route('register') }}">Create an account</a></p>
            </div>
        </section>
    </main>
</body>
</html>
