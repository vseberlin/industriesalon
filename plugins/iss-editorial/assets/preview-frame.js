(function () {
  var staleToken = document.currentScript && document.currentScript.getAttribute('data-iss-preview-stale');
  if (staleToken !== null && staleToken !== undefined && window.parent !== window) {
    window.parent.postMessage({ type: 'iss-preview-stale', token: staleToken }, window.location.origin); return;
  }
  var context = window.issEditorialPreviewFrame;
  if (!context || window.parent === window) { return; }
  document.body.classList.add('iss-editorial-preview-document');
  var origin = window.location.origin;
  var active = null;
  var count = 0;
  var toolbar = null;
  var labels = { title: 'Titel', kicker: 'Vorspann', body: 'Text', lead: 'Einleitung' };
  function send(type, data) { window.parent.postMessage(Object.assign({ type: type, token: context.token }, data || {}), origin); }
  function button(label, action) {
    var el = document.createElement('button'); el.type = 'button'; el.textContent = label;
    // Keep the caret until the explicit finish/cancel action; TinyMCE blur can otherwise commit first.
    el.addEventListener('mousedown', function (event) { if (toolbar && toolbar.contains(el)) { event.preventDefault(); } });
    el.addEventListener('click', action); return el;
  }
  function data(extra) { return Object.assign({ session: active.session, section: active.section, field: active.field, sequence: ++active.sequence }, extra || {}); }
  function changed(value) { if (active && active.started && !active.ending) { send('iss-preview-field-change', data({ value: value })); } }
  function value() { return active.editor ? active.editor.getContent() : active.node.textContent.replace(/\n/g, ' '); }
  function finish(cancel) {
    if (!active || !active.started || active.ending) { return; }
    if (!cancel) { changed(value()); }
    active.ending = true;
    send('iss-preview-field-end', data({ cancel: !!cancel }));
  }
  function teardown() {
    if (!active) { return; }
    if (active.editor) { active.editor.remove(); }
    active.node.innerHTML = active.html;
    active.node.removeAttribute('contenteditable'); active.node.removeAttribute('data-iss-editing');
    if (active.oldId) { active.node.id = active.oldId; } else { active.node.removeAttribute('id'); }
    active.node.focus();
    if (toolbar) { toolbar.remove(); toolbar = null; }
    active = null;
  }
  function begin(field) {
    if (active) { return; }
    var section = field.closest('[data-iss-preview-section]');
    active = { node: field, field: field.dataset.issField, section: Number(section.dataset.issPreviewSection), session: 'field-' + (++count), sequence: 0, html: field.innerHTML, oldId: field.id, started: false };
    send('iss-preview-field-start', { session: active.session, section: active.section, field: active.field, sequence: 0 });
  }
  function startEditing(reply) {
    active.started = true;
    toolbar = document.createElement('div'); toolbar.className = 'iss-preview-tools wp-core-ui';
    toolbar.setAttribute('role', 'region'); toolbar.setAttribute('aria-label', 'Text bearbeiten');
    var heading = document.createElement('strong'); heading.textContent = labels[active.field] + ' bearbeiten';
    toolbar.append(heading, button('Fertig', function () { finish(false); }), button('Abbrechen (Escape)', function () { finish(true); }));
    var notice = document.createElement('p'); notice.className = 'iss-preview-tools__status'; notice.setAttribute('role', 'status'); notice.textContent = 'Änderungen werden während der Eingabe gesichert.'; toolbar.appendChild(notice);
    document.body.appendChild(toolbar);
    active.node.setAttribute('data-iss-editing', '');
    if (reply.profile === 'plain') {
      active.node.textContent = reply.value;
      active.node.contentEditable = 'plaintext-only';
      active.node.focus();
      return;
    }
    if (!window.wp || !window.wp.editor || !window.tinymce || !window.issEditorialRichText || window.issEditorialRichText.hasUnsupportedMarkup(reply.value, reply.profile)) {
      notice.textContent = 'Diesen Text bitte im Formular bearbeiten; ältere Formatierung bleibt erhalten.';
      var fallbackSection = active.section;
      finish(true); send('iss-preview-form', { section: fallbackSection, message: notice.textContent }); return;
    }
    var input = active.node;
    input.innerHTML = reply.value;
    input.id = 'iss-preview-text-' + count;
    var tinyToolbar = document.createElement('div'); tinyToolbar.id = 'iss-preview-text-toolbar'; toolbar.appendChild(tinyToolbar);
    var settings = window.issEditorialRichText.configure({ profile: reply.profile, wrapper: toolbar, palette: context.palette, onChange: changed, onClose: function () { finish(true); } });
    var setup = settings.setup;
    settings.inline = true;
    settings.fixed_toolbar_container = '#iss-preview-text-toolbar';
    settings.content_style = '';
    settings.plugins = 'lists,paste,wordpress,wplink';
    settings.setup = function (editor) {
      setup(editor);
      editor.on('init', function () {
        if (!active || active.node !== input) { editor.remove(); return; }
        active.editor = editor; window.wpActiveEditor = editor.id; editor.focus();
      });
    };
    window.wp.editor.initialize(input.id, { mediaButtons: false, quicktags: false, tinymce: settings });
  }
  document.querySelectorAll('form').forEach(function (form) { form.inert = true; });
  var sections = document.querySelectorAll('[data-iss-preview-section]');
  sections.forEach(function (section) {
    section.tabIndex = 0;
    var heading = section.querySelector('h1, h2, h3');
    section.setAttribute('aria-label', 'Abschnitt bearbeiten: ' + (heading ? heading.textContent : String(Number(section.dataset.issPreviewSection) + 1)));
    if (!context.editing) { return; }
    var controls = document.createElement('div'); controls.className = 'iss-preview-section-tools';
    section.querySelectorAll('[data-iss-field]').forEach(function (field) {
      if (!labels[field.dataset.issField]) { return; }
      field.tabIndex = 0; field.setAttribute('aria-label', labels[field.dataset.issField] + ' bearbeiten');
      controls.appendChild(button(labels[field.dataset.issField] + ' bearbeiten', function () { begin(field); }));
    });
    controls.appendChild(button('Im Formular bearbeiten', function () { send('iss-preview-form', { section: Number(section.dataset.issPreviewSection) }); }));
    var label = document.createElement('span'); label.className = 'iss-preview-section-label'; label.textContent = 'Abschnitt ' + (Number(section.dataset.issPreviewSection) + 1); controls.prepend(label);
    section.prepend(controls);
    var gap = button('+ Abschnitt', function () { send('iss-preview-insert', { section: Number(section.dataset.issPreviewSection) }); }); gap.className = 'iss-preview-gap'; section.before(gap);
  });
  if (context.editing && sections.length) {
    var gap = button('+ Abschnitt', function () { send('iss-preview-insert', { section: Number(sections[sections.length - 1].dataset.issPreviewSection) + 1 }); }); gap.className = 'iss-preview-gap'; sections[sections.length - 1].after(gap);
  }
  function selectSection(section, event) {
    event.preventDefault(); event.stopImmediatePropagation();
    send('iss-preview-select', { section: Number(section.dataset.issPreviewSection), scroll: window.scrollY });
  }
  function isControl(target) { return target.closest('.iss-preview-tools, .iss-preview-section-tools, .iss-preview-gap, #wp-link-wrap, #wp-link-backdrop, .mce-container, .mce-widget'); }
  document.addEventListener('submit', function (event) { if (!event.target.closest('#wp-link-wrap')) { event.preventDefault(); event.stopImmediatePropagation(); } }, true);
  document.addEventListener('click', function (event) {
    if (isControl(event.target) || (active && active.node.contains(event.target))) { return; }
    if (active) { finish(false); }
    var section = event.target.closest('[data-iss-preview-section]');
    if (section) { selectSection(section, event); }
    else if (event.target.closest('a[href]')) { event.preventDefault(); event.stopImmediatePropagation(); }
  }, true);
  document.addEventListener('dblclick', function (event) {
    var field = event.target.closest('[data-iss-field]');
    if (context.editing && field && !active) { event.preventDefault(); begin(field); }
  });
  document.addEventListener('input', function (event) { if (active && active.started && !active.editor && event.target === active.node) { changed(value()); } });
  document.addEventListener('focusout', function () {
    window.setTimeout(function () {
      if (!active || !active.started || active.node.contains(document.activeElement) || isControl(document.activeElement)) { return; }
      // Parent-pane focus commits too; toolbar, colour picker and native link dialog remain part of this edit.
      finish(false);
    }, 0);
  });
  document.addEventListener('keydown', function (event) {
    if (active && event.target === active.node && !active.editor) {
      if (event.key === 'Escape') { event.preventDefault(); finish(true); }
      if (event.key === 'Enter') { event.preventDefault(); finish(false); }
      return;
    }
    if (context.editing && event.key === 'Enter' && event.target.matches('[data-iss-field]')) { event.preventDefault(); begin(event.target); }
    else if (['Enter', ' '].indexOf(event.key) !== -1 && event.target.matches('[data-iss-preview-section]')) { selectSection(event.target, event); }
  });
  window.addEventListener('message', function (event) {
    if (event.source !== window.parent || event.origin !== origin || !event.data || event.data.token !== context.token) { return; }
    var message = event.data;
    if (message.type === 'iss-preview-save-status' && toolbar && typeof message.message === 'string') { toolbar.querySelector('[role="status"]').textContent = message.message; return; }
    if (message.type === 'iss-preview-field-finish') { finish(false); return; }
    if (message.type === 'iss-preview-field-ack' && active && message.session === active.session) {
      if (!message.accepted) {
        var failedSection = active.section; teardown(); send('iss-preview-form', { section: failedSection, message: message.message || 'Bitte diesen Text im Formular bearbeiten.' }); return;
      }
      if (!active.started && message.sequence === 0) { startEditing(message); }
      else if (active.ending && message.sequence === active.sequence) { teardown(); }
      return;
    }
    if (message.type !== 'iss-preview-position' || active) { return; }
    var section = document.querySelector('[data-iss-preview-section="' + Number(message.section) + '"]');
    document.querySelectorAll('[data-iss-preview-section]').forEach(function (item) { item.toggleAttribute('data-iss-preview-active', item === section); });
    if (section && typeof message.label === 'string') { var label = section.querySelector('.iss-preview-section-label'); if (label) { label.textContent = message.label; } }
    if (message.scroll !== null && Number.isFinite(message.scroll)) { window.scrollTo(0, message.scroll); }
    else if (section && Number(message.section) === 0) { window.scrollTo(0, 0); }
    else if (section) { section.scrollIntoView({ block: 'start' }); }
  });
  var scheduled = false;
  window.addEventListener('scroll', function () {
    if (scheduled) { return; } scheduled = true;
    window.requestAnimationFrame(function () { scheduled = false; send('iss-preview-scroll', { scroll: window.scrollY }); });
  }, { passive: true });
  var header = document.querySelector('.iss-site-header');
  if (header && window.ResizeObserver) {
    var headerOffset = document.createElement('style'); document.head.appendChild(headerOffset);
    function measureHeader() { headerOffset.textContent = '.iss-editorial-preview-document{--iss-preview-header-offset:' + Math.ceil(header.getBoundingClientRect().height + 8) + 'px}'; }
    new window.ResizeObserver(measureHeader).observe(header); measureHeader();
  }
  send('iss-preview-ready');
})();
