(() => {
  // Phone push is offered only inside the installed Android/iOS PWA.
  // Ordinary desktop/mobile browser sessions keep their existing driver UI.
  const installed = window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: fullscreen)').matches ||
    window.navigator.standalone === true;
  if (!installed || !document.querySelector('.driver-welcome')) return;
  const button = document.createElement('button');
  button.type = 'button';
  button.textContent = 'Enable phone notifications';
  button.style.cssText = 'display:block;margin:12px 0;background:#205e94';
  const state = document.createElement('small');
  state.setAttribute('role', 'status');
  state.style.cssText = 'display:block;color:#426b88;margin-bottom:12px';
  const target = document.querySelector('.driver-welcome');
  target.insertAdjacentElement('afterend', button);
  button.insertAdjacentElement('afterend', state);

  const report = (message) => { state.textContent = message; };
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    button.disabled = true;
    report('This browser does not support push notifications. On Android, use Chrome and install Dulogix.');
    return;
  }
  const token = window.DULOGIX_SHIFT_TOKEN;
  if (!token) { button.disabled = true; report('Notification security token is missing. Refresh the page.'); return; }
  const decodeKey = key => {
    const raw = atob(key.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(key.length / 4) * 4, '='));
    return Uint8Array.from(raw, c => c.charCodeAt(0));
  };
  const save = async subscription => {
    const response = await fetch('/driver/push/subscription', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': token},
      credentials: 'same-origin',
      body: JSON.stringify(subscription.toJSON())
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data.error || 'Phone registration failed (HTTP ' + response.status + ')');
    }
  };
  const getConfig = async () => {
    const response = await fetch('/driver/push/config', {credentials: 'same-origin', cache: 'no-store'});
    if (!response.ok) throw new Error('Notification service unavailable (HTTP ' + response.status + '). Please contact Operations.');
    const config = await response.json();
    if (!config.enabled || !config.publicKey) throw new Error('Notifications are not yet enabled on the server.');
    return config;
  };
  let config;
  let registration;
  const ready = (async () => {
    config = await getConfig();
    registration = await navigator.serviceWorker.register('/service-worker.js');
    const existing = await registration.pushManager.getSubscription();
    if (existing) {
      await save(existing);
      button.textContent = 'Phone notifications enabled';
      button.disabled = true;
      report('This phone is registered for Dulogix alerts.');
    } else if (Notification.permission === 'denied') {
      button.disabled = true;
      report('Notifications are blocked. Allow notifications for Dulogix in Android app/site settings.');
    } else {
      report('Tap to allow notifications for new driver instructions.');
    }
  })();
  ready.catch(error => { button.disabled = true; report(error.message || 'Unable to prepare notifications.'); });
  button.addEventListener('click', async () => {
    if (button.disabled) return;
    // IMPORTANT: request permission directly in the tap handler, before any await.
    const permissionPromise = Notification.requestPermission();
    button.disabled = true;
    report('Setting up phone notifications…');
    try {
      const permission = await permissionPromise;
      if (permission !== 'granted') throw new Error('Notifications were not allowed. Enable them in Android app/site settings.');
      await ready;
      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true, applicationServerKey: decodeKey(config.publicKey)
      });
      await save(subscription);
      button.textContent = 'Phone notifications enabled';
      report('This phone is registered for Dulogix alerts.');
    } catch (error) {
      report(error.message || 'Could not enable notifications.');
      button.disabled = false;
    }
  });
})();
