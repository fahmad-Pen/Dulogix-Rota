document.addEventListener('DOMContentLoaded', () => {
  const filters = document.getElementById('vehicle-type-filters');
  filters?.querySelectorAll('input[type="checkbox"]').forEach(box => {
    box.addEventListener('change', () => filters.requestSubmit());
  });
  document.getElementById('vehicle-state-filter')?.addEventListener('change', () => filters?.requestSubmit());
  document.getElementById('vehicle-search')?.addEventListener('input', event => {
    const term = event.target.value.toLowerCase().trim();
    document.querySelectorAll('#vehicle-table tbody tr').forEach(row => {
      row.hidden = !row.cells[1]?.textContent.toLowerCase().includes(term);
    });
  });
  document.querySelectorAll('form.vehicle-action').forEach(form => {
    form.addEventListener('submit', event => {
      const button = event.submitter;
      if (!button?.matches('[data-vehicle-confirm]')) return;
      const action = button.dataset.vehicleConfirm;
      const registration = form.querySelector('[name="registration"]')?.value || '';
      const grounded = form.querySelector('[name="grounded"]')?.value;
      const message = action === 'delete'
        ? 'Permanently delete van ' + registration + '? This cannot be undone. Vans with assignments cannot be deleted.'
        : grounded === '0'
          ? 'Return this vehicle to active service?'
          : 'Ground this vehicle? It will no longer be available for operations assignments.';
      if (!window.confirm(message)) event.preventDefault();
    });
  });
});
