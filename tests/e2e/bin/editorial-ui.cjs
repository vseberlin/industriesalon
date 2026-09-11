/* DOM interaction checks. TinyMCE/media rendering still needs a real browser. */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

async function editor(format = 'landing', extra = {}) {
  const dom = new JSDOM('<div id="wpwrap"><form id="post"><input id="title" value="Page title"><textarea id="excerpt">Summary</textarea><select id="entity"><option value="event.general">Event</option><option value="event.festival">Festival</option></select><div class="iss-editorial-admin"><input class="iss-editorial-document-field"><input class="iss-editorial-enabled-field" value="1"><input class="iss-editorial-draft-token-field"><div class="iss-editorial-root"></div><p class="iss-editorial-autosave-status"></p><div class="iss-editorial-recovery"></div><button type="button" class="iss-editorial-preview-button">Preview</button></div><button type="submit">Update</button></form></div>', { url: 'http://localhost:8082/wp-admin/post.php', runScripts: 'outside-only', pretendToBeVisual: true });
  const { window } = dom;
  window.document.getElementById('wpwrap').inert = false;
  const sections = { kapitel: { label: 'Kapitel', supports: ['body', 'links'] }, intro: { label: 'Intro', ui_hidden: true, supports: ['body'] } };
  const document = { schema_version: 1, skin: 'standard', sections: [{ type: 'kapitel', title: 'First section', body: '<p>Original text</p>', links: [{ label: 'External', url: 'https://elsewhere.example/about/' }] }, { type: 'intro', title: 'Legacy intro', body: 'Keep this text' }] };
  const root = window.document.querySelector('.iss-editorial-root');
  root.dataset.sections = JSON.stringify(sections);
  root.dataset.document = JSON.stringify(document);
  const container = root.parentElement;
  container.dataset.format = format;
  container.dataset.postId = '99';
  const requests = [];
  let fail = false;
  let validationMessage = '';
  window.issEditorialAdmin = { postId: 99, ajaxUrl: '/wp-admin/admin-ajax.php', previewNonce: 'test', baseToken: 'base', pageChoices: [{ id: 2, url: 'http://localhost:8082/about/', title: 'About' }], sections, skins: [], ...extra };
  const nativeSetTimeout = window.setTimeout.bind(window);
  window.setTimeout = (fn, delay) => nativeSetTimeout(fn, delay === 1200 ? 10 : delay);
  window.fetch = async (_url, request) => {
    requests.push(new URLSearchParams(request.body));
    return { ok: !fail, json: async () => fail ? { success: false, data: { message: 'Draft conflict: reload required' } } : { success: true, data: { draftToken: 'draft-' + requests.length, previewUrl: '/?preview=true', validationMessage } } };
  };
  const rich = [];
  window.wp = { editor: { initialize: (id, options) => rich.push({ id, options }), remove: () => {} } };
  window.HTMLElement.prototype.getClientRects = function () { return this.hidden ? [] : [1]; };
  for (const asset of ['ui.js', 'admin.js']) {
    window.eval(fs.readFileSync('plugins/iss-editorial/assets/' + asset, 'utf8'));
  }
  // Let jsdom emit its single DOMContentLoaded event.
  await new Promise(resolve => setTimeout(resolve, 20));
  const button = (text, within = window.document) => [...within.querySelectorAll('button')].find(node => node.textContent === text);
  const value = () => JSON.parse(container.querySelector('.iss-editorial-document-field').value);
  const input = (node, value) => { node.value = value; node.dispatchEvent(new window.Event('input', { bubbles: true })); };
  const settle = () => new Promise(resolve => setTimeout(resolve, 35));
  return { window, dom, root, container, requests, rich, button, value, input, settle, setFailure: value => { fail = value; }, setValidation: value => { validationMessage = value; } };
}

