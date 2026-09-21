(function () {
  function element(tag, className, text) {
    var node = document.createElement(tag);
    node.className = className;
    if (text) { node.textContent = text; }
    return node;
  }
  window.issEditorialLivePreview = function (shell, section, onRetry, onSelectSection) {
    shell.root.classList.add('iss-editorial-modal--preview');
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
    toolbar.appendChild(element('strong', '', 'Live-Vorschau'));
    toolbar.appendChild(select);
    var retry = element('button', 'button', 'Vorschau aktualisieren'); retry.type = 'button';
    retry.addEventListener('click', function () { if (onRetry) { onRetry(); } });
    toolbar.appendChild(retry);
    pane.appendChild(toolbar); pane.appendChild(status); pane.appendChild(viewport);
    shell.body.before(workspace);
    workspace.appendChild(shell.body); workspace.appendChild(pane);
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
    workspace.before(tabs); workspace.dataset.tab = 'edit';
    var shown = null;
    var pending = null;
    var timer = null;
    var scroll = null;
    var currentToken = '';
    var destroyed = false;
    function position() {
      if (shown) { shown.contentWindow.postMessage({ type: 'iss-preview-position', token: shown.dataset.token, section: section, scroll: scroll }, window.location.origin); }
    }
    function cancelPending() {
      window.clearTimeout(timer);
      if (pending) { pending.remove(); pending = null; }
    }
    function stale(message) {
      cancelPending();
      currentToken = '';
      status.textContent = message + (shown ? ' Die Vorschau zeigt die vorige Fassung.' : '');
      pane.dataset.state = 'stale';
    }
    function receive(event) {
      if (destroyed || event.origin !== window.location.origin || !event.data) { return; }
      if (shown && event.source === shown.contentWindow && event.data.token === shown.dataset.token) {
        if (['iss-preview-scroll', 'iss-preview-select'].indexOf(event.data.type) !== -1 && Number.isFinite(event.data.scroll)) { scroll = event.data.scroll; }
        if (event.data.type === 'iss-preview-select' && Number.isInteger(event.data.section) && event.data.section >= 0 && onSelectSection) { onSelectSection(event.data.section); }
      }
      if (!pending || event.source !== pending.contentWindow || event.data.token !== currentToken || ['iss-preview-ready', 'iss-preview-stale'].indexOf(event.data.type) === -1) { return; }
      if (event.data.type === 'iss-preview-stale') { stale('Vorschau ist nicht mehr aktuell. Bitte Vorschau aktualisieren.'); return; }
      window.clearTimeout(timer);
      if (shown) { shown.remove(); }
      shown = pending; pending = null; shown.hidden = false;
      position();
      status.textContent = 'Vorschau aktuell · Entwurf, noch nicht veröffentlicht';
      pane.dataset.state = 'ready';
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
      selectSection: function (index, reveal) {
        section = index;
        if (reveal) { scroll = null; }
        position();
      },
      edit: function () { setTab('edit'); },
      update: function (url, token) {
        if (destroyed) { return; }
        cancelPending(); currentToken = token;
        var address = new URL(url, window.location.href);
        if (address.origin !== window.location.origin) { stale('Vorschau-Adresse gehört zu einer anderen Website.'); return; }
        address.searchParams.set('iss_editorial_embed', '1');
        address.searchParams.set('iss_editorial_snapshot', token);
        pending = element('iframe', 'iss-editorial-live-preview__frame');
        pending.title = 'Seitenvorschau'; pending.hidden = true; pending.dataset.token = token;
        // Restricts forms/popups as an interaction guard; authentication and snapshot checks protect the draft.
        pending.setAttribute('sandbox', 'allow-scripts allow-same-origin');
        pending.src = address.href; viewport.appendChild(pending);
        status.textContent = 'Vorschau wird geladen …'; pane.dataset.state = 'loading';
        timer = window.setTimeout(function () { stale('Vorschau konnte nicht geladen werden. Erneut sichern.'); }, 12000);
      },
      destroy: function () { destroyed = true; cancelPending(); observer.disconnect(); window.removeEventListener('message', receive); }
    };
  };
})();
