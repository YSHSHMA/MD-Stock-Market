(() => {
    const underlyings = {
        NIFTY: { label: 'NIFTY 50', spot: 25250, step: 50, decimals: 0 },
        BANKNIFTY: { label: 'NIFTY BANK', spot: 56800, step: 100, decimals: 0 },
        SENSEX: { label: 'SENSEX', spot: 82500, step: 100, decimals: 0 },
        CRUDEOIL: { label: 'CRUDE OIL', spot: 6500, step: 50, decimals: 0 },
    };

    function getChain(symbol, expiry) {
        const underlying = underlyings[symbol];
        if (!underlying || !/^\d{4}-\d{2}-\d{2}$/.test(expiry) || Number.isNaN(Date.parse(`${expiry}T00:00:00`))) {
            throw new Error('Choose a supported underlying and expiry date.');
        }

        const daysToExpiry = Math.max(0, Math.ceil((Date.parse(`${expiry}T00:00:00`) - Date.now()) / 86400000));
        const expiryIndex = Math.min(2, Math.floor(daysToExpiry / 30));
        const spot = underlying.spot + expiryIndex * underlying.step * 2;
        const atmStrike = Math.round(spot / underlying.step) * underlying.step;
        const normalCdf = (value) => {
            const x = Math.abs(value);
            const t = 1 / (1 + 0.2316419 * x);
            const density = 0.3989422804 * Math.exp(-0.5 * x * x);
            const probability = 1 - density * t * (0.31938153 + t * (-0.356563782 + t * (1.781477937 + t * (-1.821255978 + t * 1.330274429))));
            return value >= 0 ? probability : 1 - probability;
        };
        const getGreeks = (isCall, strike, iv) => {
            const years = Math.max(daysToExpiry, 1) / 365;
            const sigma = Math.max(iv / 100, 0.01);
            const rate = 0.04;
            const rootTime = Math.sqrt(years);
            const d1 = (Math.log(spot / strike) + (rate + sigma * sigma / 2) * years) / (sigma * rootTime);
            const d2 = d1 - sigma * rootTime;
            const density = 0.3989422804 * Math.exp(-0.5 * d1 * d1);
            const discount = Math.exp(-rate * years);
            const theta = isCall
                ? (-spot * density * sigma / (2 * rootTime) - rate * strike * discount * normalCdf(d2)) / 365
                : (-spot * density * sigma / (2 * rootTime) + rate * strike * discount * normalCdf(-d2)) / 365;

            return {
                delta: isCall ? normalCdf(d1) : normalCdf(d1) - 1,
                gamma: density / (spot * sigma * rootTime),
                theta,
                vega: spot * density * rootTime / 100,
            };
        };
        const rows = Array.from({ length: 13 }, (_, index) => {
            const strike = atmStrike + (index - 6) * underlying.step;
            const distance = Math.abs(strike - spot) / underlying.step;
            const timeValue = Math.max(1, underlying.spot * (0.003 - distance * 0.00015) * (1 + expiryIndex * 0.22));
            const callIntrinsic = Math.max(0, spot - strike);
            const putIntrinsic = Math.max(0, strike - spot);
            const seed = Math.abs(Math.round(strike / underlying.step)) + expiryIndex * 31;
            const callOi = 1200 + ((seed * 173) % 8700);
            const putOi = 1100 + ((seed * 137) % 9100);
            const callLtp = callIntrinsic + timeValue;
            const putLtp = putIntrinsic + timeValue * 0.94;
            const volumeFactor = 0.2 + ((seed * 19) % 70) / 100;
            const round = (value, places = 2) => Number(value.toFixed(places));
            const callGreeks = getGreeks(true, strike, 12 + distance * 0.55 + expiryIndex * 0.8);
            const putGreeks = getGreeks(false, strike, 13 + distance * 0.6 + expiryIndex * 0.85);

            return {
                strike,
                isAtm: Math.abs(strike - atmStrike) < underlying.step / 2,
                call: {
                    oi: callOi,
                    oiChange: Math.round(callOi * (((seed % 9) - 4) / 100)),
                    volume: Math.round(callOi * volumeFactor),
                    iv: round(12 + distance * 0.55 + expiryIndex * 0.8),
                    ltp: round(callLtp),
                    ...callGreeks,
                },
                put: {
                    oi: putOi,
                    oiChange: Math.round(putOi * (((seed % 7) - 3) / 100)),
                    volume: Math.round(putOi * (0.18 + ((seed * 23) % 65) / 100)),
                    iv: round(13 + distance * 0.6 + expiryIndex * 0.85),
                    ltp: round(putLtp),
                    ...putGreeks,
                },
            };
        });

        return {
            underlying: symbol,
            label: underlying.label,
            expiry,
            spot,
            rows,
            simulated: true,
        };
    }

    window.optionChainProvider = {
        getUnderlyings: () => Object.entries(underlyings).map(([value, item]) => ({
            value,
            label: item.label,
        })),
        getDefaultExpiry: () => {
            const date = new Date();
            date.setDate(date.getDate() + 7);
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        },
        getChain,
    };
})();
