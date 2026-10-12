import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const config = window.marketboard;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

if (config?.realtime?.key && csrfToken) {
    const secure = window.location.protocol === 'https:';
    const port = window.location.port
        ? Number(window.location.port)
        : (secure ? 443 : 80);

    window.marketboardEcho = new Echo({
        broadcaster: 'reverb',
        key: config.realtime.key,
        wsHost: window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: secure ? ['wss'] : ['ws', 'wss'],
        authEndpoint: config.realtime.authUrl,
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    window.marketboardEcho
        .private(`users.${config.userId}`)
        .listen('.option.greeks.updated', (payload) => {
            window.dispatchEvent(new CustomEvent('marketboard:live-greeks', {
                detail: payload,
            }));
        });

    window.marketboardEcho.connector.pusher.connection.bind('state_change', (state) => {
        window.dispatchEvent(new CustomEvent('marketboard:realtime-state', {
            detail: state,
        }));
    });
}
