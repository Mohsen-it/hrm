import axios from 'axios';
import mitt from 'mitt';

/* ============================================================================
 * EventBus — Global event emitter (mitt)
 * ========================================================================== */
window.EventBus = mitt();

/* ============================================================================
 * RTL — Arabic Right-to-Left is the default direction
 * ========================================================================== */
document.documentElement.dir = 'rtl';
document.documentElement.lang = 'ar';

/* ============================================================================
 * Axios — HTTP client defaults
 * ========================================================================== */
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept-Language'] = 'ar';

/* ============================================================================
 * CSRF Token — Laravel security
 * ========================================================================== */
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

/* ============================================================================
 * Laravel Echo — Realtime broadcasting via Reverb
 * ========================================================================== */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
