(function () {
  var staleToken = document.currentScript && document.currentScript.getAttribute('data-iss-preview-stale');
  if (staleToken !== null && staleToken !== undefined && window.parent !== window) {
    window.parent.postMessage({ type: 'iss-preview-stale', token: staleToken }, window.location.origin);
    return;
  }
  var context = window.issEditorialPreviewFrame;
  if (!context || window.parent === window) { return; }
  var origin = window.location.origin;
  function send(type, data) {
    window.parent.postMessage(Object.assign({ type: type, token: context.token }, data || {}), origin);
  }
  document.querySelectorAll('form').forEach(function (form) { form.inert = true; });
  document.querySelectorAll('[data-iss-preview-section]').forEach(function (section) {
    section.tabIndex = 0;
    var heading = section.querySelector('h1, h2, h3');
    section.setAttribute('aria-label', 'Abschnitt bearbeiten: ' + (heading ? heading.textContent : String(Number(section.dataset.issPreviewSection) + 1)));
  });
  function selectSection(section, event) {
    event.preventDefault(); event.stopImmediatePropagation();
    send('iss-preview-select', { section: Number(section.dataset.issPreviewSection), scroll: window.scrollY });
  }
  document.addEventListener('submit', function (event) { event.preventDefault(); event.stopImmediatePropagation(); }, true);
  document.addEventListener('click', function (event) {
    var section = event.target.closest('[data-iss-preview-section]');
    if (section) { selectSection(section, event); }
    else if (event.target.closest('a[href]')) { event.preventDefault(); event.stopImmediatePropagation(); }
  }, true);
  document.addEventListener('keydown', function (event) {
    if (['Enter', ' '].indexOf(event.key) !== -1 && event.target.matches('[data-iss-preview-section]')) { selectSection(event.target, event); }
  });
  window.addEventListener('message', function (event) {
    if (event.source !== window.parent || event.origin !== origin || !event.data || event.data.token !== context.token || event.data.type !== 'iss-preview-position') { return; }
    var section = document.querySelector('[data-iss-preview-section="' + Number(event.data.section) + '"]');
    document.querySelectorAll('[data-iss-preview-section]').forEach(function (item) { item.toggleAttribute('data-iss-preview-active', item === section); });
    if (event.data.scroll !== null && Number.isFinite(event.data.scroll)) {
      window.scrollTo(0, event.data.scroll);
    } else if (section) {
      section.scrollIntoView({ block: 'start' });
    }
  });
  var scheduled = false;
  window.addEventListener('scroll', function () {
    if (scheduled) { return; }
    scheduled = true;
    window.requestAnimationFrame(function () { scheduled = false; send('iss-preview-scroll', { scroll: window.scrollY }); });
  }, { passive: true });
  send('iss-preview-ready');
})();
