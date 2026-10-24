
(() => {
    'use strict';

    const config = window.marketboard;

    if (!config) {
        console.error('Marketboard configuration is missing.');
        return;
    }

    const $ = (selector) => document.querySelector(selector);

    const elements = {
        totalValue: $('#total-value'),
        totalCost: $('#total-cost'),
        totalReturn: $('#total-return'),
        totalReturnPercent: $('#total-return-percent'),
        totalDayChange: $('#total-day-change'),
        positionsCount: $('#positions-count'),
        positionsCaption: $('#positions-caption'),
        holdingsBody: $('#holdings-body'),
        activityBody: $('#activity-body'),
        emptyHoldings: $('#empty-holdings'),
        emptyActivity: $('#empty-activity'),
        unpricedNote: $('#unpriced-note'),
        activityCount: $('#activity-count'),
        search: $('#holding-search'),
        dialog: $('#trade-dialog'),
        tradeForm: $('#trade-form'),
        tradeError: $('#trade-error'),
        toast: $('#toast'),
    };

    const optionElements = {
        underlying: $('#option-underlying'),
        expiry: $('#option-expiry'),
        spot: $('#option-spot'),
        symbol: $('#option-symbol-label'),
        pcr: $('#option-pcr'),
        callOi: $('#option-call-oi'),
        putOi: $('#option-put-oi'),
        body: $('#option-chain-body'),
        tableHead: $('.option-chain-table thead'),
        summary: $('#option-summary-grid'),
        badge: $('#option-chain-badge'),
        description: $('#option-chain-description'),
        footerNote: $('#option-chain-footer-note'),
        updated: $('#option-chain-updated'),
    };

    const angelOneElements = {
        panel: $('#angel-one-connection'),
        summaryText: $('#angel-one-summary-text'),
        status: $('#angel-one-status'),
        form: $('#angel-one-form'),
        fields: $('.angel-one-fields'),
        securityNote: $('.angel-one-security-note'),
        formActions: $('.angel-one-form-actions'),
        formError: $('#angel-one-error'),
        connectButton: $('#angel-one-connect'),
        connected: $('#angel-one-connected'),
        connectedMessage: $('#angel-one-connected-message'),
        disconnectButton: $('#angel-one-disconnect'),
    };

    const optionMarketControls = $('#option-market-controls');
    const optionLiveContent = $('#option-live-content');
    const optionConnectPrompt = $('#option-connect-prompt');

    const sidebarToggle = $('#sidebar-toggle');
    const appShell = $('.app-shell');

    const demoOptionTableHead = optionElements.tableHead?.innerHTML ?? '';

    let holdings = [];
    let currency = { code: 'USD', symbol: '$' };
    let toastTimeout;
    let angelOneConnected = Boolean(config.angelOne?.connected);
    let optionChainRequestId = 0;
    let optionExpiryRequestId = 0;
    let optionChainInitialized = false;
    let liveSubscriptionTimer;
    let liveSubscriptionSelection = '';
    let liveSubscriptionErrorShown = false;

    const wsStatus = $('#ws-status');
    const wsLabel = $('#ws-label');

    const escapeHtml = (value) =>
        String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[character]);

    function updateWsStatus(state) {
        if (!wsStatus) return;

        const states = {
            connected: {
                label: 'Live',
                cls: 'ws-live',
                title: 'WebSocket connected – receiving live updates',
            },
            connecting: {
                label: 'Connecting…',
                cls: 'ws-connecting',
                title: 'Connecting to the live data server',
            },
            disconnected: {
                label: 'Offline',
                cls: 'ws-offline',
                title: 'WebSocket disconnected – will retry',
            },
            unavailable: {
                label: 'Unavailable',
                cls: 'ws-offline',
                title: 'Live data server is not reachable',
            },
        };

        const info = states[state] || states.connecting;

        wsStatus.title = info.title;
        wsStatus.className = `market-status ${info.cls}`;

        if (wsLabel) {
            wsLabel.textContent = info.label;
        }
    }

    function updateSidebarToggle() {
        if (!sidebarToggle || !appShell) return;

        const collapsed = appShell.classList.contains('sidebar-collapsed');

        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute(
            'aria-label',
            collapsed ? 'Expand sidebar' : 'Collapse sidebar'
        );
        sidebarToggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';

        const icon = sidebarToggle.querySelector('span');
        if (icon) icon.textContent = collapsed ? '›' : '‹';
    }

    if (appShell && sidebarToggle) {
        const sidebarCollapsed =
            localStorage.getItem('marketboard-sidebar-collapsed') === 'true';

        appShell.classList.toggle('sidebar-collapsed', sidebarCollapsed);
        updateSidebarToggle();

        sidebarToggle.addEventListener('click', () => {
            appShell.classList.toggle('sidebar-collapsed');

            localStorage.setItem(
                'marketboard-sidebar-collapsed',
                String(appShell.classList.contains('sidebar-collapsed'))
            );

            updateSidebarToggle();
        });
    }

    if (window.marketboardEcho) {
        const state =
            window.marketboardEcho.connector?.pusher?.connection?.state;

        updateWsStatus(state || 'connecting');
    }

    function showToast(message, isError = false) {
        if (!elements.toast) return;

        window.clearTimeout(toastTimeout);

        elements.toast.textContent = message;
        elements.toast.classList.toggle('toast-error', isError);
        elements.toast.classList.add('visible');

        toastTimeout = window.setTimeout(() => {
            elements.toast.classList.remove('visible');
        }, 3400);
    }

    const money = (value) =>
        new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency.code || 'USD',
            maximumFractionDigits: 2,
        }).format(Number(value) || 0);

    const optionMoney = (value) =>
        new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2,
        }).format(Number(value) || 0);

    const quantity = (value) =>
        new Intl.NumberFormat(undefined, {
            maximumFractionDigits: 6,
        }).format(Number(value) || 0);

    const signedMoney = (value) => {
        const amount = Number(value) || 0;

        return `${amount > 0 ? '+' : amount < 0 ? '−' : ''}${money(
            Math.abs(amount)
        )}`;
    };

    function setChange(element, value, includePercent = false) {
        if (!element) return;

        const amount = Number(value) || 0;

        element.classList.toggle('negative', amount < 0);

        element.textContent =
            `${signedMoney(amount)}` +
            (includePercent
                ? ` (${amount > 0 ? '+' : ''}${amount.toFixed(2)}%)`
                : '');
    }

    async function api(path, options = {}) {
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        };

        const xsrf = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

        if (
            xsrf &&
            options.method &&
            options.method.toUpperCase() !== 'GET'
        ) {
            headers['X-XSRF-TOKEN'] = decodeURIComponent(
                xsrf.slice('XSRF-TOKEN='.length)
            );
        }

        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(`${config.apiBase}${path}`, {
            credentials: 'same-origin',
            ...options,
            headers,
        });

        if (response.status === 401 || response.status === 419) {
            window.location.assign(config.loginUrl);
            throw new Error('Your session has expired. Please sign in again.');
        }

        const payload =
            response.status === 204
                ? null
                : await response.json().catch(() => ({}));

        if (!response.ok) {
            const validation =
                payload?.errors &&
                Object.values(payload.errors).flat()[0];

            throw new Error(
                validation ||
                payload?.message ||
                'The request could not be completed.'
            );
        }

        return payload;
    }

    async function postAngelOne(url, body) {
        const csrf = $('meta[name="csrf-token"]');

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf.content } : {}),
            },
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validation =
                payload.errors &&
                Object.values(payload.errors).flat()[0];

            const error = new Error(
                validation ||
                payload.message ||
                'Angel One connection failed.'
            );

            error.code = payload.code;
            throw error;
        }

        return payload;
    }

    function renderHoldingRows() {
        if (!elements.holdingsBody || !elements.search) return;

        const search = elements.search.value.trim().toUpperCase();

        const matches = holdings.filter((holding) =>
            String(holding.symbol).toUpperCase().includes(search)
        );

        if (!matches.length) {
            elements.holdingsBody.innerHTML = `
                <tr>
                    <td colspan="7" class="loading-cell">
                        ${
                            holdings.length
                                ? 'No positions match your search.'
                                : 'No priced positions yet.'
                        }
                    </td>
                </tr>
            `;
            return;
        }

        elements.holdingsBody.innerHTML = matches.map((holding) => {
            const symbol = escapeHtml(holding.symbol);
            const pnl = Number(holding.profit_loss) || 0;
            const pnlPercent = Number(holding.profit_loss_percent) || 0;
            const dayChange = Number(holding.day_change) || 0;

            return `
                <tr>
                    <td>
                        <div class="asset-cell">
                            <span class="asset-mark">${symbol.slice(0, 2)}</span>
                            <span class="asset-name">
                                ${symbol}
                                <span class="asset-sub">Equity · ${symbol}</span>
                            </span>
                        </div>
                    </td>
                    <td class="number-cell">${quantity(holding.quantity)}</td>
                    <td class="number-cell">
                        <span class="value-main">${money(holding.current_price)}</span>
                        <span class="value-sub">Avg. ${money(holding.average_cost)}</span>
                    </td>
                    <td class="number-cell">${money(holding.market_value)}</td>
                    <td>
                        <span class="pill ${pnl < 0 ? 'negative' : ''}">
                            ${signedMoney(pnl)} ·
                            ${pnlPercent > 0 ? '+' : ''}${pnlPercent.toFixed(2)}%
                        </span>
                    </td>
                    <td>
                        <span class="pill ${dayChange < 0 ? 'negative' : ''}">
                            ${signedMoney(dayChange)}
                        </span>
                    </td>
                    <td>
                        <button
                            class="row-action holding-trade"
                            type="button"
                            data-symbol="${symbol}"
                            title="Record trade for ${symbol}"
                            aria-label="Record trade for ${symbol}"
                        >＋</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderHoldings(data) {
        holdings = data.holdings || [];

        const unpriced = data.unpriced_symbols || [];
        const totals = data.totals || {};

        if (elements.totalValue) {
            elements.totalValue.textContent = money(totals.value);
        }

        if (elements.totalCost) {
            elements.totalCost.textContent = money(totals.cost);
        }

        if (elements.totalReturn) {
            elements.totalReturn.textContent = signedMoney(totals.profit_loss);
        }

        setChange(elements.totalReturnPercent, totals.profit_loss_percent, true);
        setChange(elements.totalDayChange, totals.day_change);

        if (elements.positionsCount) {
            elements.positionsCount.textContent = String(
                holdings.length + unpriced.length
            );
        }

        if (elements.positionsCaption) {
            elements.positionsCaption.textContent = unpriced.length
                ? `${unpriced.length} awaiting market price`
                : 'Stocks in your portfolio';
        }

        const prefix = $('#currency-prefix');
        if (prefix) {
            prefix.textContent = currency.symbol || currency.code || '$';
        }

        if (elements.emptyHoldings) {
            elements.emptyHoldings.hidden =
                holdings.length > 0 || unpriced.length > 0;
        }

        if (elements.holdingsBody) {
            if (!holdings.length && !unpriced.length) {
                elements.holdingsBody.innerHTML = '';
            } else {
                renderHoldingRows();
            }
        }

        if (elements.unpricedNote) {
            elements.unpricedNote.hidden = unpriced.length === 0;
            elements.unpricedNote.textContent = unpriced.length
                ? `Waiting for price data: ${unpriced.map(
                    (position) =>
                        `${position.symbol} (${quantity(position.quantity)} shares)`
                ).join(', ')}.`
                : '';
        }
    }

    function renderActivity(payload) {
        if (!elements.activityBody) return;

        const transactions = payload.data || [];
        const total = payload.meta?.total ?? transactions.length;

        if (elements.activityCount) {
            elements.activityCount.textContent =
                `${total} transaction${total === 1 ? '' : 's'}`;
        }

        if (elements.emptyActivity) {
            elements.emptyActivity.hidden = transactions.length !== 0;
        }

        if (!transactions.length) {
            elements.activityBody.innerHTML = '';
            return;
        }

        elements.activityBody.innerHTML = transactions.map((transaction) => {
            const type = transaction.type?.value || transaction.type;
            const isSell = type === 'sell';
            const symbol = escapeHtml(transaction.symbol);
            const date = new Date(transaction.date);

            const formattedDate = Number.isNaN(date.getTime())
                ? '—'
                : new Intl.DateTimeFormat(undefined, {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                }).format(date);

            return `
                <tr>
                    <td>
                        <span class="trade-type-label">
                            <span class="trade-indicator ${isSell ? 'sell' : ''}">
                                ${isSell ? '↗' : '↙'}
                            </span>
                            ${isSell ? 'Sold' : 'Bought'} <strong>${symbol}</strong>
                        </span>
                    </td>
                    <td>${escapeHtml(formattedDate)}</td>
                    <td class="number-cell">${quantity(transaction.quantity)}</td>
                    <td class="number-cell">${money(transaction.price)}</td>
                    <td class="number-cell activity-total">${money(transaction.total)}</td>
                    <td>
                        <button
                            class="row-action delete-transaction"
                            type="button"
                            data-id="${escapeHtml(transaction.id)}"
                            title="Delete recorded transaction"
                            aria-label="Delete recorded transaction"
                        >×</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function refresh() {
        const button = $('#refresh-data');

        button?.classList.add('spinning');

        try {
            const [summary, history] = await Promise.all([
                api('/portfolio/summary'),
                api('/portfolio'),
            ]);

            currency = summary.data.currency || currency;

            renderHoldings(summary.data);
            renderActivity(history);
        } catch (error) {
            if (elements.holdingsBody) {
                elements.holdingsBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="loading-cell">
                            ${escapeHtml(error.message)}
                        </td>
                    </tr>
                `;
            }

            showToast(error.message, true);
        } finally {
            button?.classList.remove('spinning');
        }
    }

    function formatContracts(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return new Intl.NumberFormat(undefined, {
            maximumFractionDigits: 0,
        }).format(value);
    }

    function prepareAngelOneGreeksView(source = 'angel_one') {
        optionElements.tableHead.innerHTML = `
            <tr class="chain-group-row">
                <th class="call-group" colspan="6">CALLS <span>CALL OPTIONS</span></th>
                <th class="strike-group" rowspan="2">STRIKE</th>
                <th class="put-group" colspan="6">PUTS <span>PUT OPTIONS</span></th>
            </tr>
            <tr class="chain-columns-row">
                <th class="col-call-pcr">Call PCR</th>
                <th class="col-call-vol">Call Vol.</th>
                <th class="col-call-oichg">Call OI Chg</th>
                <th class="col-call-oichgpct">Call OI Chg %</th>
                <th class="col-call-oi">Call OI (lakh)</th>
                <th class="col-call-ltp">Call LTP</th>
                <th class="col-put-iv">Put IV</th>
                <th class="col-put-ltp">Put LTP</th>
                <th class="col-put-oi">Put OI (lakh)</th>
                <th class="col-put-oichgpct">Put OI Chg %</th>
                <th class="col-put-oichg">Put OI Chg</th>
                <th class="col-put-vol">Put Vol.</th>
            </tr>
        `;

        optionElements.summary.hidden = false;

        const quoteDerived = source === 'calculated_from_live_quotes';

        optionElements.badge.innerHTML = quoteDerived
            ? '<span></span> ANGEL ONE · QUOTE-DERIVED GREEKS'
            : '<span></span> ANGEL ONE · LIVE GREEKS';

        optionElements.badge.classList.add('live-badge');

        optionElements.description.textContent = quoteDerived
            ? 'Sensibull-style Option Chain calculated from Angel One quotes for the selected underlying and expiry.'
            : 'Sensibull-style Option Chain & Live Quotes from Angel One by strike.';

        optionElements.footerNote.textContent = quoteDerived
            ? 'Open interest and volumes in lakhs/contracts from Angel One quotes. Stale or illiquid quotes can affect values.'
            : 'LTP, OI change, and volume are from Angel One live market quotes.';
    }

    function restoreDemoOptionChain() {
        optionElements.tableHead.innerHTML = `
            <tr class="chain-group-row">
                <th class="call-group" colspan="6">CALLS <span>CALL OPTIONS</span></th>
                <th class="strike-group" rowspan="2">STRIKE</th>
                <th class="put-group" colspan="6">PUTS <span>PUT OPTIONS</span></th>
            </tr>
            <tr class="chain-columns-row">
                <th class="col-call-pcr">Call PCR</th>
                <th class="col-call-vol">Call Vol.</th>
                <th class="col-call-oichg">Call OI Chg</th>
                <th class="col-call-oichgpct">Call OI Chg %</th>
                <th class="col-call-oi">Call OI (lakh)</th>
                <th class="col-call-ltp">Call LTP</th>
                <th class="col-put-iv">Put IV</th>
                <th class="col-put-ltp">Put LTP</th>
                <th class="col-put-oi">Put OI (lakh)</th>
                <th class="col-put-oichgpct">Put OI Chg %</th>
                <th class="col-put-oichg">Put OI Chg</th>
                <th class="col-put-vol">Put Vol.</th>
            </tr>
        `;
        optionElements.summary.hidden = false;
        optionElements.badge.innerHTML = '<span></span> SIMULATED DATA';
        optionElements.badge.classList.remove('live-badge');

        optionElements.description.textContent =
            'Explore call and put contracts by strike and expiry. Quotes below are illustrative sample data, not live prices.';

        optionElements.footerNote.textContent =
            'Illustrative options data for interface preview only. Not for trading or investment decisions.';
    }

    function renderAngelOneGreeks(response) {
        const rows = response.data || [];

        if (
            typeof response.expiry_date === 'string' &&
            Array.from(optionElements.expiry.options).some(
                (option) => option.value === response.expiry_date
            )
        ) {
            optionElements.expiry.value = response.expiry_date;
        }

        prepareAngelOneGreeksView(response.greeks_source);

        optionElements.symbol.textContent =
            `${response.underlying} · ${response.expiry}`;

        optionElements.updated.textContent =
            `Angel One · ${new Intl.DateTimeFormat(undefined, {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
            }).format(new Date())}`;

        if (!rows.length) {
            optionElements.body.innerHTML = `
                <tr>
                    <td colspan="13" class="chain-error">
                        Angel One returned no live option data for this underlying and expiry.
                    </td>
                </tr>
            `;
            return;
        }

        const toLakhs = (qty) => {
            if (qty == null || qty === '' || isNaN(qty)) return '—';
            const val = Number(qty) / 100000;
            return val.toFixed(1);
        };

        const formatPercent = (val) => {
            if (val == null || val === '' || isNaN(val)) return '—';
            const num = Number(val);
            return `${num > 0 ? '+' : ''}${num.toFixed(1)}%`;
        };

        // Group rows by strike into { [strike]: { call: {...}, put: {...} } }
        const strikeMap = new Map();
        let totalCallOi = 0;
        let totalPutOi = 0;
        let maxCallOi = 1;
        let maxPutOi = 1;

        rows.forEach((row) => {
            const strike = Number(row.strike);
            if (!strikeMap.has(strike)) {
                strikeMap.set(strike, { strike, call: null, put: null });
            }
            const item = strikeMap.get(strike);
            const oi = Number(row.open_interest) || 0;
            if (row.option_type === 'CE') {
                item.call = row;
                totalCallOi += oi;
                if (oi > maxCallOi) maxCallOi = oi;
            } else if (row.option_type === 'PE') {
                item.put = row;
                totalPutOi += oi;
                if (oi > maxPutOi) maxPutOi = oi;
            }
        });

        // Sort strikes ascending
        const sortedStrikes = Array.from(strikeMap.values()).sort(
            (a, b) => a.strike - b.strike
        );

        // Update summary metrics
        optionElements.callOi.textContent = formatContracts(totalCallOi);
        optionElements.putOi.textContent = formatContracts(totalPutOi);
        optionElements.pcr.textContent = totalCallOi ? (totalPutOi / totalCallOi).toFixed(2) : '—';

        // Find ATM strike if underlying spot can be estimated or if delta ~ 0.5
        let spotPrice = null;
        for (const s of sortedStrikes) {
            if (s.call?.market_data?.close || s.call?.market_data?.ltp) {
                if (s.call.delta != null && Math.abs(s.call.delta - 0.5) < 0.08) {
                    spotPrice = s.strike;
                    break;
                }
            }
        }
        if (spotPrice) {
            optionElements.spot.textContent = optionMoney(spotPrice);
        }

        optionElements.body.innerHTML = sortedStrikes.map((s) => {
            const c = s.call || {};
            const p = s.put || {};
            const isAtm = spotPrice ? Math.abs(s.strike - spotPrice) < 50 : (c.delta != null && Math.abs(c.delta - 0.5) < 0.04);
            const isCallItm = spotPrice ? s.strike < spotPrice : (c.delta != null && c.delta > 0.5);
            const isPutItm = spotPrice ? s.strike > spotPrice : (p.delta != null && Math.abs(p.delta) > 0.5);
            const callDepth = c.open_interest ? Math.round((c.open_interest / maxCallOi) * 100) : 0;
            const putDepth = p.open_interest ? Math.round((p.open_interest / maxPutOi) * 100) : 0;

            // Call PCR (Strike PCR = Put OI / Call OI)
            const cOi = Number(c.open_interest) || 0;
            const pOi = Number(p.open_interest) || 0;
            const strikePcr = cOi > 0 ? (pOi / cOi).toFixed(1) : (pOi > 0 ? '—' : '—');

            // Volumes in thousands (k) or contracts (e.g. Sensibull shows volume in thousands)
            const formatVol = (v) => {
                if (v == null || v === '' || isNaN(v)) return '—';
                const num = Number(v);
                if (num >= 1000) return (num / 1000).toFixed(num >= 10000 ? 0 : 1);
                return num.toString();
            };
            const cVol = formatVol(c.trade_volume);
            const pVol = formatVol(p.trade_volume);

            // OI Change in Lakhs and OI Change %
            // opnInterest is today's current OI.
            // If historical/prior OI is provided or estimated via opnInterestChg:
            const cOiChgRaw = c.market_data?.opnInterestChg != null 
                ? Number(c.market_data.opnInterestChg) 
                : (c.oi_change != null ? Number(c.oi_change) : null);
            const pOiChgRaw = p.market_data?.opnInterestChg != null 
                ? Number(p.market_data.opnInterestChg) 
                : (p.oi_change != null ? Number(p.oi_change) : null);

            const cOiChgLakh = cOiChgRaw != null ? (cOiChgRaw / 100000).toFixed(1) : '—';
            const pOiChgLakh = pOiChgRaw != null ? (pOiChgRaw / 100000).toFixed(1) : '—';

            // Percent change in OI: (OI Chg / (Current OI - OI Chg)) * 100
            const calcOiChgPct = (currOi, chg) => {
                if (chg == null || !currOi) return null;
                const prevOi = currOi - chg;
                if (prevOi <= 0) return 0;
                return (chg / prevOi) * 100;
            };

            const cOiPctVal = c.oi_change_pct != null 
                ? Number(c.oi_change_pct) 
                : (cOiChgRaw != null ? calcOiChgPct(cOi, cOiChgRaw) : null);
            const pOiPctVal = p.oi_change_pct != null 
                ? Number(p.oi_change_pct) 
                : (pOiChgRaw != null ? calcOiChgPct(pOi, pOiChgRaw) : null);

            const cOiChgPctStr = cOiPctVal != null ? `${cOiPctVal > 0 ? '+' : ''}${cOiPctVal.toFixed(1)}%` : '—';
            const pOiChgPctStr = pOiPctVal != null ? `${pOiPctVal > 0 ? '+' : ''}${pOiPctVal.toFixed(1)}%` : '—';

            const cLtp = c.ltp != null ? Number(c.ltp).toFixed(2) : (c.market_data?.ltp != null ? Number(c.market_data.ltp).toFixed(2) : '—');
            const pLtp = p.ltp != null ? Number(p.ltp).toFixed(2) : (p.market_data?.ltp != null ? Number(p.market_data.ltp).toFixed(2) : '—');

            const pIv = p.implied_volatility != null ? Number(p.implied_volatility).toFixed(1) : '—';

            const callPnlClass = (c.market_data?.netChange ?? 0) < 0 ? 'negative-cell' : 'positive-cell';
            const putPnlClass = (p.market_data?.netChange ?? 0) < 0 ? 'negative-cell' : 'positive-cell';

            return `
                <tr class="${isAtm ? 'atm-row' : ''}">
                    <!-- CALLS: Call PCR, Call Vol., Call OI Chg, Call OI Chg %, Call OI (lakh), Call LTP -->
                    <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'}">${strikePcr}</td>
                    <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'}">${cVol}</td>
                    <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'} ${(Number(cOiChgLakh) || 0) < 0 ? 'negative-cell' : 'positive-cell'}">${cOiChgLakh}</td>
                    <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'} ${(cOiPctVal || 0) < 0 ? 'negative-cell' : 'positive-cell'}">${cOiChgPctStr}</td>
                    <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'} call-cell" style="--depth:${callDepth}%">${toLakhs(c.open_interest)}</td>
                    <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'} ${callPnlClass}"><strong>${cLtp}</strong></td>

                    <!-- STRIKE (CENTER) -->
                    <td class="strike-cell ${isAtm ? 'atm-strike-active' : ''}">${formatContracts(s.strike)}${isAtm ? ' · ATM' : ''}</td>

                    <!-- PUTS: Put IV, Put LTP, Put OI (lakh), Put OI Chg %, Put OI Chg, Put Vol. -->
                    <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'}">${pIv}</td>
                    <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'} ${putPnlClass}"><strong>${pLtp}</strong></td>
                    <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'} put-cell" style="--depth:${putDepth}%">${toLakhs(p.open_interest)}</td>
                    <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'} ${(pOiPctVal || 0) < 0 ? 'negative-cell' : 'positive-cell'}">${pOiChgPctStr}</td>
                    <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'} ${(Number(pOiChgLakh) || 0) < 0 ? 'negative-cell' : 'positive-cell'}">${pOiChgLakh}</td>
                    <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'}">${pVol}</td>
                </tr>
            `;
        }).join('');
    }

    async function refreshOptionChain() {
        const provider = window.optionChainProvider;
        const underlying = optionElements.underlying.value;
        const expiry = optionElements.expiry.value;
        const requestId = ++optionChainRequestId;

        optionElements.body.innerHTML = `
            <tr>
                <td colspan="${angelOneConnected ? 13 : 11}" class="chain-loading">
                    <span class="spinner"></span>
                    Loading ${angelOneConnected ? 'live Angel One option data' : 'simulated option chain'}…
                </td>
            </tr>
        `;

        try {
            if (angelOneConnected) {
                prepareAngelOneGreeksView(
                    ['SENSEX', 'CRUDEOIL'].includes(underlying)
                        ? 'calculated_from_live_quotes'
                        : 'angel_one'
                );

                if (!expiry) {
                    optionElements.body.innerHTML = `
                        <tr>
                            <td colspan="13" class="chain-error">
                                Choose an expiry listed by Angel One for this underlying.
                            </td>
                        </tr>
                    `;

                    optionElements.updated.textContent = 'Waiting for an expiry';
                    return;
                }

                const liveData = await postAngelOne(
                    config.angelOne.greeksUrl,
                    { underlying, expiry }
                );

                if (requestId !== optionChainRequestId) return;

                renderAngelOneGreeks(liveData);
                return;
            }

            if (!provider) {
                throw new Error('Option-chain demo provider is unavailable.');
            }

            restoreDemoOptionChain();

            const chain = await provider.getChain(underlying, expiry);

            if (requestId !== optionChainRequestId) return;

            const callOi = chain.rows.reduce(
                (total, row) => total + row.call.oi, 0
            );

            const putOi = chain.rows.reduce(
                (total, row) => total + row.put.oi, 0
            );

            const maxCallOi = Math.max(
                1,
                ...chain.rows.map((row) => row.call.oi)
            );

            const maxPutOi = Math.max(
                1,
                ...chain.rows.map((row) => row.put.oi)
            );

            optionElements.spot.textContent = optionMoney(chain.spot);
            optionElements.symbol.textContent = `${chain.label} · SAMPLE`;

            optionElements.pcr.textContent =
                callOi ? (putOi / callOi).toFixed(2) : '—';

            optionElements.callOi.textContent = formatContracts(callOi);
            optionElements.putOi.textContent = formatContracts(putOi);

            optionElements.updated.textContent =
                `Sample snapshot · ${new Intl.DateTimeFormat(undefined, {
                    hour: 'numeric',
                    minute: '2-digit',
                }).format(new Date())}`;

            optionElements.body.innerHTML = chain.rows.map((row) => {
                const callDepth = Math.round((row.call.oi / maxCallOi) * 100);
                const putDepth = Math.round((row.put.oi / maxPutOi) * 100);
                const strikePcr = row.call.oi > 0 ? (row.put.oi / row.call.oi).toFixed(1) : '—';
                const cVol = (row.call.volume / 1000).toFixed(0);
                const pVol = (row.put.volume / 1000).toFixed(0);
                const cOiChgLakh = (row.call.oiChange / 100000).toFixed(1);
                const pOiChgLakh = (row.put.oiChange / 100000).toFixed(1);
                const cOiChgPct = row.call.oi ? ((row.call.oiChange / row.call.oi) * 100).toFixed(1) : 0;
                const pOiChgPct = row.put.oi ? ((row.put.oiChange / row.put.oi) * 100).toFixed(1) : 0;
                const cOiLakh = (row.call.oi / 100000).toFixed(1);
                const pOiLakh = (row.put.oi / 100000).toFixed(1);

                const isCallItm = chain.spot ? row.strike < chain.spot : false;
                const isPutItm = chain.spot ? row.strike > chain.spot : false;

                return `
                    <tr class="${row.isAtm ? 'atm-row' : ''}">
                        <!-- CALLS: Call PCR, Call Vol., Call OI Chg, Call OI Chg %, Call OI (lakh), Call LTP -->
                        <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'}">${strikePcr}</td>
                        <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'}">${cVol}</td>
                        <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'}">${cOiChgLakh}</td>
                        <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'} ${cOiChgPct < 0 ? 'negative-cell' : 'positive-cell'}">${cOiChgPct > 0 ? '+' : ''}${cOiChgPct}%</td>
                        <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'} call-cell" style="--depth:${callDepth}%">${cOiLakh}</td>
                        <td class="cell-call ${isCallItm ? 'zone-itm' : 'zone-otm'}"><strong>${Number(row.call.ltp).toFixed(2)}</strong></td>

                        <!-- STRIKE -->
                        <td class="strike-cell ${row.isAtm ? 'atm-strike-active' : ''}">${formatContracts(row.strike)}${row.isAtm ? ' · ATM' : ''}</td>

                        <!-- PUTS: Put IV, Put LTP, Put OI (lakh), Put OI Chg %, Put OI Chg, Put Vol. -->
                        <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'}">${Number(row.put.iv).toFixed(1)}</td>
                        <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'}"><strong>${Number(row.put.ltp).toFixed(2)}</strong></td>
                        <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'} put-cell" style="--depth:${putDepth}%">${pOiLakh}</td>
                        <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'} ${pOiChgPct < 0 ? 'negative-cell' : 'positive-cell'}">${pOiChgPct > 0 ? '+' : ''}${pOiChgPct}%</td>
                        <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'}">${pOiChgLakh}</td>
                        <td class="cell-put ${isPutItm ? 'zone-itm' : 'zone-otm'}">${pVol}</td>
                    </tr>
                `;
            }).join('');
        } catch (error) {
            if (requestId !== optionChainRequestId) return;

            optionElements.body.innerHTML = `
                <tr>
                    <td colspan="13" class="chain-error">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;

            showToast(error.message, true);
        }
    }

    async function loadAngelOneExpiries() {
        const underlying = optionElements.underlying.value;
        const previousExpiry = optionElements.expiry.value;
        const requestId = ++optionExpiryRequestId;

        ++optionChainRequestId;

        optionElements.expiry.disabled = true;
        optionElements.expiry.replaceChildren(
            new Option('Loading expiries…', '')
        );

        optionElements.body.innerHTML = `
            <tr>
                <td colspan="10" class="chain-loading">
                    <span class="spinner"></span> Loading listed expiries…
                </td>
            </tr>
        `;

        try {
            const url = new URL(
                config.angelOne.expiriesUrl,
                window.location.origin
            );

            url.searchParams.set('underlying', underlying);

            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validation =
                    payload.errors &&
                    Object.values(payload.errors).flat()[0];

                throw new Error(
                    validation ||
                    payload.message ||
                    'Could not load listed option expiries.'
                );
            }

            if (
                requestId !== optionExpiryRequestId ||
                underlying !== optionElements.underlying.value
            ) {
                return;
            }

            const expiries = payload.expiries || [];

            if (!expiries.length) {
                throw new Error(
                    `Angel One currently lists no option expiries for ${underlying}.`
                );
            }

            optionElements.expiry.replaceChildren(
                ...expiries.map(
                    (expiry) => new Option(expiry.label, expiry.date)
                )
            );

            optionElements.expiry.value = expiries.some(
                (expiry) => expiry.date === previousExpiry
            )
                ? previousExpiry
                : expiries[0].date;

            optionElements.expiry.disabled = false;

            startLiveSubscription();
            await refreshOptionChain();
        } catch (error) {
            if (
                requestId !== optionExpiryRequestId ||
                underlying !== optionElements.underlying.value
            ) {
                return;
            }

            optionElements.expiry.replaceChildren(
                new Option('Expiries unavailable', '')
            );

            optionElements.expiry.disabled = false;

            optionElements.body.innerHTML = `
                <tr>
                    <td colspan="10" class="chain-error">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;

            optionElements.updated.textContent = 'Could not load expiries';
            showToast(error.message, true);
        }
    }

    async function renewLiveSubscription() {
        if (!angelOneConnected || document.hidden) return;

        const underlying = optionElements.underlying.value;
        const expiry = optionElements.expiry.value;

        if (!expiry) return;

        try {
            await postAngelOne(config.angelOne.subscribeUrl, {
                underlying,
                expiry,
            });

            liveSubscriptionSelection = `${underlying}:${expiry}`;
            liveSubscriptionErrorShown = false;
        } catch (error) {
            if (error.code === 'expiry_unavailable') {
                liveSubscriptionSelection = '';
                await loadAngelOneExpiries();
                return;
            }

            optionElements.updated.textContent = 'Live updates unavailable';

            if (!liveSubscriptionErrorShown) {
                showToast(error.message, true);
                liveSubscriptionErrorShown = true;
            }
        }
    }

    function startLiveSubscription() {
        const expiry = optionElements.expiry.value;

        const selection =
            `${optionElements.underlying.value}:${expiry}`;

        if (!expiry || selection === liveSubscriptionSelection) return;

        liveSubscriptionSelection = '';

        renewLiveSubscription();

        if (!liveSubscriptionTimer) {
            liveSubscriptionTimer = window.setInterval(
                renewLiveSubscription,
                20000
            );
        }
    }

    function setAngelOneConnected(connected, clientCode = '') {
        angelOneConnected = connected;

        angelOneElements.panel.classList.toggle('is-connected', connected);

        angelOneElements.summaryText.textContent = connected
            ? `Connected · ${clientCode}`
            : 'Connect Angel One';

        angelOneElements.status.querySelector('span:last-child').textContent =
            connected
                ? `Angel One connected as ${clientCode} · Live option Greeks enabled`
                : 'Sample feed · Connect Angel One to securely retrieve an API access token';

        angelOneElements.status.hidden = connected;
        angelOneElements.fields.hidden = connected;
        angelOneElements.securityNote.hidden = connected;
        angelOneElements.formActions.hidden = connected;
        angelOneElements.connected.hidden = !connected;
        angelOneElements.disconnectButton.disabled = !connected;

        angelOneElements.connectedMessage.textContent = connected
            ? `Connected as ${clientCode}. Disconnect this account or close this panel to view the chain.`
            : '';

        angelOneElements.connectedMessage.hidden = !connected;

        optionMarketControls.hidden = !connected;
        optionLiveContent.hidden = !connected;
        optionConnectPrompt.hidden = connected;
    }

    function initializeOptionChain() {
        const provider = window.optionChainProvider;

        if (!angelOneConnected && !provider) {
            optionElements.body.innerHTML = `
                <tr>
                    <td colspan="11" class="chain-error">
                        Option-chain demo provider is unavailable.
                    </td>
                </tr>
            `;
            return;
        }

        if (optionChainInitialized) {
            if (angelOneConnected) {
                loadAngelOneExpiries();
            } else {
                refreshOptionChain();
            }
            return;
        }

        optionChainInitialized = true;
        optionElements.underlying.value = 'NIFTY';

        if (!angelOneConnected) {
            const sampleExpiry = provider.getDefaultExpiry();

            optionElements.expiry.replaceChildren(
                new Option(sampleExpiry, sampleExpiry)
            );
        }

        optionElements.underlying.addEventListener('change', () => {
            if (angelOneConnected) {
                loadAngelOneExpiries();
            } else {
                refreshOptionChain();
            }
        });

        optionElements.expiry.addEventListener('change', () => {
            startLiveSubscription();
            refreshOptionChain();
        });

        $('#refresh-option-chain').addEventListener('click', () => {
            if (angelOneConnected) {
                loadAngelOneExpiries();
            } else {
                refreshOptionChain();
            }
        });

        if (angelOneConnected) {
            loadAngelOneExpiries();
        } else {
            refreshOptionChain();
        }
    }

    function showOptionChain() {
        const section = $('#option-chain');

        if (!section) {
            console.error('Option Chain section #option-chain was not found.');
            return;
        }

        section.hidden = false;
        document.body.classList.add('option-chain-focus');

        document.querySelectorAll('.sidebar .side-link').forEach((link) => {
            link.classList.toggle(
                'active',
                link.getAttribute('href') === '#option-chain'
            );
        });

        initializeOptionChain();
    }

    function restoreNavigationFromHash() {
        const hash = window.location.hash || '#holdings';

        $('#option-chain').hidden = hash !== '#option-chain';

        document.body.classList.toggle(
            'option-chain-focus',
            hash === '#option-chain'
        );

        document.querySelectorAll('.sidebar .side-link').forEach((link) => {
            link.classList.toggle(
                'active',
                link.getAttribute('href') === hash
            );
        });

        if (hash === '#option-chain') {
            showOptionChain();
        }
    }

    const optionLink = $('.sidebar .side-link[href="#option-chain"]');

    optionLink?.addEventListener('click', (event) => {
        event.preventDefault();

        if (window.location.hash !== '#option-chain') {
            window.history.pushState(null, '', '#option-chain');
        }

        showOptionChain();
    });

    document.querySelectorAll(
        '.sidebar .side-link[href="#holdings"], ' +
        '.sidebar .side-link[href="#activity"]'
    ).forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();

            const hash = link.getAttribute('href');

            if (window.location.hash !== hash) {
                window.history.pushState(null, '', hash);
            }

            restoreNavigationFromHash();
        });
    });

    window.addEventListener('popstate', restoreNavigationFromHash);
    window.addEventListener('hashchange', restoreNavigationFromHash);

    window.addEventListener('marketboard:live-greeks', (event) => {
        const update = event.detail;

        if (
            !angelOneConnected ||
            update.underlying !== optionElements.underlying.value ||
            update.expiry_date !== optionElements.expiry.value
        ) {
            return;
        }

        ++optionChainRequestId;
        renderAngelOneGreeks(update);

        if (update.updated_at) {
            optionElements.updated.textContent =
                `Live WebSocket · ${new Intl.DateTimeFormat(undefined, {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                }).format(new Date(update.updated_at))}`;
        }
    });

    window.addEventListener('marketboard:realtime-state', (event) => {
        updateWsStatus(event.detail.current);

        if (event.detail.current === 'connected') {
            startLiveSubscription();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            startLiveSubscription();
        }
    });

    angelOneElements.form.addEventListener('submit', async (event) => {
        event.preventDefault();

        angelOneElements.formError.hidden = true;
        angelOneElements.connectButton.disabled = true;
        angelOneElements.connectButton.textContent = 'Connecting…';

        const credentials = Object.fromEntries(
            new FormData(angelOneElements.form).entries()
        );

        try {
            const result = await postAngelOne(
                config.angelOne.connectUrl,
                credentials
            );

            angelOneElements.form.reset();

            setAngelOneConnected(
                true,
                result.connection.client_code
            );

            angelOneElements.panel.open = false;

            showToast(result.message);

            optionElements.underlying.value = 'NIFTY';

            if (window.location.hash !== '#option-chain') {
                window.history.pushState(null, '', '#option-chain');
            }

            showOptionChain();
        } catch (error) {
            angelOneElements.form.elements.password.value = '';
            angelOneElements.form.elements.totp.value = '';

            angelOneElements.formError.textContent = error.message;
            angelOneElements.formError.hidden = false;
            angelOneElements.panel.open = true;
        } finally {
            angelOneElements.connectButton.disabled = false;
            angelOneElements.connectButton.textContent = 'Connect';
        }
    });

    angelOneElements.disconnectButton.addEventListener('click', async () => {
        angelOneElements.disconnectButton.disabled = true;

        try {
            const result = await postAngelOne(
                config.angelOne.disconnectUrl,
                {}
            );

            setAngelOneConnected(false);

            liveSubscriptionSelection = '';

            window.clearInterval(liveSubscriptionTimer);
            liveSubscriptionTimer = undefined;

            angelOneElements.panel.open = false;

            showToast(result.message);

            const provider = window.optionChainProvider;

            if (provider && window.location.hash === '#option-chain') {
                const sampleExpiry = provider.getDefaultExpiry();

                optionElements.expiry.replaceChildren(
                    new Option(sampleExpiry, sampleExpiry)
                );

                restoreDemoOptionChain();
                await refreshOptionChain();
            }
        } catch (error) {
            showToast(error.message, true);
            angelOneElements.disconnectButton.disabled = false;
        }
    });

    $('.angel-one-close')?.addEventListener('click', () => {
        angelOneElements.panel.open = false;
    });

    if (angelOneConnected) {
        setAngelOneConnected(true, config.angelOne.clientCode || '');
    }

    const today = new Date();

    if ($('#today-date')) {
        $('#today-date').textContent = new Intl.DateTimeFormat(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(today);
    }

    if ($('#footer-year')) {
        $('#footer-year').textContent = today.getFullYear();
    }

    if ($('#trade-date')) {
        $('#trade-date').value = today.toISOString().slice(0, 10);
    }

    function openDialog(type = 'buy', symbol = '') {
        elements.tradeForm.reset();

        elements.tradeForm.elements.date.value =
            new Date().toISOString().slice(0, 10);

        elements.tradeForm.elements.symbol.value = symbol;
        elements.tradeForm.elements.type.value = type;

        document.querySelectorAll('.trade-type').forEach((button) => {
            button.classList.toggle(
                'active',
                button.dataset.type === type
            );
        });

        $('#submit-trade').textContent =
            type === 'buy' ? 'Record buy' : 'Record sell';

        elements.tradeError.hidden = true;
        elements.dialog.showModal();

        elements.tradeForm.elements[symbol ? 'quantity' : 'symbol'].focus();
    }

    $('#open-trade')?.addEventListener('click', () => openDialog());
    $('#empty-trade')?.addEventListener('click', () => openDialog());

    $('.dialog-close')?.addEventListener('click', () => {
        elements.dialog.close();
    });

    $('.dialog-cancel')?.addEventListener('click', () => {
        elements.dialog.close();
    });

    elements.dialog.addEventListener('click', (event) => {
        if (event.target === elements.dialog) {
            elements.dialog.close();
        }
    });

    document.querySelectorAll('.trade-type').forEach((button) => {
        button.addEventListener('click', () => {
            elements.tradeForm.elements.type.value = button.dataset.type;

            document.querySelectorAll('.trade-type').forEach((tab) => {
                tab.classList.toggle('active', tab === button);
            });

            $('#submit-trade').textContent =
                button.dataset.type === 'buy' ? 'Record buy' : 'Record sell';
        });
    });

    elements.search?.addEventListener('input', renderHoldingRows);
    $('#refresh-data')?.addEventListener('click', refresh);

    elements.tradeForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submit = $('#submit-trade');
        const type = elements.tradeForm.elements.type.value;

        const body = {
            symbol: elements.tradeForm.elements.symbol.value
                .trim()
                .toUpperCase(),
            type,
            quantity: elements.tradeForm.elements.quantity.value,
            price: elements.tradeForm.elements.price.value,
            date: elements.tradeForm.elements.date.value,
        };

        submit.disabled = true;
        submit.textContent = 'Saving…';
        elements.tradeError.hidden = true;

        try {
            await api('/portfolio', {
                method: 'POST',
                body: JSON.stringify(body),
            });

            elements.dialog.close();

            showToast(`${body.symbol} ${type} recorded in your portfolio.`);

            await refresh();
        } catch (error) {
            elements.tradeError.textContent = error.message;
            elements.tradeError.hidden = false;
        } finally {
            submit.disabled = false;
            submit.textContent =
                type === 'buy' ? 'Record buy' : 'Record sell';
        }
    });

    elements.holdingsBody.addEventListener('click', (event) => {
        const button = event.target.closest('.holding-trade');

        if (button) {
            openDialog('buy', button.dataset.symbol);
        }
    });

    elements.activityBody.addEventListener('click', async (event) => {
        const button = event.target.closest('.delete-transaction');

        if (!button) return;

        if (
            !window.confirm(
                'Delete this recorded transaction? Your portfolio will be recalculated.'
            )
        ) {
            return;
        }

        button.disabled = true;

        try {
            await api(
                `/portfolio/${encodeURIComponent(button.dataset.id)}`,
                { method: 'DELETE' }
            );

            showToast('Recorded transaction deleted.');
            await refresh();
        } catch (error) {
            showToast(error.message, true);
            button.disabled = false;
        }
    });

    $('.notice-dismiss')?.addEventListener('click', () => {
        $('.notice-banner')?.remove();
    });

    refresh();

    // Initialize the view selected in the URL.
    // Being connected to Angel One alone does not force navigation.
    restoreNavigationFromHash();
})();
