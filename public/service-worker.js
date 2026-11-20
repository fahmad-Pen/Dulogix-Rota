// Network-only: never store driver routes, instructions, sessions or API responses.
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (_) {}
  event.waitUntil((async () => {
    // Refresh already-open driver pages as soon as the push arrives.
    const pages = await clients.matchAll({type: 'window', includeUncontrolled: true});
    for (const page of pages) {
      if (new URL(page.url).origin === self.location.origin && page.url.includes('/driver/daily-check'))
        page.postMessage({type: 'DULOGIX_INSTRUCTIONS_UPDATED'});
    }
    await self.registration.showNotification(data.title || 'Dulogix update', {
    body: data.body || 'Please check your latest instructions.',
    icon: '/pwa-icon-192.png',
    badge: '/pwa-icon-192.png',
    tag: 'dulogix-driver-instructions',
    data: {url: '/driver/daily-check'}
    });
  })());
});
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil((async () => {
    const windows = await clients.matchAll({type: 'window', includeUncontrolled: true});
    const candidates = windows.filter(c => new URL(c.url).origin === self.location.origin && c.url.includes('/driver/daily-check'));
    // A normal Chrome tab must not take precedence over the installed PWA.
    // Ask each live page which display mode it is actually running in.
    const modes = await Promise.all(candidates.map(c => new Promise(resolve => {
      const channel = new MessageChannel();
      const timeout = setTimeout(() => resolve(false), 350);
      channel.port1.onmessage = e => { clearTimeout(timeout); resolve(e.data?.standalone === true); };
      try { c.postMessage({type: 'DULOGIX_DISPLAY_MODE'}, [channel.port2]); }
      catch (_) { clearTimeout(timeout); resolve(false); }
    })));
    const app = candidates.find((_, i) => modes[i]);
    if (app) {
      await app.focus();
      app.postMessage({type: 'DULOGIX_INSTRUCTIONS_UPDATED'});
      return;
    }
    // No installed app window is available: let Android resolve the PWA URL.
    await clients.openWindow('/driver/daily-check');
  })());
});
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
  // Keep requests network-only; turn a rejected fetch into an explicit
  // network-error response so the service worker has no unhandled rejection.
  event.respondWith((event.request.mode === 'navigate'
    ? fetch(event.request, { cache: 'no-store' })
    : fetch(event.request)).catch(() => Response.error()));
});
