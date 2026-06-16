import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Breeze puts this in layouts/app.blade.php
const tokenTag = document.head.querySelector('meta[name="csrf-token"]');
if (tokenTag) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenTag.content;
}

// Base path for subdirectory deployment (e.g., "/roomready/laravel")
// Read from <meta name="base-path"> set in blade layouts
const basePath = document.head.querySelector('meta[name="base-path"]')?.content || '';

/**
 * Ensure a URL includes the base path prefix.
 * Handles both relative paths (/sessions/...) and absolute URLs (http://...).
 * URLs that already include the base path are returned unchanged.
 */
function prefixUrl(url) {
    if (!basePath) return url;

    // Absolute URLs (from form.action or blade-generated)
    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            const u = new URL(url);
            if (!u.pathname.startsWith(basePath + '/') && u.pathname !== basePath) {
                u.pathname = basePath + u.pathname;
                return u.toString();
            }
        } catch (e) { /* invalid URL, return as-is */ }
        return url;
    }

    // Relative paths starting with /
    if (url.startsWith('/') && !url.startsWith(basePath + '/') && url !== basePath) {
        return basePath + url;
    }

    return url;
}

// Expose for other scripts that may need it
window.basePath = basePath;

/**
 * Simple API helper: api.get/post/put/patch/delete
 * Automatically prefixes URLs with the base path for subdirectory deployment.
 */
window.api = {
    get(url, config = {}) {
        return axios.get(prefixUrl(url), config).then(res => res.data);
    },
    post(url, data = {}, config = {}) {
        return axios.post(prefixUrl(url), data, config).then(res => res.data);
    },
    put(url, data = {}, config = {}) {
        return axios.put(prefixUrl(url), data, config).then(res => res.data);
    },
    patch(url, data = {}, config = {}) {
        return axios.patch(prefixUrl(url), data, config).then(res => res.data);
    },
    delete(url, config = {}) {
        return axios.delete(prefixUrl(url), config).then(res => res.data);
    },
};

// Global interceptor to handle 419 Session Expired cleanly for AJAX
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 419) {
            window.location.reload();
        }
        return Promise.reject(error);
    }
);
