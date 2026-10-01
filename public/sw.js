/*
 * iruali service worker (registered by resources/js/pwa.js on production storefront pages).
 *
 * - Navigations (HTML): network first, the cached copy when offline, else the /offline page.
 * - /build/, /storage/ and /images/: stale-while-revalidate (serve from cache, refresh in the background).
 * - Private pages (admin, seller, account, checkout, orders, cart, API, sign-in...) are never cached.
 * - Push notifications: shows them and opens the order on tap.
 */
const VERSION = 'v1';
const SHELL_CACHE = 'iruali-shell-' + VERSION;
const PAGE_CACHE = 'iruali-pages-' + VERSION;
const ASSET_CACHE = 'iruali-assets-' + VERSION;
const OFFLINE_URL = '/offline';

const PRECACHE = [
    OFFLINE_URL,
    '/site.webmanifest',
    '/favicon.svg',
    '/images/brand/iruali-logo.svg',
    '/images/brand/iruali-mark.svg',
    '/images/icons/icon-192.png',
    '/images/icons/icon-512.png',
    '/images/product-placeholder.svg',
];

// Nothing under these paths is ever stored (also with the /dv language prefix)
const PRIVATE = /^\/(dv\/)?(admin|seller|account|checkout|orders|cart|api|login|register|logout|forgot-password|reset-password|2fa|verification|auth|profile|wishlist|compare|saved|payments|returns|track|feeds|locale|offline|storage\/returns)(\/|$|\?)/;
const ASSET = /^\/(build|storage|images)\//;
const MAX_PAGES = 40;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then(function (cache) { return cache.addAll(PRECACHE); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys
                .filter(function (key) { return key.indexOf('iruali-') === 0 && key.indexOf('-' + VERSION) === -1; })
                .map(function (key) { return caches.delete(key); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;
    if (request.method !== 'GET') return;

    var url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (PRIVATE.test(url.pathname)) return;

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request));
        return;
    }

    if (ASSET.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});

function networkFirstPage(request) {
    return fetch(request).then(function (response) {
        if (response && response.ok && response.type === 'basic') {
            var copy = response.clone();
            caches.open(PAGE_CACHE).then(function (cache) {
                cache.put(request, copy).then(function () { return trim(cache, MAX_PAGES); });
            });
        }
        return response;
    }).catch(function () {
        return caches.match(request, { ignoreSearch: false }).then(function (cached) {
            return cached || caches.match(OFFLINE_URL);
        });
    });
}

function staleWhileRevalidate(request) {
    return caches.open(ASSET_CACHE).then(function (cache) {
        return cache.match(request).then(function (cached) {
            var network = fetch(request).then(function (response) {
                if (response && response.ok && response.type === 'basic') {
                    cache.put(request, response.clone());
                }
                return response;
            }).catch(function () { return cached; });
            return cached || network;
        });
    });
}

function trim(cache, max) {
    return cache.keys().then(function (keys) {
        if (keys.length <= max) return;
        return cache.delete(keys[0]).then(function () { return trim(cache, max); });
    });
}

/* Push notifications (payload built by App\Notifications\Channels\WebPushChannel) */
self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }

    var title = data.title || 'iruali';
    var options = {
        body: data.body || '',
        icon: data.icon || '/images/icons/icon-192.png',
        badge: '/images/icons/icon-192.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url || '/' },
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
            for (var i = 0; i < windows.length; i++) {
                if (windows[i].url === url && 'focus' in windows[i]) return windows[i].focus();
            }
            if (self.clients.openWindow) return self.clients.openWindow(url);
        })
    );
});

self.addEventListener('pushsubscriptionchange', function (event) {
    // The browser rotated the subscription: the account page re-subscribes on the next visit.
    event.waitUntil(Promise.resolve());
});
