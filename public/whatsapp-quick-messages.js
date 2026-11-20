document.addEventListener('click', event => {
  const button = event.target.closest('button[data-confirm-delete]');
  if (button && !window.confirm('Delete this quick message?')) event.preventDefault();
});
