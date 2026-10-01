// "Get order updates on this device" (account page): asks for notification permission, subscribes
// the browser with the site's VAPID key and saves the subscription at POST /account/push.
// The button lives in a [data-push] block: data-key (VAPID public key), data-store / data-destroy URLs.

const root = document.querySelector('[data-push]');

function base64UrlToUint8Array(base64Url) {
    const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function send(url, method, body) {
    const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('push request failed');
    return response.json();
}

function init() {
    if (!root) return;

    const button = root.querySelector('[data-push-toggle]');
    const status = root.querySelector('[data-push-status]');
    const labels = JSON.parse(root.getAttribute('data-labels') || '{}');
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    const say = (text) => { if (status) status.textContent = text; };
    const setState = (on) => {
        root.dataset.state = on ? 'on' : 'off';
        if (button) button.textContent = on ? labels.off : labels.on;
        say(on ? labels.statusOn : labels.statusOff);
    };

    if (!supported || !import.meta.env.PROD) {
        root.dataset.state = 'unsupported';
        if (button) button.disabled = true;
        say(labels.unsupported);
        return;
    }

    let current = null;

    navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then((registration) => registration.pushManager.getSubscription())
        .then((subscription) => { current = subscription; setState(!!subscription); })
        .catch(() => { setState(false); });

    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const registration = await navigator.serviceWorker.ready;
            if (current) {
                await send(root.getAttribute('data-destroy'), 'DELETE', { endpoint: current.endpoint });
                await current.unsubscribe();
                current = null;
                setState(false);
            } else {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') { say(labels.denied); return; }
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64UrlToUint8Array(root.getAttribute('data-key')),
                });
                const json = subscription.toJSON();
                await send(root.getAttribute('data-store'), 'POST', {
                    endpoint: json.endpoint,
                    keys: json.keys,
                    content_encoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0],
                });
                current = subscription;
                setState(true);
            }
        } catch (e) {
            say(labels.failed);
        } finally {
            button.disabled = false;
        }
    });
}

init();
