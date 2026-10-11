(() => {
  const config = window.issContentOrder || {};
  const text = (key, fallback) => (config.strings && config.strings[key]) || fallback;

  function init() {
    const body = document.getElementById('the-list');
    if (![true, 1, '1'].includes(config.enabled) || !body) return;
    const selector = config.rowSelector || 'tr';
    const rows = () => Array.from(body.children).filter((row) => row.matches(selector));
    const ids = () => rows().map((row) => Number(row.querySelector('.iss-content-order-handle')?.dataset.postId)).filter(Boolean);
    let busy = false;
    let dragged = null;
    let dropped = false;
    let before = [];
    const restore = () => before.forEach((row) => body.append(row));

    function notice(message, type) {
      const wrap = document.querySelector('.wrap');
      if (!wrap) return;
      let node = wrap.querySelector('.iss-content-order-notice');
      if (!node) {
        node = document.createElement('div');
        node.setAttribute('role', 'status');
        node.setAttribute('aria-live', 'polite');
        node.append(document.createElement('p'));
        (wrap.querySelector('h1') || wrap.firstElementChild).after(node);
      }
      node.className = `notice iss-content-order-notice notice-${type}`;
      node.querySelector('p').textContent = message;
    }

    function setBusy(value) {
      busy = value;
      document.body.classList.toggle('iss-content-order-saving', value);
      body.setAttribute('aria-busy', String(value));
      body.querySelectorAll('.iss-content-order-handle, .iss-content-order-step').forEach((button) => {
        button.disabled = value;
      });
    }

    async function save(focusTarget) {
      if (busy) return;
      setBusy(true);
      notice(text('saving', 'Saving order ...'), 'info');
      const data = new URLSearchParams({ action: config.action || 'iss_content_project_reorder', nonce: config.nonce || '' });
      ids().forEach((id) => data.append('post_ids[]', String(id)));
      let failureMessage = text('error', 'Order could not be saved.');
      try {
        const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data });
        const result = await response.json();
        if (!response.ok || !result?.success) {
          failureMessage = result?.data?.message || failureMessage;
          throw new Error(failureMessage);
        }
        const orders = result.data?.orders || {};
        rows().forEach((row) => {
          const id = row.querySelector('.iss-content-order-handle')?.dataset.postId;
          const value = row.querySelector('.iss-content-order-value');
          if (value && Object.prototype.hasOwnProperty.call(orders, id)) value.textContent = String(orders[id]);
        });
        notice(result.data?.message || text('saved', 'Order saved.'), 'success');
      } catch {
        restore();
        notice(failureMessage, 'error');
      } finally {
        setBusy(false);
        if (focusTarget?.isConnected && (document.activeElement === document.body || document.activeElement === focusTarget)) focusTarget.focus();
      }
    }

    function move(button, direction) {
      if (busy || dragged) return;
      const row = button.closest(selector);
      const current = rows();
      const target = current[current.indexOf(row) + direction];
      if (!row || !target) return;
      before = Array.from(body.children);
      body.insertBefore(row, direction < 0 ? target : target.nextSibling);
      save(button);
    }

    function decorateRows() {
      rows().forEach((row) => {
        const handle = row.querySelector('.iss-content-order-handle');
        if (!handle || row.querySelector('.iss-content-order-controls')) return;
        handle.draggable = true;
        const controls = document.createElement('span');
        controls.className = 'iss-content-order-controls';
        const title = row.querySelector('.row-title')?.textContent.trim() || '';
        [[-1, 'earlier', 'Move up', '↑'], [1, 'later', 'Move down', '↓']].forEach(([direction, key, fallback, symbol]) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'button-link iss-content-order-step';
          button.dataset.direction = String(direction);
          button.setAttribute('aria-label', `${text(key, fallback)}${title ? `: ${title}` : ''}`);
          button.textContent = symbol;
          controls.append(button);
        });
        handle.after(controls);
      });
    }

    body.addEventListener('click', (event) => {
      const button = event.target.closest('.iss-content-order-step');
      if (button) move(button, Number(button.dataset.direction));
    });
    body.addEventListener('keydown', (event) => {
      const button = event.target.closest('.iss-content-order-handle');
      if (button && ['ArrowUp', 'ArrowDown'].includes(event.key)) {
        event.preventDefault();
        move(button, event.key === 'ArrowUp' ? -1 : 1);
      }
    });
    body.addEventListener('dragstart', (event) => {
      const handle = event.target.closest('.iss-content-order-handle');
      if (busy || !handle) { event.preventDefault(); return; }
      dragged = handle.closest(selector);
      if (!dragged) { event.preventDefault(); return; }
      before = Array.from(body.children);
      dropped = false;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', handle.dataset.postId);
      dragged.classList.add('iss-content-order-dragging');
    });
    body.addEventListener('dragover', (event) => {
      if (!dragged || busy) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      const target = event.target.closest(selector);
      if (!target || target === dragged || target.parentElement !== body) return;
      const bounds = target.getBoundingClientRect();
      body.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextSibling);
    });
    body.addEventListener('drop', (event) => {
      if (!dragged || busy) return;
      event.preventDefault();
      dropped = true;
      const changed = before.some((row, index) => body.children[index] !== row);
      if (changed) save(dragged.querySelector('.iss-content-order-handle'));
    });
    body.addEventListener('dragend', () => {
      if (!dragged) return;
      dragged.classList.remove('iss-content-order-dragging');
      if (!dropped) restore();
      dragged = null;
    });
    document.body.classList.add('iss-content-order-enabled');
    decorateRows();
    // Core Quick Edit replaces table rows. Decorate those new native handles too.
    new MutationObserver(decorateRows).observe(body, { childList: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
