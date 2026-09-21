(function () {
  function element(tag, className, text) {
    var node = document.createElement(tag);
    node.className = className;
    if (text) { node.textContent = text; }
    return node;
  }
  window.issEditorialLivePreview = function (shell, section, onRetry, onSelectSection, options) {
    options = options || {};
    var persistent = !!shell.previewMount;
    if (!persistent) { shell.root.classList.add('iss-editorial-modal--preview'); }
    var workspace = element('div', 'iss-editorial-workspace');
    var pane = element('section', 'iss-editorial-live-preview');
    pane.setAttribute('aria-label', 'Live-Vorschau');
    var toolbar = element('div', 'iss-editorial-live-preview__toolbar');
    var status = element('p', 'iss-editorial-live-preview__status', 'Vorschau wird vorbereitet …');
    status.setAttribute('role', 'status');
    var viewport = element('div', 'iss-editorial-live-preview__viewport');
    var select = element('select', '');
    select.setAttribute('aria-label', 'Vorschau-Breite');
    [['desktop', 'Desktop · 1280 px'], ['tablet', 'Tablet · 768 px'], ['phone', 'Telefon · 390 px']].forEach(function (choice) {
      var option = element('option', '', choice[1]); option.value = choice[0]; select.appendChild(option);
    });
    viewport.dataset.device = 'desktop';
    select.addEventListener('change', function () { viewport.dataset.device = select.value; resize(); });
    if (!persistent) { toolbar.appendChild(element('strong', '', 'Live-Vorschau')); }
    if (persistent) {
      var devices = element('div', 'iss-editorial-preview-devices');
      devices.setAttribute('role', 'group'); devices.setAttribute('aria-label', 'Vorschau-Breite');
      [['desktop', 'Desktop'], ['tablet', 'Tablet'], ['phone', 'Telefon']].forEach(function (choice) {
        var device = element('button', 'iss-editorial-preview-device', choice[1]); device.type = 'button'; device.dataset.device = choice[0];
        device.setAttribute('aria-pressed', String(choice[0] === 'desktop'));
        device.addEventListener('click', function () { select.value = choice[0]; viewport.dataset.device = choice[0]; resize(); Array.from(devices.children).forEach(function (item) { item.setAttribute('aria-pressed', String(item === device)); }); }); devices.appendChild(device);
      }); toolbar.appendChild(devices);
    } else { toolbar.appendChild(select); }
    var retry = element('button', persistent ? 'iss-editorial-studio__button iss-editorial-studio__icon' : 'button', persistent ? '↻' : 'Vorschau aktualisieren'); retry.type = 'button';
    retry.setAttribute('aria-label', 'Vorschau aktualisieren'); retry.title = 'Vorschau aktualisieren';
    retry.addEventListener('click', function () { if (onRetry) { onRetry(); } });
    toolbar.appendChild(retry);
    if (persistent) { shell.previewTools.appendChild(toolbar); shell.previewStatus.appendChild(status); pane.classList.add('iss-editorial-live-preview--canvas'); }
    else { pane.appendChild(toolbar); pane.appendChild(status); }
    pane.appendChild(viewport);
    if (persistent) { shell.previewMount.appendChild(pane); }
    else { shell.body.before(workspace); workspace.appendChild(shell.body); workspace.appendChild(pane); }
    var tabs = element('div', 'iss-editorial-workspace__tabs');
    function setTab(value) {
      workspace.dataset.tab = value;
      Array.from(tabs.children).forEach(function (tab) { tab.setAttribute('aria-pressed', String(tab.dataset.tab === value)); });
    }
    [['edit', 'Bearbeiten'], ['preview', 'Vorschau']].forEach(function (choice) {
      var button = element('button', 'button', choice[1]); button.type = 'button';
      button.dataset.tab = choice[0];
      button.setAttribute('aria-pressed', String(choice[0] === 'edit'));
      button.addEventListener('click', function () { setTab(choice[0]); });
      tabs.appendChild(button);
    });
    if (!persistent) { workspace.before(tabs); workspace.dataset.tab = 'edit'; }
    var sectionRef = null;
    function previewStatus(message, state) { status.textContent = message; status.dataset.state = state; pane.dataset.state = state; }
    var shown = null;
    var pending = null;
    var timer = null;
    var scroll = null;
    var currentToken = '';
    var destroyed = false;
    var editing = false;
    var waitingActions = [];
    function tell(data) { if (shown) { shown.contentWindow.postMessage(Object.assign({ token: shown.dataset.token }, data), window.location.origin); } }
    function afterEditing(action) {
      if (!editing) { action(); return; }
      waitingActions.push(action); tell({ type: 'iss-preview-field-finish' });
    }
    function position() {
      if (shown && !editing) {
        var index = sectionRef && shown.issSnapshot ? shown.issSnapshot.refs.indexOf(sectionRef) : section;
        var label = shown.issSnapshot && shown.issSnapshot.labels ? shown.issSnapshot.labels[index] : '';
        shown.contentWindow.postMessage({ type: 'iss-preview-position', token: shown.dataset.token, section: index, label: label, scroll: scroll }, window.location.origin);
      }
    }
    function cancelPending() {
      window.clearTimeout(timer);
      if (pending) { pending.remove(); pending = null; }
    }
    function stale(message) {
      cancelPending();
      currentToken = '';
      previewStatus(message + (shown && !editing ? ' Die Vorschau zeigt die vorige Fassung.' : ''), 'stale');
    }
    function receive(event) {
      if (destroyed || event.origin !== window.location.origin || !event.data) { return; }
      if (shown && event.source === shown.contentWindow && event.data.token === shown.dataset.token) {
        if (['iss-preview-scroll', 'iss-preview-select'].indexOf(event.data.type) !== -1 && Number.isFinite(event.data.scroll)) { scroll = event.data.scroll; }
        if (event.data.type === 'iss-preview-select' && Number.isInteger(event.data.section) && event.data.section >= 0 && onSelectSection) { onSelectSection(event.data.section, shown.issSnapshot); }
      }
      if (shown && event.source === shown.contentWindow && event.data.token === shown.dataset.token && options.field && /^iss-preview-field-(start|change|end)$/.test(event.data.type)) {
        var response = options.field(event.data, shown.issSnapshot);
        if (event.data.type === 'iss-preview-field-start' && response.accepted) { editing = true; cancelPending(); }
        tell(Object.assign({ type: 'iss-preview-field-ack', session: event.data.session, sequence: event.data.sequence || 0 }, response));
        if (event.data.type === 'iss-preview-field-end' && response.accepted) {
          editing = false;
          var actions = waitingActions.splice(0); actions.forEach(function (action) { action(); });
        }
      }
      if (shown && event.source === shown.contentWindow && event.data.token === shown.dataset.token && event.data.type === 'iss-preview-insert' && options.insert && Number.isInteger(event.data.section) && event.data.section >= 0 && event.data.section <= shown.issSnapshot.refs.length) { options.insert(event.data.section, shown.issSnapshot); }
      if (shown && event.source === shown.contentWindow && event.data.token === shown.dataset.token && event.data.type === 'iss-preview-form' && options.form && Number.isInteger(event.data.section)) { options.form(event.data.section, shown.issSnapshot, event.data.message); }
      if (!pending || event.source !== pending.contentWindow || event.data.token !== currentToken || ['iss-preview-ready', 'iss-preview-stale'].indexOf(event.data.type) === -1) { return; }
      if (event.data.type === 'iss-preview-stale') { stale('Vorschau ist nicht mehr aktuell. Bitte Vorschau aktualisieren.'); return; }
      window.clearTimeout(timer);
      if (shown) { shown.remove(); }
      shown = pending; pending = null; shown.hidden = false;
      position();
      previewStatus('Vorschau aktuell', 'ready');
    }
    var sizing = document.createElement('style');
    pane.appendChild(sizing);
    function resize() {
      var width = { desktop: 1280, tablet: 768, phone: 390 }[select.value];
      var available = viewport.clientWidth;
      if (!available || !viewport.clientHeight) { return; }
      var scale = Math.min(1, available / width);
      // Numeric geometry only, in a workspace stylesheet rather than inline attributes.
      sizing.textContent = '.iss-editorial-live-preview__frame{width:' + width + 'px;height:' + (viewport.clientHeight / scale) + 'px;zoom:' + scale + '}';
    }
    var observer = new window.ResizeObserver(resize);
    observer.observe(viewport);
    window.addEventListener('message', receive);
    return {
      stale: stale,
      reportSave: function (message) { tell({ type: 'iss-preview-save-status', message: message }); },
      selectSection: function (index, reveal, ref) {
        section = index; sectionRef = ref || null;
        if (reveal) { scroll = null; }
        position();
      },
      afterEditing: afterEditing,
      edit: function () { setTab('edit'); },
      update: function (url, token, snapshot) {
        if (editing) { return; }
        if (destroyed) { return; }
        cancelPending(); currentToken = token;
        var address = new URL(url, window.location.href);
        if (address.origin !== window.location.origin) { stale('Vorschau-Adresse gehört zu einer anderen Website.'); return; }
        address.searchParams.set('iss_editorial_embed', '1');
        if (persistent) { address.searchParams.set('iss_editorial_canvas', '1'); }
        address.searchParams.set('iss_editorial_snapshot', token);
        pending = element('iframe', 'iss-editorial-live-preview__frame');
        pending.issSnapshot = snapshot;
        pending.title = 'Seitenvorschau'; pending.hidden = true; pending.dataset.token = token;
        // Restricts forms/popups as an interaction guard; authentication and snapshot checks protect the draft.
        pending.setAttribute('sandbox', 'allow-scripts allow-same-origin');
        pending.src = address.href; viewport.appendChild(pending);
        previewStatus('Vorschau wird geladen …', 'loading');
        timer = window.setTimeout(function () { stale('Vorschau konnte nicht geladen werden. Erneut sichern.'); }, 12000);
      },
      destroy: function () { destroyed = true; cancelPending(); observer.disconnect(); window.removeEventListener('message', receive); }
    };
  };
})();
