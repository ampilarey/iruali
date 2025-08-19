// Service Worker to block icon requests
self.addEventListener('install', function(event) {
    console.log('Icon Blocker Service Worker installed');
});

self.addEventListener('fetch', function(event) {
    const url = event.request.url;
    
    // Block requests to old icon files
    if (url.includes('apple-touch-icon') || url.includes('precomposed')) {
        console.log('Service Worker blocked:', url);
        event.respondWith(
            new Response('', {
                status: 200,
                headers: { 'Content-Type': 'image/svg+xml' }
            })
        );
        return;
    }
    
    // Block requests to external placeholder services
    if (url.includes('via.placeholder.com')) {
        console.log('Service Worker blocked external placeholder:', url);
        event.respondWith(
            new Response('', {
                status: 200,
                headers: { 'Content-Type': 'image/svg+xml' }
            })
        );
        return;
    }
    
    // Allow all other requests
    event.respondWith(fetch(event.request));
});
