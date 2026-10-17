import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Determine runtime Pusher or Reverb configuration
const pusherKey = window.PUSHER_CONFIG?.key || import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = window.PUSHER_CONFIG?.cluster || import.meta.env.VITE_PUSHER_APP_CLUSTER || 'ap1';
const reverbKey = window.REVERB_CONFIG?.key || import.meta.env.VITE_REVERB_APP_KEY;

if (pusherKey) {
    try {
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: pusherCluster,
            forceTLS: true,
            authEndpoint: '/broadcasting/auth',
        });
        console.info(`[Echo] Real-time broadcasting connected via Pusher Channels (cluster: ${pusherCluster}).`);
    } catch (error) {
        console.warn('[Echo] Pusher initialization error:', error);
    }
} else if (reverbKey) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST ?? (typeof window !== 'undefined' ? window.location.hostname : 'localhost'),
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        console.info('[Echo] Real-time broadcasting connected via Laravel Reverb.');
    } catch (error) {
        console.warn('[Echo] Reverb initialization error:', error);
    }
} else {
    console.info('[Echo] Neither Pusher nor Reverb credentials configured; Echo real-time broadcasting is inactive.');
}

