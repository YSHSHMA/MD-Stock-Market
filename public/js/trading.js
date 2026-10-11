(() => {
    const config = window.marketboard;
    const elements = {
        totalValue: document.querySelector('#total-value'),
        totalCost: document.querySelector('#total-cost'),
        totalReturn: document.querySelector('#total-return'),
        totalReturnPercent: document.querySelector('#total-return-percent'),
        totalDayChange: document.querySelector('#total-day-change'),
        positionsCount: document.querySelector('#positions-count'),
        positionsCaption: document.querySelector('#positions-caption'),
        holdingsBody: document.querySelector('#holdings-body'),
        activityBody: document.querySelector('#activity-body'),
        emptyHoldings: document.querySelector('#empty-holdings'),
        emptyActivity: document.querySelector('#empty-activity'),
        unpricedNote: document.querySelector('#unpriced-note'),
        activityCount: document.querySelector('#activity-count'),
        search: document.querySelector('#holding-search'),
        dialog: document.querySelector('#trade-dialog'),
        tradeForm: document.querySelector('#trade-form'),
        tradeError: document.querySelector('#trade-error'),
        toast: document.querySelector('#toast'),
    };

    const optionElements = {
        underlying: document.querySelector('#option-underlying'),
        expiry: document.querySelector('#option-expiry'),
        spot: document.querySelector('#option-spot'),
        symbol: document.querySelector('#option-symbol-label'),
        pcr: document.querySelector('#option-pcr'),
        callOi: document.querySelector('#option-call-oi'),
        putOi: document.querySelector('#option-put-oi'),
        body: document.querySelector('#option-chain-body'),
        tableHead: document.querySelector('.option-chain-table thead'),
        summary: document.querySelector('#option-summary-grid'),
        badge: document.querySelector('#option-chain-badge'),
        description: document.querySelector('#option-chain-description'),
        footerNote: document.querySelector('#option-chain-footer-note'),
        updated: document.querySelector('#option-chain-updated'),
    };
    const demoOptionTableHead = optionElements.tableHead.innerHTML;

    let holdings = [];
    let currency = { code: 'USD', symbol: '$' };
    let toastTimeout;
    let angelOneConnected = config.angelOne.connected;
    let optionChainRequestId = 0;

    const sidebarToggle = document.querySelector('#sidebar-toggle');
    const sidebarCollapsed = localStorage.getItem('marketboard-sidebar-collapsed') === 'true';
    document.querySelector('.app-shell').classList.toggle('sidebar-collapsed', sidebarCollapsed);
    updateSidebarToggle();

    function updateSidebarToggle() {
        const collapsed = document.querySelector('.app-shell').classList.contains('sidebar-collapsed');
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        sidebarToggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        sidebarToggle.querySelector('span').textContent = collapsed ? '›' : '‹';
    }

    const angelOneElements = {
        panel: document.querySelector('#angel-one-connection'),
        summaryText: document.querySelector('#angel-one-summary-text'),
        status: document.querySelector('#angel-one-status'),
        form: document.querySelector('#angel-one-form'),
        fields: document.querySelector('.angel-one-fields'),
        securityNote: document.querySelector('.angel-one-security-note'),
        formActions: document.querySelector('.angel-one-form-actions'),
        formError: document.querySelector('#angel-one-error'),
        connectButton: document.querySelector('#angel-one-connect'),
        connected: document.querySelector('#angel-one-connected'),
        connectedMessage: document.querySelector('#angel-one-connected-message'),
        disconnectButton: document.querySelector('#angel-one-disconnect'),
    };
    const optionMarketControls = document.querySelector('#option-market-controls');
    const optionLiveContent = document.querySelector('#option-live-content');
    const optionConnectPrompt = document.querySelector('#option-connect-prompt');

    function setAngelOneConnected(connected, clientCode = '') {
        angelOneConnected = connected;
        angelOneElements.panel.classList.toggle('is-connected', connected);
        angelOneElements.summaryText.textContent = connected
            ? `Connected · ${clientCode}`
            : 'Connect Angel One';
        angelOneElements.status.querySelector('span:last-child').textContent = connected
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

    async function postAngelOne(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = payload.errors && Object.values(payload.errors).flat()[0];
            throw new Error(validationMessage || payload.message || 'Angel One connection failed.');
        }

        return payload;
    }

    angelOneElements.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        angelOneElements.formError.hidden = true;
        angelOneElements.connectButton.disabled = true;
        angelOneElements.connectButton.textContent = 'Connecting…';

        const credentials = Object.fromEntries(new FormData(angelOneElements.form).entries());
        try {
            const result = await postAngelOne(config.angelOne.connectUrl, credentials);
            angelOneElements.form.reset();
            setAngelOneConnected(true, result.connection.client_code);
            angelOneElements.panel.open = false;
            showToast(result.message);
            if (optionChainInitialized) {
                await refreshOptionChain();
            } else {
                initializeOptionChain();
            }
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
            const result = await postAngelOne(config.angelOne.disconnectUrl, {});
            setAngelOneConnected(false);
            angelOneElements.panel.open = false;
            showToast(result.message);
        } catch (error) {
            showToast(error.message, true);
            angelOneElements.disconnectButton.disabled = false;
        }
    });

    document.querySelector('.angel-one-close').addEventListener('click', () => {
        angelOneElements.panel.open = false;
    });

    if (config.angelOne.connected) {
        setAngelOneConnected(true, config.angelOne.clientCode);
    }

    sidebarToggle.addEventListener('click', () => {
        const shell = document.querySelector('.app-shell');
        shell.classList.toggle('sidebar-collapsed');
        localStorage.setItem('marketboard-sidebar-collapsed', String(shell.classList.contains('sidebar-collapsed')));
        updateSidebarToggle();
    });

    document.querySelector('#today-date').textContent = new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date());
    document.querySelector('#footer-year').textContent = new Date().getFullYear();
    document.querySelector('#trade-date').value = new Date().toISOString().slice(0, 10);

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));

    const money = (value) => new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.code || 'USD',
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);

    const optionMoney = (value) => new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);

    const quantity = (value) => new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 6,
    }).format(Number(value) || 0);

    const signedMoney = (value) => {
        const amount = Number(value) || 0;
        return `${amount > 0 ? '+' : amount < 0 ? '−' : ''}${money(Math.abs(amount))}`;
    };

    const setChange = (element, value, includePercent = false) => {
        const amount = Number(value) || 0;
        element.classList.toggle('negative', amount < 0);
        element.textContent = `${signedMoney(amount)}${includePercent ? ` (${amount > 0 ? '+' : ''}${amount.toFixed(2)}%)` : ''}`;
    };

    async function api(path, options = {}) {
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        };
        const xsrf = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
        if (xsrf && options.method && options.method !== 'GET') {
            headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf.slice('XSRF-TOKEN='.length));
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

        const payload = response.status === 204 ? null : await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = payload.errors && Object.values(payload.errors).flat()[0];
            throw new Error(validation || payload.message || 'The request could not be completed.');
        }
        return payload;
    }

    function renderHoldings(data) {
        holdings = data.holdings || [];
        const unpriced = data.unpriced_symbols || [];
        const totals = data.totals || {};

        elements.totalValue.textContent = money(totals.value);
        elements.totalCost.textContent = money(totals.cost);
        elements.totalReturn.textContent = signedMoney(totals.profit_loss);
        setChange(elements.totalReturnPercent, totals.profit_loss_percent, true);
        setChange(elements.totalDayChange, totals.day_change);
        elements.positionsCount.textContent = String(holdings.length + unpriced.length);
        elements.positionsCaption.textContent = unpriced.length
            ? `${unpriced.length} awaiting market price`
            : 'Stocks in your portfolio';
        document.querySelector('#currency-prefix').textContent = currency.symbol || currency.code || '$';

        if (holdings.length === 0 && unpriced.length === 0) {
            elements.holdingsBody.innerHTML = '';
            elements.emptyHoldings.hidden = false;
        } else {
            elements.emptyHoldings.hidden = true;
            renderHoldingRows();
        }

        elements.unpricedNote.hidden = unpriced.length === 0;
        elements.unpricedNote.textContent = unpriced.length
            ? `Waiting for price data: ${unpriced.map((position) => `${position.symbol} (${quantity(position.quantity)} shares)`).join(', ')}. Record a trade to add a holding, then price data will appear after a market-data update.`
            : '';
    }

    function renderHoldingRows() {
        const search = elements.search.value.trim().toUpperCase();
        const matches = holdings.filter((holding) => holding.symbol.toUpperCase().includes(search));
        if (!matches.length) {
            elements.holdingsBody.innerHTML = `<tr><td colspan="7" class="loading-cell">${holdings.length ? 'No positions match your search.' : 'No priced positions yet.'}</td></tr>`;
            return;
        }

        elements.holdingsBody.innerHTML = matches.map((holding) => {
            const symbol = escapeHtml(holding.symbol);
            const pnl = Number(holding.profit_loss) || 0;
            const pnlPercent = Number(holding.profit_loss_percent) || 0;
            const dayChange = Number(holding.day_change) || 0;
            const tone = pnl < 0 ? 'negative' : '';
            const dayTone = dayChange < 0 ? 'negative' : '';
            return `<tr>
                <td><div class="asset-cell"><span class="asset-mark">${escapeHtml(symbol.slice(0, 2))}</span><span class="asset-name">${symbol}<span class="asset-sub">Equity · ${symbol}</span></span></div></td>
                <td class="number-cell">${quantity(holding.quantity)}</td>
                <td class="number-cell"><span class="value-main">${money(holding.current_price)}</span><span class="value-sub">Avg. ${money(holding.average_cost)}</span></td>
                <td class="number-cell"><span class="value-main">${money(holding.market_value)}</span></td>
                <td><span class="pill ${tone}">${signedMoney(pnl)} · ${pnlPercent > 0 ? '+' : ''}${pnlPercent.toFixed(2)}%</span></td>
                <td><span class="pill ${dayTone}">${signedMoney(dayChange)}</span></td>
                <td><button class="row-action holding-trade" type="button" data-symbol="${symbol}" title="Record trade for ${symbol}" aria-label="Record trade for ${symbol}">＋</button></td>
            </tr>`;
        }).join('');
    }

    function renderActivity(payload) {
        const transactions = payload.data || [];
        elements.activityCount.textContent = `${payload.meta?.total ?? transactions.length} transaction${(payload.meta?.total ?? transactions.length) === 1 ? '' : 's'}`;
        elements.emptyActivity.hidden = transactions.length !== 0;
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
                : new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
            return `<tr>
                <td><span class="trade-type-label"><span class="trade-indicator ${isSell ? 'sell' : ''}">${isSell ? '↗' : '↙'}</span>${isSell ? 'Sold' : 'Bought'} <strong>${symbol}</strong></span></td>
                <td>${escapeHtml(formattedDate)}</td>
                <td class="number-cell">${quantity(transaction.quantity)}</td>
                <td class="number-cell">${money(transaction.price)}</td>
                <td class="number-cell activity-total">${money(transaction.total)}</td>
                <td><button class="row-action delete-transaction" type="button" data-id="${escapeHtml(transaction.id)}" title="Delete recorded transaction" aria-label="Delete recorded ${isSell ? 'sell' : 'buy'} of ${symbol}">×</button></td>
            </tr>`;
        }).join('');
    }

    function formatContracts(value) {
        return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(value);
    }

    function prepareAngelOneGreeksView() {
        optionElements.tableHead.innerHTML = '<tr class="chain-columns-row"><th>TYPE</th><th>STRIKE</th><th>DELTA</th><th>GAMMA</th><th>THETA</th><th>VEGA</th><th>IV</th><th>VOLUME</th></tr>';
        optionElements.summary.hidden = true;
        optionElements.badge.innerHTML = '<span></span> ANGEL ONE · LIVE GREEKS';
        optionElements.badge.classList.add('live-badge');
        optionElements.description.textContent = 'Live option Greeks by strike for the selected underlying and expiry.';
        optionElements.footerNote.textContent = 'Angel One Greeks endpoint does not provide LTP, open interest, or underlying spot. Values are market data; verify before trading.';
    }

    function renderAngelOneGreeks(response) {
        const rows = response.data || [];
        prepareAngelOneGreeksView();
        optionElements.symbol.textContent = `${response.underlying} · ${response.expiry}`;
        optionElements.updated.textContent = `Angel One · ${new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date())}`;

        if (!rows.length) {
            optionElements.body.innerHTML = '<tr><td colspan="8" class="chain-error">Angel One returned no option Greeks for this underlying and expiry.</td></tr>';
            return;
        }

        const number = (value) => Number(value).toLocaleString(undefined, {
            maximumFractionDigits: 6,
        });
        optionElements.body.innerHTML = rows.map((row) => `<tr>
            <td>${row.option_type}</td>
            <td class="strike-cell">${formatContracts(row.strike)}</td>
            <td>${number(row.delta)}</td>
            <td>${number(row.gamma)}</td>
            <td>${number(row.theta)}</td>
            <td>${number(row.vega)}</td>
            <td>${number(row.implied_volatility)}%</td>
            <td>${formatContracts(row.trade_volume)}</td>
        </tr>`).join('');
    }

    function restoreDemoOptionChain() {
        optionElements.tableHead.innerHTML = demoOptionTableHead;
        optionElements.summary.hidden = false;
        optionElements.badge.innerHTML = '<span></span> SIMULATED DATA';
        optionElements.badge.classList.remove('live-badge');
        optionElements.description.textContent = 'Explore call and put contracts by strike and expiry. Quotes below are illustrative sample data, not live prices.';
        optionElements.footerNote.textContent = 'Illustrative options data for interface preview only. Not for trading or investment decisions.';
    }

    async function refreshOptionChain() {
        const provider = window.optionChainProvider;
        const underlying = optionElements.underlying.value;
        const expiry = optionElements.expiry.value;
        const requestId = ++optionChainRequestId;

        optionElements.body.innerHTML = `<tr><td colspan="${angelOneConnected ? 8 : 11}" class="chain-loading"><span class="spinner"></span> Loading ${angelOneConnected ? 'live Angel One Greeks' : 'simulated option chain'}…</td></tr>`;

        try {
            if (angelOneConnected) {
                prepareAngelOneGreeksView();
                if (!expiry) {
                    optionElements.body.innerHTML = '<tr><td colspan="8" class="chain-error">Choose an expiry listed by Angel One for this underlying.</td></tr>';
                    optionElements.updated.textContent = 'Waiting for an expiry';
                    return;
                }
                const liveData = await postAngelOne(config.angelOne.greeksUrl, {
                    underlying,
                    expiry,
                });
                if (requestId !== optionChainRequestId) return;
                renderAngelOneGreeks(liveData);
                return;
            }

            restoreDemoOptionChain();
            const chain = await provider.getChain(underlying, expiry);
            if (requestId !== optionChainRequestId) return;
            const callOi = chain.rows.reduce((total, row) => total + row.call.oi, 0);
            const putOi = chain.rows.reduce((total, row) => total + row.put.oi, 0);
            const maxCallOi = Math.max(...chain.rows.map((row) => row.call.oi));
            const maxPutOi = Math.max(...chain.rows.map((row) => row.put.oi));

            optionElements.spot.textContent = optionMoney(chain.spot);
            optionElements.symbol.textContent = `${chain.label} · SAMPLE`;
            optionElements.pcr.textContent = (putOi / callOi).toFixed(2);
            optionElements.callOi.textContent = formatContracts(callOi);
            optionElements.putOi.textContent = formatContracts(putOi);
            optionElements.updated.textContent = `Sample snapshot · ${new Intl.DateTimeFormat(undefined, {
                hour: 'numeric',
                minute: '2-digit',
            }).format(new Date())}`;
            optionElements.body.innerHTML = chain.rows.map((row) => {
                const callDepth = Math.round((row.call.oi / maxCallOi) * 100);
                const putDepth = Math.round((row.put.oi / maxPutOi) * 100);
                const callChangeClass = row.call.oiChange < 0 ? 'negative-cell' : 'positive-cell';
                const putChangeClass = row.put.oiChange < 0 ? 'negative-cell' : 'positive-cell';
                return `<tr class="${row.isAtm ? 'atm-row' : ''}">
                    <td class="call-cell" style="--depth:${callDepth}%">${formatContracts(row.call.oi)}</td>
                    <td class="${callChangeClass}">${row.call.oiChange > 0 ? '+' : ''}${formatContracts(row.call.oiChange)}</td>
                    <td>${formatContracts(row.call.volume)}</td>
                    <td>${row.call.iv.toFixed(1)}%</td>
                    <td><strong>${optionMoney(row.call.ltp)}</strong></td>
                    <td class="strike-cell">${formatContracts(row.strike)}${row.isAtm ? ' · ATM' : ''}</td>
                    <td><strong>${optionMoney(row.put.ltp)}</strong></td>
                    <td>${row.put.iv.toFixed(1)}%</td>
                    <td>${formatContracts(row.put.volume)}</td>
                    <td class="${putChangeClass}">${row.put.oiChange > 0 ? '+' : ''}${formatContracts(row.put.oiChange)}</td>
                    <td class="put-cell" style="--depth:${putDepth}%">${formatContracts(row.put.oi)}</td>
                </tr>`;
            }).join('');
        } catch (error) {
            if (requestId !== optionChainRequestId) return;
            optionElements.body.innerHTML = `<tr><td colspan="${angelOneConnected ? 8 : 11}" class="chain-error">${escapeHtml(error.message)}</td></tr>`;
            showToast(error.message, true);
        }
    }

    let optionChainInitialized = false;

    function initializeOptionChain() {
        const provider = window.optionChainProvider;
        if (!provider) {
            optionElements.body.innerHTML = '<tr><td colspan="11" class="chain-error">Option-chain demo provider is unavailable.</td></tr>';
            return;
        }

        if (optionChainInitialized) {
            refreshOptionChain();
            return;
        }

        optionChainInitialized = true;
        const localDate = new Date();
        optionElements.expiry.min = `${localDate.getFullYear()}-${String(localDate.getMonth() + 1).padStart(2, '0')}-${String(localDate.getDate()).padStart(2, '0')}`;
        optionElements.underlying.value = 'NIFTY';
        optionElements.expiry.value = provider.getDefaultExpiry();

        optionElements.underlying.addEventListener('change', refreshOptionChain);
        optionElements.expiry.addEventListener('change', refreshOptionChain);
        document.querySelector('#refresh-option-chain').addEventListener('click', refreshOptionChain);
        if (angelOneConnected) refreshOptionChain();
    }

    function showOptionChain() {
        const section = document.querySelector('#option-chain');
        section.hidden = false;
        document.body.classList.add('option-chain-focus');
        document.querySelectorAll('.sidebar .side-link').forEach((link) => {
            link.classList.toggle('active', link.getAttribute('href') === '#option-chain');
        });
        initializeOptionChain();
    }

    document.querySelector('.sidebar .side-link[href="#option-chain"]').addEventListener('click', (event) => {
        event.preventDefault();
        if (window.location.hash !== '#option-chain') {
            window.history.pushState(null, '', '#option-chain');
        }
        showOptionChain();
    });

    document.querySelectorAll('.sidebar .side-link[href="#holdings"], .sidebar .side-link[href="#activity"]').forEach((link) => {
        link.addEventListener('click', () => {
            document.querySelector('#option-chain').hidden = true;
            document.body.classList.remove('option-chain-focus');
            document.querySelectorAll('.sidebar .side-link').forEach((item) => {
                item.classList.toggle('active', item === link);
            });
        });
    });

    function restoreNavigationFromHash() {
        if (window.location.hash === '#option-chain') {
            showOptionChain();
            return;
        }

        document.querySelector('#option-chain').hidden = true;
        document.body.classList.remove('option-chain-focus');
        document.querySelectorAll('.sidebar .side-link').forEach((link) => {
            link.classList.toggle('active', link.getAttribute('href') === window.location.hash || (!window.location.hash && link.getAttribute('href') === window.location.pathname));
        });
    }

    window.addEventListener('popstate', restoreNavigationFromHash);
    window.addEventListener('hashchange', restoreNavigationFromHash);

    async function refresh() {
        const button = document.querySelector('#refresh-data');
        button.classList.add('spinning');
        try {
            const [summary, history] = await Promise.all([
                api('/portfolio/summary'),
                api('/portfolio'),
            ]);
            currency = summary.data.currency || currency;
            renderHoldings(summary.data);
            renderActivity(history);
        } catch (error) {
            elements.holdingsBody.innerHTML = `<tr><td colspan="7" class="loading-cell">${escapeHtml(error.message)}</td></tr>`;
            showToast(error.message, true);
        } finally {
            button.classList.remove('spinning');
        }
    }

    function showToast(message, isError = false) {
        window.clearTimeout(toastTimeout);
        elements.toast.textContent = message;
        elements.toast.classList.toggle('toast-error', isError);
        elements.toast.classList.add('visible');
        toastTimeout = window.setTimeout(() => elements.toast.classList.remove('visible'), 3400);
    }

    function openDialog(type = 'buy', symbol = '') {
        elements.tradeForm.reset();
        elements.tradeForm.elements.date.value = new Date().toISOString().slice(0, 10);
        elements.tradeForm.elements.symbol.value = symbol;
        elements.tradeForm.elements.type.value = type;
        document.querySelectorAll('.trade-type').forEach((button) => {
            button.classList.toggle('active', button.dataset.type === type);
        });
        document.querySelector('#submit-trade').textContent = type === 'buy' ? 'Record buy' : 'Record sell';
        elements.tradeError.hidden = true;
        elements.dialog.showModal();
        if (symbol) {
            elements.tradeForm.elements.quantity.focus();
        } else {
            elements.tradeForm.elements.symbol.focus();
        }
    }

    document.querySelector('#open-trade').addEventListener('click', () => openDialog());
    document.querySelector('#empty-trade').addEventListener('click', () => openDialog());
    document.querySelector('.dialog-close').addEventListener('click', () => elements.dialog.close());
    document.querySelector('.dialog-cancel').addEventListener('click', () => elements.dialog.close());
    elements.dialog.addEventListener('click', (event) => {
        if (event.target === elements.dialog) elements.dialog.close();
    });
    document.querySelectorAll('.trade-type').forEach((button) => {
        button.addEventListener('click', () => {
            elements.tradeForm.elements.type.value = button.dataset.type;
            document.querySelectorAll('.trade-type').forEach((tab) => tab.classList.toggle('active', tab === button));
            document.querySelector('#submit-trade').textContent = button.dataset.type === 'buy' ? 'Record buy' : 'Record sell';
        });
    });
    elements.search.addEventListener('input', renderHoldingRows);
    document.querySelector('#refresh-data').addEventListener('click', refresh);

    elements.tradeForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = document.querySelector('#submit-trade');
        const type = elements.tradeForm.elements.type.value;
        const body = {
            symbol: elements.tradeForm.elements.symbol.value.trim().toUpperCase(),
            type,
            quantity: elements.tradeForm.elements.quantity.value,
            price: elements.tradeForm.elements.price.value,
            date: elements.tradeForm.elements.date.value,
        };
        submit.disabled = true;
        submit.textContent = 'Saving…';
        elements.tradeError.hidden = true;
        try {
            await api('/portfolio', { method: 'POST', body: JSON.stringify(body) });
            elements.dialog.close();
            showToast(`${body.symbol} ${type} recorded in your portfolio.`);
            await refresh();
        } catch (error) {
            elements.tradeError.textContent = error.message;
            elements.tradeError.hidden = false;
        } finally {
            submit.disabled = false;
            submit.textContent = type === 'buy' ? 'Record buy' : 'Record sell';
        }
    });

    elements.holdingsBody.addEventListener('click', (event) => {
        const button = event.target.closest('.holding-trade');
        if (button) openDialog('buy', button.dataset.symbol);
    });

    elements.activityBody.addEventListener('click', async (event) => {
        const button = event.target.closest('.delete-transaction');
        if (!button) return;
        if (!window.confirm('Delete this recorded transaction? Your portfolio will be recalculated.')) return;
        button.disabled = true;
        try {
            await api(`/portfolio/${encodeURIComponent(button.dataset.id)}`, { method: 'DELETE' });
            showToast('Recorded transaction deleted.');
            await refresh();
        } catch (error) {
            showToast(error.message, true);
            button.disabled = false;
        }
    });

    document.querySelector('.notice-dismiss').addEventListener('click', () => {
        document.querySelector('.notice-banner').remove();
    });

    refresh();
    if (window.location.hash === '#option-chain') {
        showOptionChain();
    }
})();
