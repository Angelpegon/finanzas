const BASE = self.location.pathname.replace(/\/sw\.js$/, '');
// Bumpear en cada release que toque public/assets o la lógica del SW (Plesk / iOS PWA).
const CACHE = 'finanzas-pwa-v7';
const PRECACHE = [`${BASE}/offline.html`, `${BASE}/manifest.json`, `${BASE}/icons/icon-192.png`, `${BASE}/icons/icon-512.png`];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Nunca cachear HTML de la app (saldos). Sin red → offline honesto.
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(`${BASE}/offline.html`))
        );
        return;
    }

    const networkFirst = (request) => fetch(request)
        .then((response) => {
            if (response.ok) {
                const clone = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, clone));
            }
            return response;
        })
        .catch(() => caches.match(request));

    // Vite hashea /build/: red primero (iPhone/PWA no se queda con CSS viejo).
    if (url.pathname.startsWith(`${BASE}/build/`)) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Vendor estático: también red primero. Cache-first rompía SweetAlert/jQuery tras deploy en Plesk.
    if (url.pathname.startsWith(`${BASE}/assets/`)) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    const estaticoFijo = url.pathname.startsWith(`${BASE}/icons/`)
        || url.pathname === `${BASE}/manifest.json`
        || url.pathname === `${BASE}/offline.html`;

    if (! estaticoFijo) {
        return;
    }

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(event.request);
            if (cached) {
                return cached;
            }
            const response = await fetch(event.request);
            if (response.ok) {
                cache.put(event.request, response.clone());
            }
            return response;
        })
    );
});
