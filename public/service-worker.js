// Network-only: never store driver routes, instructions, sessions or API responses.
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (_) {}
  event.waitUntil(self.registration.showNotification(data.title || 'Dulogix update', {
    body: data.body || 'Please check your latest instructions.',
    icon: '/pwa-icon-192.png',
    badge: '/pwa-icon-192.png',
    tag: 'dulogix-driver-instructions',
    data: {url: '/driver/daily-check'}
  }));
});
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil((async () => {
    const windows = await clients.matchAll({type: 'window', includeUncontrolled: true});
    const existing = windows.find(c => new URL(c.url).origin === self.location.origin && c.url.includes('/driver/daily-check'));
    if (existing) { await existing.focus(); return; }
    await clients.openWindow('/driver/daily-check');
  })());
});
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
  event.respondWith(event.request.mode === 'navigate'
    ? fetch(event.request, { cache: 'no-store' })
    : fetch(event.request));
});
