(() => {
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
  const token = window.DULOGIX_SHIFT_TOKEN;
  if (!token) return;
  const button = document.createElement('button');
  button.type = 'button';
  button.textContent = 'Enable phone notifications';
  button.style.cssText = 'display:block;margin:12px 0;background:#205e94';
  const state = document.createElement('small');
  state.style.cssText = 'display:block;color:#426b88;margin-bottom:12px';
  const target = document.querySelector('.driver-welcome');
  if (!target) return;
  target.insertAdjacentElement('afterend', button);
  button.insertAdjacentElement('afterend', state);
  const decodeKey = (key) => {
    const base64 = key.replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '='));
    return Uint8Array.from(raw, c => c.charCodeAt(0));
  };
  const save = async (subscription) => {
    const response = await fetch('/driver/push/subscription', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': token},
      credentials: 'same-origin',
      body: JSON.stringify(subscription.toJSON())
    });
    if (!response.ok) throw new Error('Could not register this phone');
  };
  const setup = async () => {
    const config = await fetch('/driver/push/config', {credentials: 'same-origin'}).then(r => r.json());
    if (!config.enabled) { button.remove(); state.remove(); return; }
    const registration = await navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();
    if (existing) {
      await save(existing); // Refresh a previously registered subscription after login.
      button.textContent = 'Phone notifications enabled';
      button.disabled = true;
    }
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') throw new Error('Notification permission not granted');
        const subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true, applicationServerKey: decodeKey(config.publicKey)
        });
        await save(subscription);
        button.textContent = 'Phone notifications enabled';
        state.textContent = 'Alerts will arrive for new unread Operations instructions.';
      } catch (error) {
        state.textContent = error.message || 'Notifications could not be enabled';
        button.disabled = false;
      }
    });
  };
  setup().catch(() => { state.textContent = 'Phone notifications are unavailable on this browser.'; button.disabled = true; });
})();
