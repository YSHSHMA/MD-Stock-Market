<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f6">
    <title>About Us · {{ config('app.name', 'Marketboard') }}</title>
    <link rel="stylesheet" href="{{ asset('css/trading.css') }}">
    <link rel="stylesheet" href="{{ asset('css/option-chain.css') }}?v={{ filemtime(public_path('css/option-chain.css')) }}">
</head>
<body class="information-page">
    <header class="topbar info-topbar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">T</span><span class="brand-name">treddy</span></a>
        <nav class="topbar-navigation" aria-label="Main navigation">
            <a class="top-nav-link" href="{{ route('dashboard') }}">Overview</a>
            <a class="top-nav-link" href="{{ route('dashboard') }}#option-chain">Option Chain</a>
            <a class="top-nav-link active" href="{{ route('about') }}">About Us</a>
            <a class="top-nav-link" href="{{ route('privacy-policy') }}">Privacy Policy</a>
        </nav>
    </header>
    <main class="info-content">
        <span class="eyebrow">ABOUT TREDDY</span>
        <h1>A clearer view of your market activity.</h1>
        <p class="info-lead">Treddy brings portfolio tracking and an options-chain view together in one workspace, so you can review your own activity and explore market data with less friction.</p>

        <section class="info-card">
            <h2>Built for a more thoughtful workflow</h2>
            <p>Record stock trades and follow your holdings, returns, and activity from a single dashboard. The option chain presents call and put data around the at-the-money strike, with Greeks and open-interest indicators to help you read the table.</p>
            <p>When you connect Angel One, Treddy can show option-chain quotes and Greeks for the selected underlying and expiry. Some Greeks may be calculated from available quotes when live Greek values are not supplied.</p>
        </section>

        <section class="info-card">
            <h2>Your decisions stay yours</h2>
            <p>Trades recorded in Treddy update your portfolio tracker only. The app does not place orders with a broker or exchange. Market data and calculations are informational and can change as quotes update.</p>
        </section>
        <a class="info-back-link" href="{{ route('dashboard') }}">← Back to your dashboard</a>
    </main>
</body>
</html>
