import axios from 'axios';

// Create an axios instance without baseURL for CSRF calls
const csrfAxios = axios.create();

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true; // <-- IMPORTANTE!

// Function to ensure CSRF token is available
const ensureCSRFToken = async () => {
    try {
        await csrfAxios.get('/sanctum/csrf-cookie');
    } catch (error) {
        console.warn('Failed to get CSRF token:', error);
    }
};

// Initialize CSRF token
ensureCSRFToken();

window.axios.defaults.baseURL = '/api'; // Se hai una base URL per le API

// Add response interceptor to handle auth failures
window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401 || error.response?.status === 419) {
            // Try to refresh CSRF token and retry
            await ensureCSRFToken();
            // Optionally retry the original request
        }
        return Promise.reject(error);
    }
);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';
// import Pusher from 'pusher-js';
// window.Pusher = Pusher;
// window.Echo = new Echo({ ... });