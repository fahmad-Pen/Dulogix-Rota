(function () {
  if (window.__netradyneAutosaveBound) return;
  window.__netradyneAutosaveBound = true;
  const timers = new WeakMap();
  const queues = new WeakMap();
  document.addEventListener('submit', function (event) {
    if (event.target.matches('form:has(.netradyne-save-status)')) event.preventDefault();
  });
  document.addEventListener('input', function (event) {
    const input = event.target;
    if (!input.matches('form:has(.netradyne-save-status) input[name="d"], form:has(.netradyne-save-status) input[name="fd"], form:has(.netradyne-save-status) input[name="ss"], form:has(.netradyne-save-status) input[name="speed"], form:has(.netradyne-save-status) input[name="tl"]')) return;
    const form = input.form;
    const status = form.querySelector('.netradyne-save-status');
    const penalty = form.querySelector('[data-penalty="' + input.name + '"]');
    if (penalty) penalty.value = -(Number(input.value) || 0) * (input.name === 'tl' ? 10 : 2);
    const total = form.closest('details')?.querySelector('.netradyne-total');
    if (total) {
      const value = Array.from(form.querySelectorAll('[data-penalty]')).reduce(function (sum, field) { return sum + (Number(field.value) || 0); }, 0);
      total.textContent = value;
      total.style.color = value < 0 ? '#b42335' : '#087a39';
      total.style.background = value < 0 ? '#ffe2e6' : '#c9f2d5';
    }
    clearTimeout(timers.get(form));
    if (!form.checkValidity()) { status.textContent = 'Enter valid whole numbers'; return; }
    status.textContent = 'Saving…';
    timers.set(form, setTimeout(function () {
      const payload = new FormData(form);
      payload.set('autosave', '1');
      const previous = queues.get(form) || Promise.resolve();
      const next = previous.catch(function () {}).then(function () {
        return fetch(form.action, { method: 'POST', body: payload, credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (response) {
          if (!response.ok) throw new Error('Save failed');
          return response.json();
        });
      });
      queues.set(form, next);
      next.then(function () {
        if (queues.get(form) === next) status.textContent = 'Saved';
      }).catch(function () {
        if (queues.get(form) === next) status.textContent = 'Could not save — please retry editing';
      });
    }, 450));
  });
})();
