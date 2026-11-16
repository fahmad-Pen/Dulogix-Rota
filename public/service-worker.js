// Network-only: never store driver routes, instructions, sessions or API responses.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
  event.respondWith(event.request.mode === 'navigate'
    ? fetch(event.request, { cache: 'no-store' })
    : fetch(event.request));
});
