<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f6">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MB · {{ config('app.name', 'Marketboard') }}</title>
    <link rel="stylesheet" href="{{ asset('css/trading.css') }}">
    <link rel="stylesheet" href="{{ asset('css/option-chain.css') }}">
</head>
<body class="dashboard-page">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">M</span><span class="brand-name">market<span class="brand-light">board</span></span></a>
                <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Collapse sidebar" aria-expanded="true" aria-controls="workspace-navigation" title="Collapse sidebar"><span>‹</span></button>
            </div>
            <nav id="workspace-navigation" aria-label="Workspace navigation">
            <div class="side-label">WORKSPACE</div>
            <a class="side-link active" href="{{ route('dashboard') }}" title="Overview" aria-label="Overview"><span class="side-icon">▦</span><span class="nav-label">Overview</span></a>
            <a class="side-link" href="#holdings" title="My positions" aria-label="My positions"><span class="side-icon">◫</span><span class="nav-label">My positions</span></a>
            <a class="side-link" href="#activity" title="Activity" aria-label="Activity"><span class="side-icon">↕</span><span class="nav-label">Activity</span></a>
            <a class="side-link" href="#option-chain" title="Option chain" aria-label="Option chain"><span class="side-icon">⌁</span><span class="nav-label">Option chain</span></a>
            </nav>
            <div class="sidebar-bottom">
                <div class="help-card"><span class="help-icon">✳</span><strong>Investing is a journey.</strong><span>Stay informed and make decisions that are right for you.</span></div>
                <div class="profile-row"><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="profile-name">{{ auth()->user()->name }}<small>{{ auth()->user()->email }}</small></span><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit" aria-label="Sign out" title="Sign out">↪</button></form></div>
            </div>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb"><span>Workspace</span><span class="breadcrumb-sep">/</span><strong>Overview</strong></div>
                <div class="topbar-right"><span class="market-status"><span class="status-dot"></span> Portfolio tracker</span><span class="topbar-divider"></span><span class="topbar-date" id="today-date"></span></div>
            </header>
            <div class="page-content">
                <section class="welcome-row">
                    <div><span class="eyebrow">YOUR FINANCIAL SNAPSHOT</span><h1>Good to see you, {{ explode(' ', auth()->user()->name)[0] }} <span class="wave">✳</span></h1><p>Here’s what’s happening with your portfolio.</p></div>
                    <button class="button button-primary" id="open-trade"><span class="button-plus">＋</span> Record a trade</button>
                </section>
                <div class="notice-banner"><span class="notice-icon">ⓘ</span><span><strong>Portfolio tracker only.</strong> Trades you record here update your portfolio; they are not sent to a broker or exchange.</span><button class="notice-dismiss" aria-label="Dismiss notice">×</button></div>
                <section class="stats-grid" aria-label="Portfolio summary">
                    <article class="stat-card stat-featured"><div class="stat-top"><span>Total portfolio value</span><span class="stat-icon">◈</span></div><div class="stat-value" id="total-value">—</div><div class="stat-foot"><span class="stat-change" id="total-day-change">—</span><span>today</span></div><div class="featured-spark" aria-hidden="true"><svg viewBox="0 0 260 60" preserveAspectRatio="none"><path d="M0 49 C18 46 20 33 39 38 S62 50 78 35 S98 23 111 31 S132 43 149 26 S172 31 188 17 S211 28 229 13 S247 21 260 4" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M0 49 C18 46 20 33 39 38 S62 50 78 35 S98 23 111 31 S132 43 149 26 S172 31 188 17 S211 28 229 13 S247 21 260 4 V60 H0Z" fill="url(#sparkFill)" opacity=".25"/><defs><linearGradient id="sparkFill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#7ce0b5"/><stop offset="1" stop-color="#7ce0b5" stop-opacity="0"/></linearGradient></defs></svg></div></article>
                    <article class="stat-card"><div class="stat-top"><span>Invested capital</span><span class="stat-icon stat-icon-muted">↗</span></div><div class="stat-value" id="total-cost">—</div><div class="stat-foot"><span class="stat-muted">Across your open positions</span></div></article>
                    <article class="stat-card"><div class="stat-top"><span>Total return</span><span class="stat-icon stat-icon-muted">◎</span></div><div class="stat-value" id="total-return">—</div><div class="stat-foot"><span class="stat-change" id="total-return-percent">—</span><span>since inception</span></div></article>
                    <article class="stat-card"><div class="stat-top"><span>Open positions</span><span class="stat-icon stat-icon-muted">▤</span></div><div class="stat-value" id="positions-count">—</div><div class="stat-foot"><span class="stat-muted" id="positions-caption">Stocks in your portfolio</span></div></article>
                </section>
                <section class="content-card holdings-card" id="holdings">
                    <div class="card-heading"><div><h2>Your positions</h2><p>A live view of the stocks in your portfolio</p></div><div class="table-tools"><label class="search-box"><span>⌕</span><input id="holding-search" type="search" placeholder="Find a stock" aria-label="Search positions"></label><button class="icon-button refresh-button" id="refresh-data" title="Refresh portfolio" aria-label="Refresh portfolio">↻</button></div></div>
                    <div class="table-scroll"><table class="data-table"><thead><tr><th>ASSET</th><th>SHARES</th><th>LAST PRICE</th><th>MARKET VALUE</th><th>RETURN</th><th>DAY</th><th></th></tr></thead><tbody id="holdings-body"><tr><td colspan="7" class="loading-cell"><span class="spinner"></span> Loading your portfolio…</td></tr></tbody></table></div>
                    <div class="card-empty-note" id="unpriced-note" hidden></div>
                    <div class="empty-state" id="empty-holdings" hidden><div class="empty-icon">◫</div><strong>Your portfolio starts here</strong><p>Record your first buy to see your positions and performance here.</p><button class="button button-primary button-small" id="empty-trade">Record your first trade</button></div>
                </section>
                <section class="content-card activity-card" id="activity">
                    <div class="card-heading"><div><h2>Recent activity</h2><p>Your latest recorded buys and sells</p></div><span class="activity-count" id="activity-count">Recent transactions</span></div>
                    <div class="table-scroll"><table class="data-table activity-table"><thead><tr><th>TRADE</th><th>DATE</th><th>SHARES</th><th>PRICE</th><th>TOTAL</th><th></th></tr></thead><tbody id="activity-body"><tr><td colspan="6" class="loading-cell"><span class="spinner"></span> Loading activity…</td></tr></tbody></table></div>
                    <div class="empty-state empty-activity" id="empty-activity" hidden><div class="empty-icon">↕</div><strong>No activity yet</strong><p>Your recorded transactions will appear here.</p></div>
                </section>
                <section class="content-card option-chain-card" id="option-chain" hidden>
                    <div class="option-chain-heading">
                        <div>
                                <div class="option-title-row"><h2>Option chain</h2><span class="sample-badge" id="option-chain-badge"><span></span> SIMULATED DATA</span></div>
                            <p id="option-chain-description">Explore call and put contracts by strike and expiry. Quotes below are illustrative sample data, not live prices.</p>
                        </div>
                        <button class="icon-button" id="refresh-option-chain" type="button" title="Refresh sample chain" aria-label="Refresh sample chain">↻</button>
                    </div>
                    <div class="option-chain-controls">
                        <div class="angel-one-tools">
                            <div class="chain-source-note" id="angel-one-status" role="status">
                                <span class="source-dot"></span>
                                <span>Sample feed · Connect Angel One to securely retrieve an API access token</span>
                            </div>
                            <details class="angel-one-connection" id="angel-one-connection">
                                <summary id="angel-one-summary">
                                    <span class="source-dot"></span>
                                    <span id="angel-one-summary-text">Connect Angel One</span>
                                </summary>
                                <div class="angel-one-panel">
                                    <div class="angel-one-panel-heading">
                                        <div>
                                            <strong>Angel One SmartAPI</strong>
                                            <p>Credentials are sent securely to the app server. PIN and TOTP are used only for login; credentials and tokens remain encrypted server-side.</p>
                                        </div>
                                        <button class="icon-button angel-one-close" type="button" aria-label="Close connection form">×</button>
                                    </div>
                                    <form id="angel-one-form" autocomplete="off">
                                        <div class="angel-one-fields">
                                            <label>Client Id<input name="clientcode" required maxlength="64" autocomplete="off" placeholder="Angel One client code"></label>
                                            <label>Client PIN<input name="password" type="password" required maxlength="128" autocomplete="new-password" placeholder="Trading account PIN"></label>
                                            <label>Authenticator TOTP<input name="totp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required autocomplete="one-time-code" placeholder="6-digit code"></label>
                                            <label>API key<input name="api_key" type="password" required maxlength="255" autocomplete="new-password" placeholder="SmartAPI key"></label>
                                        </div>
                                        <p class="angel-one-security-note">Server network headers and a fresh request state are added automatically and are never shown in the browser.</p>
                                        <div class="angel-one-form-error" id="angel-one-error" role="alert" hidden></div>
                                        <div class="angel-one-form-actions">
                                            <button class="button button-primary button-small" id="angel-one-connect" type="submit">Connect</button>
                                        </div>
                                    </form>
                                    <div class="angel-one-connected" id="angel-one-connected" hidden>
                                        <p class="angel-one-connected-message" id="angel-one-connected-message"></p>
                                        <button class="button button-secondary button-small" id="angel-one-disconnect" type="button" disabled>Disconnect</button>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                    <p class="option-connect-prompt" id="option-connect-prompt">Connect your Angel One account to load live option data.</p>
                    <div class="option-live-content" id="option-live-content" hidden>
                    <div class="option-chain-controls option-market-controls" id="option-market-controls" hidden>
                        <label class="option-select-label">UNDERLYING
                            <select id="option-underlying" aria-label="Option underlying">
                                <option value="NIFTY" selected>NIFTY 50</option>
                                <option value="BANKNIFTY">BANK NIFTY</option>
                                <option value="SENSEX">SENSEX</option>
                                <option value="CRUDEOIL">CRUDE OIL</option>
                            </select>
                        </label>
                        <label class="option-select-label">EXPIRY
                            <input id="option-expiry" type="date" aria-label="Option expiry date" required>
                        </label>
                    </div>
                    <div class="option-summary-grid" id="option-summary-grid" aria-label="Option chain summary">
                        <div class="option-summary-item"><span>UNDERLYING SPOT</span><strong id="option-spot">—</strong><small id="option-symbol-label">—</small></div>
                        <div class="option-summary-item"><span>PUT / CALL RATIO</span><strong id="option-pcr">—</strong><small>Based on sample open interest</small></div>
                        <div class="option-summary-item"><span>CALLS · TOTAL OI</span><strong id="option-call-oi">—</strong><small>Open interest contracts</small></div>
                        <div class="option-summary-item"><span>PUTS · TOTAL OI</span><strong id="option-put-oi">—</strong><small>Open interest contracts</small></div>
                    </div>
                    <div class="option-chain-table-wrap">
                        <table class="option-chain-table">
                            <thead>
                                <tr class="chain-group-row">
                                    <th class="call-group" colspan="5">CALLS <span>CALL OPTIONS</span></th>
                                    <th class="strike-group" rowspan="2">STRIKE</th>
                                    <th class="put-group" colspan="5">PUTS <span>PUT OPTIONS</span></th>
                                </tr>
                                <tr class="chain-columns-row">
                                    <th>OPEN INT.</th><th>OI CHANGE</th><th>VOLUME</th><th>IV</th><th>LTP</th>
                                    <th>LTP</th><th>IV</th><th>VOLUME</th><th>OI CHANGE</th><th>OPEN INT.</th>
                                </tr>
                            </thead>
                            <tbody id="option-chain-body">
                                <tr><td colspan="11" class="chain-loading"><span class="spinner"></span> Loading option chain…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="option-chain-footer"><span id="option-chain-footer-note">Illustrative options data for interface preview only. Not for trading or investment decisions.</span><span id="option-chain-updated">—</span></div>
                    </div>
                </section>
                <footer class="page-footer"><span>Marketboard <span id="footer-year"></span></span><span>Data shown is for informational purposes only and may be delayed.</span></footer>
            </div>
        </main>
    </div>

    <dialog class="trade-dialog" id="trade-dialog" aria-labelledby="trade-title">
        <form class="trade-form" id="trade-form">
            <div class="dialog-heading"><div><span class="eyebrow">PORTFOLIO ACTIVITY</span><h2 id="trade-title">Record a trade</h2><p>This records a portfolio transaction only; no broker order is placed.</p></div><button class="icon-button dialog-close" type="button" aria-label="Close">×</button></div>
            <div class="trade-switch" role="group" aria-label="Transaction type"><button class="trade-type active" type="button" data-type="buy">Buy</button><button class="trade-type" type="button" data-type="sell">Sell</button></div>
            <input type="hidden" name="type" value="buy">
            <div class="form-grid">
                <div class="form-field"><label for="symbol">Stock symbol</label><input id="symbol" name="symbol" type="text" maxlength="20" required placeholder="e.g. AAPL" autocomplete="off"><span class="field-hint">Enter the ticker symbol</span></div>
                <div class="form-field"><label for="quantity">Shares</label><input id="quantity" name="quantity" type="number" min="0.00000001" step="any" required placeholder="0.00"></div>
                <div class="form-field"><label for="price">Price per share</label><div class="input-with-prefix"><span id="currency-prefix">$</span><input id="price" name="price" type="number" min="0" step="any" required placeholder="0.00"></div><span class="field-hint">Enter your recorded execution price</span></div>
                <div class="form-field"><label for="trade-date">Trade date</label><input id="trade-date" name="date" type="date" required></div>
            </div>
            <div class="trade-error" id="trade-error" role="alert" hidden></div>
            <div class="dialog-actions"><button class="button button-secondary dialog-cancel" type="button">Cancel</button><button class="button button-primary" id="submit-trade" type="submit">Record buy</button></div>
        </form>
    </dialog>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script>
        window.marketboard = {
            userName: @json(auth()->user()->name),
            apiBase: @json(url('/api')),
            loginUrl: @json(route('login')),
            angelOne: {
                connected: @json(is_array($angelOneConnection ?? null) && !empty($angelOneConnection['secrets'])),
                clientCode: @json(is_array($angelOneConnection ?? null) ? ($angelOneConnection['client_code'] ?? null) : null),
                connectUrl: @json(route('option-chain.angel-one.connect')),
                greeksUrl: @json(route('option-chain.angel-one.greeks')),
                disconnectUrl: @json(route('option-chain.angel-one.disconnect')),
            },
        };
    </script>
    <script src="{{ asset('js/option-chain-demo.js') }}" defer></script>
    <script src="{{ asset('js/trading.js') }}" defer></script>
</body>
</html>
