/* A normal POST still owns the run; prevent accidental repeated submissions. */
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.matches('[data-iss-sync-run]')) return;
  const button = event.submitter;
  if (button instanceof HTMLInputElement || button instanceof HTMLButtonElement) {
    button.disabled = true;
  }
  const status = form.querySelector('[data-iss-sync-progress]');
  if (status) status.textContent = 'Termine werden abgeglichen. Bitte diese Seite geöffnet lassen.';
});
