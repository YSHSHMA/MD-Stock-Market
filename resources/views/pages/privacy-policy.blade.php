<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f6">
    <title>Privacy Policy · {{ config('app.name', 'Marketboard') }}</title>
    <link rel="stylesheet" href="{{ asset('css/trading.css') }}">
    <link rel="stylesheet" href="{{ asset('css/option-chain.css') }}?v={{ filemtime(public_path('css/option-chain.css')) }}">
</head>
<body class="information-page">
    <header class="topbar info-topbar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">T</span><span class="brand-name">treddy</span></a>
        <nav class="topbar-navigation" aria-label="Main navigation">
            <a class="top-nav-link" href="{{ route('dashboard') }}">Overview</a>
            <a class="top-nav-link" href="{{ route('dashboard') }}#option-chain">Option Chain</a>
            <a class="top-nav-link" href="{{ route('about') }}">About Us</a>
            <a class="top-nav-link active" href="{{ route('privacy-policy') }}">Privacy Policy</a>
        </nav>
    </header>
    <main class="info-content">
        <span class="eyebrow">YOUR INFORMATION</span>
        <h1>Privacy Policy</h1>
        <p class="info-lead">This page explains what Treddy uses to provide portfolio tracking and the optional Angel One option-chain connection.</p>

        <section class="info-card">
            <h2>Information stored in Treddy</h2>
            <p>Your account name and email are used to identify your account. Trades and portfolio activity that you enter are stored to calculate and display your holdings, performance, and reports.</p>
            <p>If you connect Angel One, the app stores your client code and encrypted connection credentials to maintain that connection and retrieve the market data you request. You can disconnect the connection from the option-chain settings.</p>
        </section>

        <section class="info-card">
            <h2>How information is used</h2>
            <p>Account and portfolio information is used to operate your dashboard and provide portfolio reports. Angel One connection information is used for the live option-chain features. Treddy does not send portfolio trades to a broker or exchange.</p>
        </section>

        <section class="info-card">
            <h2>Keeping your information secure</h2>
            <p>Angel One connection credentials are encrypted before being stored by the application. Use a private device, keep your sign-in details secure, and sign out when you have finished using a shared device.</p>
        </section>

        <section class="info-card">
            <h2>Questions or account requests</h2>
            <p>For questions about this policy or a request concerning your account information, contact the administrator of this Treddy service.</p>
        </section>
        <a class="info-back-link" href="{{ route('dashboard') }}">← Back to your dashboard</a>
    </main>
</body>
</html>
