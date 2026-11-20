document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('#scoring-rules-editor form');
  const total = document.getElementById('weights-total');
  const warning = document.getElementById('weights-warning');
  const save = document.getElementById('weights-save');
  if (!form || !total || !warning || !save) return;
  const inputs = [...form.querySelectorAll('input[name^="maximum["]')];
  function update() {
    const numbers = inputs.map(input => input.value.trim() === '' ? NaN : Number(input.value));
    const sum = numbers.reduce((a, b) => a + b, 0);
    const valid = numbers.every(n => Number.isFinite(n) && n >= 0 && n <= 100) && Math.abs(sum - 100) < 0.001;
    total.textContent = 'Total: ' + (Number.isFinite(sum) ? sum.toFixed(1).replace(/\.0$/, '') : '—') +
      ' / 100 ' + (valid ? '✓' : '— ' + (Number.isFinite(sum) ?
        (sum < 100 ? (100 - sum).toFixed(1) + ' points short' : (sum - 100).toFixed(1) + ' points over') : 'Check values'));
    total.classList.toggle('weights-invalid', !valid);
    warning.hidden = valid;
    save.disabled = !valid;
  }
  inputs.forEach(input => input.addEventListener('input', update));
  update();
});
