<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8faf8">
    <title>Treddy · Your market, in focus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/trading.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="landing-page">
    <header class="landing-header">
        <nav class="landing-nav" aria-label="Main navigation">
            <a class="brand" href="{{ route('home') }}" aria-label="Treddy home"><span class="brand-mark">T</span><span>treddy</span></a>
            <div class="landing-links"><a href="#markets">Markets</a><a href="#tools">Tools</a><a href="#about">About</a></div>
            <div class="landing-actions">
                @auth
                    <div class="user-menu-wrap">
                        <button class="user-menu-button" type="button" aria-expanded="false" aria-controls="user-menu"><span class="user-icon">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span>{{ mb_strtoupper(explode(' ', trim(auth()->user()->name))[0]) }}</span><span class="chevron">⌄</span></button>
                        <div class="user-menu" id="user-menu"><a href="{{ route('dashboard') }}">Open dashboard <span>→</span></a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out <span>↗</span></button></form></div>
                    </div>
                @else
                    <button class="login-trigger" type="button" data-open-login>Log in</button>
                    <button class="landing-cta" type="button" data-open-account>Create account <span>→</span></button>
                @endauth
            </div>
            <button class="mobile-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
        </nav>
    </header>

    <main>
        <section class="hero" id="about">
            <div class="hero-copy">
                <span class="hero-kicker"><span class="pulse-dot"></span> A CLEARER WAY TO FOLLOW THE MARKET</span>
                <h1>Your market.<br><span>Your moves.</span></h1>
                <p class="hero-description">Make sense of your portfolio with one calm, clear view of the markets, your positions, and every move you make.</p>
                <div class="hero-buttons"><a href="{{ auth()->check() ? route('dashboard') : '#' }}" class="hero-primary" @guest data-open-account @endguest>{{ auth()->check() ? 'Open your dashboard' : 'Get started for free' }} <span>→</span></a><a href="#tools" class="hero-secondary">Explore Treddy <span>↓</span></a></div>
                <div class="hero-proof"><div class="proof-avatars"><span>A</span><span>R</span><span>M</span><span>+</span></div><p><strong>A smarter view of your money</strong><br>Built for the way you invest.</p></div>
            </div>
            <div class="hero-visual" aria-label="Illustration of a portfolio market dashboard">
                <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div>
                <div class="market-card">
                    <div class="market-card-top"><div><span class="card-overline">YOUR PORTFOLIO</span><strong>Market overview</strong></div><span class="live-pill"><i></i> LIVE</span></div>
                    <div class="portfolio-value"><strong>₹12,48,560</strong><span>↗ 8.42% <small>this month</small></span></div>
                    <div class="chart-labels"><span>Portfolio value</span><span>Last 30 days⌄</span></div>
                    <svg class="portfolio-chart" viewBox="0 0 520 180" preserveAspectRatio="none" role="img" aria-label="Portfolio performance trending upward"><defs><linearGradient id="chart-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#62c795" stop-opacity=".25"/><stop offset="1" stop-color="#62c795" stop-opacity="0"/></linearGradient></defs><path class="chart-area" d="M0 144 C30 137 34 119 61 126 S95 118 121 130 S160 91 186 105 S219 95 244 103 S276 76 301 86 S333 65 357 75 S391 43 415 56 S451 39 473 45 S498 20 520 24 V180 H0Z"/><path class="chart-line" d="M0 144 C30 137 34 119 61 126 S95 118 121 130 S160 91 186 105 S219 95 244 103 S276 76 301 86 S333 65 357 75 S391 43 415 56 S451 39 473 45 S498 20 520 24"/></svg>
                    <div class="chart-axis"><span>May 01</span><span>May 08</span><span>May 15</span><span>May 22</span><span>May 30</span></div>
                    <div class="market-card-bottom"><div><span class="mini-icon green">↗</span><span><small>Today's change</small><strong>+₹8,240 <em>+1.24%</em></strong></span></div><div class="mini-divider"></div><div><span class="mini-icon violet">◈</span><span><small>Positions tracked</small><strong>12 <em>across 4 sectors</em></strong></span></div></div>
                </div>
                <div class="floating-card floating-gain"><span class="gain-icon">↗</span><span><small>Portfolio growth</small><strong>+₹24,830</strong></span><span class="gain-tag">+8.4%</span></div>
                <div class="floating-card floating-stock"><span class="stock-mark">N</span><span><small>NIFTY 50</small><strong>24,853.40</strong></span><span class="stock-change">+0.68%</span></div>
                <div class="visual-caption"><span class="caption-spark">✳</span> Clarity for every kind of investor</div>
            </div>
            <div class="hero-decoration decoration-a"></div><div class="hero-decoration decoration-b"></div>
        </section>

        <section class="ticker-strip" id="markets"><div class="ticker-intro"><span class="pulse-dot"></span> MARKETS TODAY</div><div class="ticker-item"><span>NIFTY 50</span><strong>24,853.40</strong><em>+0.68%</em><svg viewBox="0 0 70 24"><path d="M1 18 12 15 22 17 34 9 44 12 55 5 69 7"/></svg></div><div class="ticker-item"><span>SENSEX</span><strong>81,765.86</strong><em>+0.52%</em><svg viewBox="0 0 70 24"><path d="M1 17 11 19 23 12 33 14 45 8 54 11 69 4"/></svg></div><div class="ticker-item"><span>BANK NIFTY</span><strong>56,204.15</strong><em>+0.31%</em><svg viewBox="0 0 70 24"><path d="M1 20 12 13 23 17 34 10 43 13 56 7 69 5"/></svg></div><div class="ticker-note">Indicative market data</div></section>

        <section class="features-section" id="tools"><div class="section-heading"><span class="section-kicker">MADE FOR YOUR EVERYDAY</span><h2>Everything in focus.<br><span>Nothing in the way.</span></h2><p>From your first position to your next big goal, keep your investing life in one easy place.</p></div><div class="feature-grid"><article class="feature-card feature-card-dark"><span class="feature-icon">◫</span><span class="feature-count">01 / TRACK</span><h3>Your portfolio,<br>at a glance.</h3><p>See the value of your positions and the changes that matter, all in one simple snapshot.</p><span class="feature-link">A clearer overview <b>↗</b></span><div class="feature-graphic graphic-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article><article class="feature-card"><span class="feature-icon icon-lilac">⌁</span><span class="feature-count">02 / UNDERSTAND</span><h3>Know what’s<br>moving you.</h3><p>Bring your transactions and market activity into a view that feels easy to understand.</p><span class="feature-link">Make sense of the details <b>↗</b></span><div class="feature-graphic graphic-donut"><div><strong>+8.4%</strong><small>this month</small></div></div></article><article class="feature-card"><span class="feature-icon icon-peach">✳</span><span class="feature-count">03 / BUILD</span><h3>Good habits.<br>Better decisions.</h3><p>Keep your records tidy and make more room for thoughtful, confident investing.</p><span class="feature-link">Start with the basics <b>↗</b></span><div class="feature-graphic graphic-spark"><span>₹</span><svg viewBox="0 0 230 80"><path d="M2 66 C28 58 34 61 50 45 S78 54 96 38 120 42 140 25 165 40 184 15 210 20 228 4"/></svg></div></article></div></section>

        <section class="closing-cta"><div><span class="section-kicker">A LITTLE MORE CLARITY GOES A LONG WAY</span><h2>Ready to see your market<br><span>in a new light?</span></h2></div><a href="{{ auth()->check() ? route('dashboard') : '#' }}" class="hero-primary" @guest data-open-account @endguest>{{ auth()->check() ? 'Go to dashboard' : 'Create your free account' }} <span>→</span></a><div class="closing-orb"></div></section>
    </main>
    <footer class="landing-footer"><a class="brand" href="{{ route('home') }}"><span class="brand-mark">T</span><span>treddy</span></a><span>Invest with clarity. Move with confidence.</span><div><a href="{{ route('about') }}">About</a><a href="{{ route('privacy-policy') }}">Privacy</a></div></footer>

    <div class="login-modal" id="login-modal" aria-hidden="true" data-mode="{{ old('_auth_mode', request('auth', 'login')) }}" @if($errors->any() || request('auth')) data-show="true" @endif><div class="modal-backdrop" data-close-login></div><section class="login-dialog" role="dialog" aria-modal="true" aria-labelledby="login-title"><button class="modal-close" type="button" aria-label="Close account dialog" data-close-login>×</button><a class="brand modal-brand" href="{{ route('home') }}"><span class="brand-mark">T</span><span>treddy</span></a>
        <div class="account-view" data-view="login"><span class="section-kicker">WELCOME BACK</span><h2 id="login-title">Good to see you again.</h2><p class="modal-subtitle">Sign in to pick up where you left off.</p><form method="POST" action="{{ route('login.store') }}" class="auth-form modal-form">@csrf<input type="hidden" name="_auth_mode" value="login"><label for="modal-email">Email address</label><input id="modal-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@example.com">@error('email')<span class="field-error">{{ $message }}</span>@enderror<label for="modal-password">Password</label><input id="modal-password" name="password" type="password" autocomplete="current-password" required placeholder="Your password">@error('password')<span class="field-error">{{ $message }}</span>@enderror<label class="remember-row"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label><button class="hero-primary modal-submit" type="submit">Log in <span>→</span></button></form><p class="modal-register">New to Treddy? <button type="button" data-switch-account="register">Create an account</button></p></div>
        <div class="account-view" data-view="register"><span class="section-kicker">GET STARTED</span><h2 id="register-title">Create your account.</h2><p class="modal-subtitle">A clearer view of your portfolio starts here.</p><form method="POST" action="{{ route('register.store') }}" class="auth-form modal-form">@csrf<input type="hidden" name="_auth_mode" value="register"><label for="register-name">Full name</label><input id="register-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required placeholder="Alex Morgan">@error('name')<span class="field-error">{{ $message }}</span>@enderror<label for="register-email">Email address</label><input id="register-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@example.com">@if(old('_auth_mode') === 'register') @error('email')<span class="field-error">{{ $message }}</span>@enderror @endif<label for="register-password">Password</label><input id="register-password" name="password" type="password" autocomplete="new-password" required placeholder="At least 8 characters">@if(old('_auth_mode') === 'register') @error('password')<span class="field-error">{{ $message }}</span>@enderror @enderror<label for="register-password-confirmation">Confirm password</label><input id="register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Enter your password again">@error('password_confirmation')<span class="field-error">{{ $message }}</span>@enderror<button class="hero-primary modal-submit" type="submit">Create account <span>→</span></button></form><p class="modal-register">Already have an account? <button type="button" data-switch-account="login">Log in</button></p></div>
    </section></div>
    <script>
        (() => {
            const modal = document.getElementById('login-modal');
            const setMode = mode => { modal.dataset.mode = mode; modal.querySelectorAll('[data-view]').forEach(view => view.hidden = view.dataset.view !== mode); modal.querySelector('.login-dialog').setAttribute('aria-labelledby', mode === 'register' ? 'register-title' : 'login-title'); };
            setMode(modal.dataset.mode === 'register' ? 'register' : 'login');
            document.querySelectorAll('[data-open-account]').forEach(button => button.addEventListener('click', event => { event.preventDefault(); setMode('register'); open(); }));
            document.querySelectorAll('[data-switch-account]').forEach(button => button.addEventListener('click', () => setMode(button.dataset.switchAccount)));
            document.querySelectorAll('[data-open-login]').forEach(button => button.addEventListener('click', () => { setMode('login'); open(); }));
            const open = () => { modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('modal-open'); setTimeout(() => document.getElementById('modal-email')?.focus(), 80); };
            const close = () => { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('modal-open'); };
            
            document.querySelectorAll('[data-close-login]').forEach(button => button.addEventListener('click', close));
            document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
            if (modal.dataset.show === 'true') open();
            const userButton = document.querySelector('.user-menu-button');
            userButton?.addEventListener('click', () => { const menu = document.getElementById('user-menu'); const opened = menu.classList.toggle('is-open'); userButton.setAttribute('aria-expanded', String(opened)); });
            document.addEventListener('click', event => { if (!event.target.closest('.user-menu-wrap')) { document.getElementById('user-menu')?.classList.remove('is-open'); userButton?.setAttribute('aria-expanded', 'false'); } });
            const menuToggle = document.querySelector('.mobile-menu-toggle');
            menuToggle?.addEventListener('click', () => { const links = document.querySelector('.landing-links'); const opened = links.classList.toggle('is-open'); menuToggle.setAttribute('aria-expanded', String(opened)); });
        })();
    </script>
</body>
</html>
