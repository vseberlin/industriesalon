(() => {
  const cfg = window.ISS_PUBLICATION_ORDER || {};
  const orderUrl = typeof cfg.orderUrl === 'string' ? cfg.orderUrl : '';
  if (!orderUrl) return;

  let modal = null;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getModal() {
    if (modal) return modal;

    const root = document.createElement('div');
    root.className = 'iss-publication-order-modal';
    root.hidden = true;
    root.innerHTML = `
      <div class="iss-publication-order-modal__overlay" data-close="1" tabindex="-1"></div>
      <div class="iss-publication-order-modal__panel" role="dialog" aria-modal="true" aria-label="Publikation bestellen">
        <button type="button" class="iss-publication-order-modal__close" data-close="1" aria-label="Schließen">×</button>
        <div class="iss-publication-order-modal__content"></div>
      </div>
    `;

    const content = root.querySelector('.iss-publication-order-modal__content');
    if (!content) return null;

    function close() {
      root.hidden = true;
      content.innerHTML = '';
      document.documentElement.classList.remove('iss-publication-order-modal-open');
    }

    function open(payload) {
      content.innerHTML = '';
      content.appendChild(createForm(payload, close));
      root.hidden = false;
      document.documentElement.classList.add('iss-publication-order-modal-open');
    }

    root.addEventListener('click', (event) => {
      const closeTarget = event.target && event.target.closest ? event.target.closest('[data-close="1"]') : null;
      if (closeTarget) {
        close();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !root.hidden) {
        close();
      }
    });

    document.body.appendChild(root);
    modal = { open, close };
    return modal;
  }

  function createForm(payload, closeModal) {
    const wrap = document.createElement('div');
    const mollieDisabledAttr = 'disabled aria-disabled="true"';
    const amountEuros = Number(payload.amount || 0) / 100;

    wrap.innerHTML = `
      <form class="iss-publication-order-form" novalidate>
        <input type="hidden" name="publication_id" value="${escapeHtml(payload.publicationId)}">
        <div class="iss-publication-order-form__intro">
          <p class="iss-kicker iss-kicker--compact">Bestellung</p>
          <h2 class="iss-publication-order-form__title">${escapeHtml(payload.title || 'Publikation bestellen')}</h2>
          <p class="iss-publication-order-form__price">${escapeHtml(amountEuros.toFixed(2))} €</p>
        </div>
        <div class="iss-publication-order-form__row">
          <label>Ihr Name<br><input required name="name" type="text"></label>
        </div>
        <div class="iss-publication-order-form__row">
          <label>E-Mail<br><input required name="email" type="email"></label>
        </div>
        <div class="iss-publication-order-form__row">
          <label>Anzahl<br><input name="quantity" type="number" min="1" step="1" value="1"></label>
        </div>
        <fieldset class="iss-publication-order-form__row">
          <legend>Zahlung</legend>
          <label><input type="radio" name="payment" value="onsite" checked> Vor Ort</label>
          <label><input type="radio" name="payment" value="mollie" ${mollieDisabledAttr}> Mollie (bald)</label>
        </fieldset>
        <div class="iss-publication-order-form__actions">
          <button type="submit">${escapeHtml(payload.label || 'Bestellen')}</button>
        </div>
        <p class="iss-publication-order-form__status" aria-live="polite"></p>
      </form>
    `;

    const form = wrap.querySelector('form');
    const status = wrap.querySelector('.iss-publication-order-form__status');
    if (!form || !status) return wrap;

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const fd = new FormData(form);
      const body = Object.fromEntries(fd.entries());
      body.quantity = Number(body.quantity || 1);

      try {
        status.textContent = 'Sende ...';
        const res = await fetch(orderUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(body)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data || data.ok !== true) {
          status.textContent = (data && data.error) ? data.error : 'Fehler bei der Bestellung.';
          return;
        }
        status.textContent = 'Danke! Ihre Bestellung wurde aufgenommen.';
        form.reset();
        window.setTimeout(() => closeModal(), 500);
      } catch {
        status.textContent = 'Netzwerkfehler. Bitte später erneut versuchen.';
      }
    });

    return wrap;
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target && event.target.closest
      ? event.target.closest('.iss-publication-order-trigger')
      : null;

    if (!trigger) return;
    event.preventDefault();

    const dialog = getModal();
    if (!dialog) return;

    dialog.open({
      publicationId: String(trigger.dataset.publicationId || '').trim(),
      title: String(trigger.dataset.title || '').trim(),
      amount: Number(trigger.dataset.amount || 0),
      label: String(trigger.dataset.label || '').trim()
    });
  });
})();
