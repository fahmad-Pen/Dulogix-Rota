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
    if (penalty) penalty.value = Number(input.value) > 0 ? -2 : 0;
    const total = form.closest('details')?.querySelector('.netradyne-total');
    if (total) {
      const value = Array.from(form.querySelectorAll('[data-penalty]')).reduce(function (sum, field) { return sum + (Number(field.value) || 0); }, 0);
      total.textContent = value + '%';
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
        if (queues.get(form) === next) {
          status.textContent = 'Saved';
          const button = Array.from(document.querySelectorAll('.driver-report-open')).find(b => b.dataset.id === form.elements.namedItem('transporter_id').value);
          if (button) {
            const fields = ['d','fd','ss','speed','tl'];
            const deduction = getter => fields.reduce((sum,k) => {
              const count = Math.max(0,Number(getter(k)) || 0);
              return sum + (count > 0 ? 2 : 0);
            },0);
            const oldPenalty = deduction(k => button.dataset['net'+k[0].toUpperCase()+k.slice(1)]);
            const newPenalty = deduction(k => form.elements.namedItem(k).value);
            const score = Math.max(0, Number(button.dataset.score) + oldPenalty - newPenalty);
            fields.forEach(k => button.dataset['net'+k[0].toUpperCase()+k.slice(1)] = form.elements.namedItem(k).value);
            if (Number.isFinite(score)) {
              button.dataset.score = String(Math.round(score*100)/100);
              const cell = button.closest('tr')?.cells[1];
              const scoreLine = cell?.querySelectorAll(':scope > div')[1];
              if (scoreLine) scoreLine.textContent = 'Score: '+button.dataset.score+' / 100';
              const all = Array.from(document.querySelectorAll('.driver-report-open')).filter(b => Number.isFinite(Number(b.dataset.score)));
              all.sort((a,b) => Number(b.dataset.score)-Number(a.dataset.score) || a.dataset.id.localeCompare(b.dataset.id));
              let previous = null, rank = 0;
              all.forEach((b,i) => {
                const value = Number(b.dataset.score);
                if (previous === null || value !== previous) rank = i+1;
                previous = value;
                b.dataset.rank = String(rank);
                const cell = b.closest('tr')?.cells[1];
                const heading = cell?.querySelector('strong');
                if (heading) heading.textContent = 'Rank: '+rank;
                const status = value >= 93.5 ? 'Fantastic_Plus' : value >= 85 ? 'Fantastic' : value >= 70 ? 'Great' : value >= 40 ? 'Fair' : 'Poor';
                b.dataset.status = status;
                const badge = cell?.querySelector('div span');
                if (badge) {
                  badge.textContent = status.replaceAll('_', ' ');
                  badge.style.fontSize = '12px';
                  badge.style.background = value >= 93.5 ? '#BFE8D0' : value >= 85 ? '#DDF2E7' : value >= 70 ? '#DCEBFA' : value >= 40 ? '#FFF0BF' : '#FAD9DF';
                }
              });
            }
          }
        }
      }).catch(function () {
        if (queues.get(form) === next) status.textContent = 'Could not save — please retry editing';
      });
    }, 450));
  });
})();