for (const format of ['landing', 'projekt', 'veranstaltung', 'place', 'fuehrung', 'publication', 'ausstellung', 'rueckblick']) {
  test(format + ': same section editing, discard, keyboard focus, reorder and recovery storage', async () => {
    const e = await editor(format);
    try {
      assert.equal(e.root.querySelectorAll('.iss-editorial-card').length, 2, 'Stored hidden intro remains visible');
      e.button('Bearbeiten').focus();
      e.button('Bearbeiten').click();
      await e.settle();
      const dialog = e.window.document.querySelector('[role="dialog"]');
      assert.equal(dialog.getAttribute('aria-modal'), 'true');
      assert.equal(e.window.document.getElementById('wpwrap').inert, true);
      assert.ok(dialog.contains(e.window.document.activeElement));
      assert.equal(e.rich.length, 1, 'Every format uses WordPress rich editor');
      assert.equal(e.value().sections[0].links[0].url, 'https://elsewhere.example/about/', 'Opening a section never rewrites an external URL');
      const title = [...dialog.querySelectorAll('label')].find(node => node.firstChild.textContent === 'Titel').querySelector('input');
      e.input(title, 'Temporary change');
      e.button('Änderungen verwerfen', dialog).click();
      await e.settle();
      assert.equal(e.value().sections[0].title, 'First section');
      assert.equal(e.window.document.getElementById('wpwrap').inert, false);
      assert.equal(e.window.document.activeElement.classList.contains('iss-editorial-card__edit'), true);
      e.button('Nach unten').click();
      assert.equal(e.value().sections[0].title, 'Legacy intro');
      e.button('In Papierkorb').click();
      assert.equal(e.value().deleted_sections[0].title, 'Legacy intro');
      e.button('Wiederherstellen').click();
      await e.settle();
      assert.equal(e.value().sections.length, 2);
      assert.equal(e.value().deleted_sections.length, 0);
      e.input(e.window.document.getElementById('title'), 'Changed page title');
      e.input(e.window.document.getElementById('excerpt'), 'Changed summary');
      await e.settle();
      const request = e.requests.at(-1);
      assert.equal(request.get('title'), 'Changed page title');
      assert.equal(request.get('excerpt'), 'Changed summary');
      assert.equal(request.get('base'), 'base');
      assert.match(request.get('draft_token'), /^draft-/);
      assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Entwurf gesichert/);
    } finally { e.dom.window.close(); }
  });
}

test('Recovery is explicit and restores title, summary and event structure with the document', async () => {
  const e = await editor('veranstaltung', { recovery: { base: 'older', modified: 'Today', title: 'Recovered title', excerpt: 'Recovered summary', document: { schema_version: 1, skin: 'standard', entity_key: 'event.festival', sections: [{ type: 'kapitel', title: 'Recovered section' }] } }, documentBindings: { entity_key: '#entity' } });
  try {
    assert.equal(e.root.inert, true);
    assert.equal(e.requests.length, 0);
    e.button('Entwurf wiederherstellen').click();
    await e.settle();
    assert.equal(e.root.inert, false);
    assert.equal(e.value().sections[0].title, 'Recovered section');
    assert.equal(e.value().entity_key, 'event.festival');
    assert.equal(e.requests.at(-1).get('title'), 'Recovered title');
    assert.equal(e.requests.at(-1).get('excerpt'), 'Recovered summary');
  } finally { e.dom.window.close(); }
});

test('Failed autosave keeps local content, reports the error, blocks update and permits retry', async () => {
  const e = await editor();
  try {
    e.setFailure(true);
    e.input(e.window.document.getElementById('title'), 'Unsaved work');
    await e.settle();
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Draft conflict/);
    assert.equal(e.button('Erneut sichern').hidden, false);
    assert.equal(e.window.document.getElementById('title').value, 'Unsaved work');
    const submit = new e.window.Event('submit', { bubbles: true, cancelable: true });
    e.window.document.getElementById('post').dispatchEvent(submit);
    assert.equal(submit.defaultPrevented, true);
    await e.settle();
    e.setFailure(false);
    e.button('Erneut sichern').click();
    await e.settle();
    assert.equal(e.button('Erneut sichern').hidden, true);
  } finally { e.dom.window.close(); }
});

test('Unfinished drafts are acknowledged as secured while publication remains blocked', async () => {
  const e = await editor();
  try {
    e.setValidation('Link-Adresse fehlt');
    e.input(e.window.document.getElementById('title'), 'Work in progress');
    await e.settle();
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Entwurf gesichert.*Link-Adresse fehlt/);
    assert.equal(e.button('Erneut sichern').hidden, true);
    let submitted = false;
    e.window.document.getElementById('post').requestSubmit = () => { submitted = true; };
    e.window.document.getElementById('post').dispatchEvent(new e.window.Event('submit', { bubbles: true, cancelable: true }));
    await e.settle();
    assert.equal(submitted, false);
    assert.match(e.container.querySelector('.iss-editorial-draft-token-field').value, /^draft-/);
  } finally { e.dom.window.close(); }
});
