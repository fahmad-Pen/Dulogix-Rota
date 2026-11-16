// Network-only PWA: check for updated service workers when the app returns to the foreground.
// Do not reload automatically: Operations and driver forms may contain unsaved work.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/service-worker.js', {
        updateViaCache: 'none'
      });
      const check = () => registration.update().catch(() => {});
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) check();
      });
      window.addEventListener('pageshow', event => {
        if (event.persisted) check();
      });
      // The worker uses skipWaiting/clients.claim; never force-refresh a page.
    } catch (_) {
      // The website remains usable if service workers are unavailable.
    }
  });
}
