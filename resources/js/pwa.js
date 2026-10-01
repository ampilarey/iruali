// Installable app: registers the service worker (production storefront pages only) and shows a
// dismissible "Add to home screen" banner once per 30 days when the browser offers to install.

const PRIVATE_PAGE = /^\/(dv\/)?(admin|seller)(\/|$)/;
const DISMISS_KEY = 'iruali.install.dismissed';
const DISMISS_DAYS = 30;

const t = (key, fallback) => (window.pwaLabels && window.pwaLabels[key]) || fallback;

function storage(action, value) {
    try {
        if (action === 'get') return window.localStorage.getItem(DISMISS_KEY);
        window.localStorage.setItem(DISMISS_KEY, value);
    } catch (e) {
        return null;
    }
}

export function registerServiceWorker() {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) return;
    if (PRIVATE_PAGE.test(window.location.pathname)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

function recentlyDismissed() {
    const at = parseInt(storage('get') || '0', 10);
    return at && Date.now() - at < DISMISS_DAYS * 24 * 60 * 60 * 1000;
}

function showBanner(promptEvent) {
    if (document.getElementById('install-banner')) return;

    const banner = document.createElement('div');
    banner.id = 'install-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', t('title', 'Add iruali to your home screen'));
    banner.className = 'fixed inset-x-3 bottom-20 lg:bottom-6 lg:inset-x-auto lg:end-6 lg:w-96 z-40 rounded-xl bg-white shadow-xl border border-gray-200 p-4 flex items-center gap-3';
    banner.innerHTML =
        '<img src="/images/icons/icon-192.png" alt="" width="48" height="48" class="w-12 h-12 rounded-xl shrink-0">' +
        '<div class="min-w-0 flex-1">' +
            '<p class="font-semibold text-dark text-sm"></p>' +
            '<p class="text-xs text-gray-600 mt-0.5"></p>' +
            '<div class="mt-2 flex gap-2">' +
                '<button type="button" data-install class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-hover"></button>' +
                '<button type="button" data-dismiss class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"></button>' +
            '</div>' +
        '</div>';
    banner.querySelector('p').textContent = t('title', 'Add iruali to your home screen');
    banner.querySelectorAll('p')[1].textContent = t('body', 'Shop faster, even on a slow connection, with the app on your phone.');
    banner.querySelector('[data-install]').textContent = t('install', 'Install');
    banner.querySelector('[data-dismiss]').textContent = t('later', 'Not now');

    const close = () => { storage('set', String(Date.now())); banner.remove(); };
    banner.querySelector('[data-dismiss]').addEventListener('click', close);
    banner.querySelector('[data-install]').addEventListener('click', async () => {
        banner.remove();
        storage('set', String(Date.now()));
        try {
            promptEvent.prompt();
            await promptEvent.userChoice;
        } catch (e) { /* the browser withdrew the prompt */ }
    });

    document.body.appendChild(banner);
}

export function installPrompt() {
    if (PRIVATE_PAGE.test(window.location.pathname)) return;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        if (recentlyDismissed()) return;
        showBanner(event);
    });

    window.addEventListener('appinstalled', () => {
        storage('set', String(Date.now()));
        const banner = document.getElementById('install-banner');
        if (banner) banner.remove();
    });
}

registerServiceWorker();
installPrompt();
