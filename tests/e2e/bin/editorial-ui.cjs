/* DOM interaction checks. TinyMCE/media rendering still needs a real browser. */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

async function editor(format = 'landing', extra = {}) {
  const dom = new JSDOM('<div id="wpwrap"><form id="post"><input id="title" value="Page title"><textarea id="excerpt">Summary</textarea><select id="entity"><option value="event.general">Event</option><option value="event.festival">Festival</option></select><div class="iss-editorial-admin"><input class="iss-editorial-document-field"><input class="iss-editorial-enabled-field" value="1"><input class="iss-editorial-draft-token-field"><div class="iss-editorial-recovery"></div><p class="iss-editorial-autosave-status"></p><div class="iss-editorial-root"></div><button type="button" class="iss-editorial-preview-button">Preview</button></div><button type="submit">Update</button></form></div>', { url: 'http://localhost:8082/wp-admin/post.php', runScripts: 'outside-only', pretendToBeVisual: true });
  const { window } = dom;
  window.document.getElementById('wpwrap').inert = false;
  const sections = extra.sections || { kapitel: { label: 'Kapitel', supports: ['body', 'links'] }, intro: { label: 'Intro', ui_hidden: true, supports: ['body'] } };
  const document = extra.document || { schema_version: 1, skin: 'standard', sections: [{ type: 'kapitel', title: 'First section', body: '<p>Original text</p>', links: [{ label: 'External', url: 'https://elsewhere.example/about/' }] }, { type: 'intro', title: 'Legacy intro', body: 'Keep this text' }] };
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
  window.setTimeout = (fn, delay) => nativeSetTimeout(fn, [350, 1200].includes(delay) ? 10 : delay);
  window.fetch = async (_url, request) => {
    requests.push(new URLSearchParams(request.body));
    return { ok: !fail, json: async () => fail ? { success: false, data: { message: 'Draft conflict: reload required' } } : { success: true, data: { draftToken: 'draft-' + requests.length, previewUrl: '/?preview=true', validationMessage } } };
  };
  const rich = [];
  window.wp = { editor: { initialize: (id, options) => rich.push({ id, options }), remove: () => {} } };
  window.HTMLElement.prototype.getClientRects = function () { return this.hidden ? [] : [1]; };
  window.ResizeObserver = class { observe() {} disconnect() {} };
  for (const asset of ['ui.js', 'rich-text.js', 'live-preview.js', 'admin.js']) {
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
    assert.equal(e.root.hidden, true, 'Do not show a canvas whose controls cannot be used');
    assert.equal(e.button('Preview').disabled, true);
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /zuerst eine Fassung/);
    assert.equal(e.requests.length, 0);
    e.button('Entwurf weiterbearbeiten').click();
    await e.settle();
    assert.equal(e.root.inert, false);
    assert.equal(e.root.hidden, false);
    assert.equal(e.button('Preview').disabled, false);
    assert.equal(e.window.document.activeElement, e.button('Bearbeiten'), 'Continue directly with the recovered sections');
    assert.equal(e.value().sections[0].title, 'Recovered section');
    assert.equal(e.value().entity_key, 'event.festival');
    assert.equal(e.requests.at(-1).get('title'), 'Recovered title');
    assert.equal(e.requests.at(-1).get('excerpt'), 'Recovered summary');
  } finally { e.dom.window.close(); }
});

test('Failed draft discard keeps the choice visible; retry opens the saved canvas only after acknowledgement', async () => {
  const e = await editor('landing', { recovery: { base: 'base', modified: 'Today', document: {schema_version: 1, sections: [{type: 'kapitel', title: 'Private draft'}]} } });
  try {
    e.setFailure(true);
    e.button('Entwurf verwerfen').click();
    await e.settle();
    assert.equal(e.requests.at(-1).get('intent'), 'discard');
    assert.equal(e.root.hidden, true);
    assert.ok(e.button('Entwurf weiterbearbeiten'));
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Draft conflict/);
    e.setFailure(false);
    e.button('Entwurf verwerfen').click();
    await e.settle();
    assert.equal(e.root.hidden, false);
    assert.equal(e.root.inert, false);
    assert.equal(e.value().sections[0].title, 'First section');
    assert.equal(e.container.querySelector('.iss-editorial-recovery').textContent, '');
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


test('Landing v2 preserves colours, highlights and safe links but rejects arbitrary styling', async () => {
  const e = await editor();
  try {
    const rich = e.window.issEditorialRichText;
    const input = '<p><a href="/archive/"><strong><span class="iss-ink-123abc iss-mark-ffeeaa">History</span></strong></a></p>';
    assert.equal(rich.sanitize(input, 'block'), input);
    assert.equal(rich.hasUnsupportedMarkup(input, 'block'), false);
    assert.equal(rich.hasUnsupportedMarkup('<p style="color:red">Imported</p>', 'block'), true);
    assert.equal(rich.sanitize('<a href="java&#10;script:alert(1)">word</a>', 'inline'), 'word');
    assert.equal(rich.sanitize('<a href="/archive/"><em>word</em></a>', 'inline-card'), '<em>word</em>');
    assert.equal(rich.sanitize('<span class="iss-ink-red random" style="color:red">word</span>', 'inline'), '<span>word</span>');
    assert.equal(rich.sanitize('<a href="/archive/" target="_blank">word</a>', 'inline'), '<a href="/archive/" target="_blank" rel="noopener noreferrer">word</a>');
  } finally { e.dom.window.close(); }
});

test('Upgrade is explicit and escapes legacy literal item text, including deleted sections', async () => {
  const sections = { gateway: { label: 'Cards', supports: ['items'], rich_text: { body: 'block', items: 'inline-card' } } };
  const document = { schema_version: 1, skin: 'standard', sections: [{ type: 'gateway', items: [{label:'Item',text:'Literal <b>word</b> & label',url:'/'}] }], deleted_sections: [{type:'gateway',items:[{label:'Deleted',text:'Keep <i>literal</i>',url:'/'}]}] };
  const e = await editor('landing', { sections, document, supportedVersions: [1,2] });
  try {
    assert.equal(e.value().schema_version, 1);
    assert.equal(e.requests.length, 0);
    e.button('Textfarben aktivieren').click();
    assert.equal(e.value().schema_version, 2);
    assert.equal(e.value().sections[0].items[0].text, 'Literal &lt;b&gt;word&lt;/b&gt; &amp; label');
    assert.equal(e.value().deleted_sections[0].items[0].text, 'Keep &lt;i&gt;literal&lt;/i&gt;');
    e.button('Bearbeiten').click(); await e.settle();
    const card = e.rich.find(item => item.options.tinymce.forced_root_block === false);
    assert.ok(card);
    assert.ok(!card.options.tinymce.toolbar1.includes('isslink'));
    assert.ok(!card.options.tinymce.valid_elements.includes('a['));
  } finally { e.dom.window.close(); }
});

test('Unsupported imported markup remains intact until the author explicitly simplifies it', async () => {
  const e = await editor('landing', { sections: {kapitel:{label:'Text',supports:[],rich_text:{body:'block'}}}, document: {schema_version:2,skin:'standard',sections:[{type:'kapitel',body:'<p style="color:red">Old <strong>text</strong></p>'}]} });
  try {
    e.button('Bearbeiten').click(); await e.settle();
    assert.equal(e.rich.length, 0);
    assert.equal(e.value().sections[0].body, '<p style="color:red">Old <strong>text</strong></p>');
    e.button('Formatierung vereinfachen').click();
    assert.equal(e.rich.length, 1);
    assert.equal(e.value().sections[0].body, '<p>Old <strong>text</strong></p>');
  } finally { e.dom.window.close(); }
});

test('Live preview accepts only its current frame and token and retains valid output on failed refresh', async () => {
  const e = await editor();
  try {
    const shell = e.window.issEditorialUi.createModal({title:'Preview fixture'});
    e.window.document.body.appendChild(shell.root);
    const selected = [];
    const preview = e.window.issEditorialLivePreview(shell, 0, null, index => selected.push(index));
    const send = (frame, token, origin='http://localhost:8082') => e.window.dispatchEvent(new e.window.MessageEvent('message', {source:frame.contentWindow, origin, data:{type:'iss-preview-ready',token}}));
    const select = (frame, token, index, origin='http://localhost:8082') => e.window.dispatchEvent(new e.window.MessageEvent('message', {source:frame.contentWindow, origin, data:{type:'iss-preview-select',token,section:index,scroll:400}}));
    preview.update('/?preview=true', 'one');
    const first = shell.root.querySelector('iframe');
    assert.equal(first.getAttribute('sandbox'), 'allow-scripts allow-same-origin');
    send(first, 'one', 'https://elsewhere.example'); assert.equal(first.hidden, true);
    send(first, 'wrong'); assert.equal(first.hidden, true);
    select(first, 'one', 1); assert.deepEqual(selected, [], 'Hidden pending frames cannot select sections');
    send(first, 'one'); assert.equal(first.hidden, false);
    select(first, 'wrong', 1); select(first, 'one', 1, 'https://elsewhere.example'); select(first, 'one', '1'); select(first, 'one', -1);
    assert.deepEqual(selected, [], 'Selection also checks source, origin, token and integer index');
    select(first, 'one', 1); assert.deepEqual(selected, [1]);
    preview.update('/?preview=true', 'two');
    const second = shell.root.querySelector('iframe[hidden]');
    preview.stale('Incomplete link');
    assert.equal(first.isConnected, true);
    assert.equal(second.isConnected, false);
    assert.match(shell.root.querySelector('[role=status]').textContent, /vorige Fassung/);
    preview.update('/?preview=true', 'three');
    const third = shell.root.querySelector('iframe[hidden]');
    send(first, 'one'); assert.equal(third.hidden, true);
    send(third, 'three'); assert.equal(first.isConnected, false); assert.equal(third.hidden, false);
    select(first, 'one', 2); assert.deepEqual(selected, [1], 'Replaced frames cannot change the selected section');
    preview.destroy();
  } finally { e.dom.window.close(); }
});

test('Section navigation retains pending edits and the displayed preview; discard applies only to the active section', async () => {
  const e = await editor('landing', {livePreview:true});
  try {
    e.button('Bearbeiten').click(); await e.settle();
    const shell = e.window.document.querySelector('.iss-editorial-modal');
    const frame = shell.querySelector('iframe');
    const send = data => e.window.dispatchEvent(new e.window.MessageEvent('message', {source:frame.contentWindow,origin:'http://localhost:8082',data:{...data,token:frame.dataset.token}}));
    send({type:'iss-preview-ready'});
    const positions = [];
    frame.contentWindow.postMessage = message => positions.push(message);
    const field = () => [...shell.querySelectorAll('label')].find(node=>node.firstChild.textContent === 'Titel').querySelector('input');
    const select = shell.querySelector('[aria-label="Abschnitt wählen"]');
    e.input(field(), 'First edited');
    select.value = '1'; select.dispatchEvent(new e.window.Event('change'));
    assert.equal(field().value, 'Legacy intro');
    assert.equal(e.value().sections[0].title, 'First edited');
    assert.equal(shell.querySelector('iframe'), frame, 'Changing section does not reload the preview');
    assert.equal(positions.at(-1).section, 1);
    assert.equal(positions.at(-1).scroll, null, 'Editor selection reveals the corresponding preview section');
    e.input(field(), 'Second edited');
    e.button('Vorschau', shell).click();
    send({type:'iss-preview-select',section:99,scroll:400});
    assert.equal(field().value, 'Second edited', 'Out-of-range section is ignored');
    send({type:'iss-preview-select',section:0,scroll:400});
    assert.equal(field().value, 'First edited');
    assert.equal(select.value, '0');
    assert.equal(shell.querySelector('.iss-editorial-workspace').dataset.tab, 'edit', 'On narrow screens a preview click opens editing');
    assert.equal(positions.at(-1).scroll, 400, 'Preview click retains the reader position');
    await e.settle();
    const saved = JSON.parse(e.requests.at(-1).get('document'));
    assert.equal(saved.sections[0].title, 'First edited');
    assert.equal(saved.sections[1].title, 'Second edited');
    e.button('Änderungen im Abschnitt verwerfen', shell).click();
    await e.settle();
    assert.equal(e.value().sections[0].title, 'First section');
    assert.equal(e.value().sections[1].title, 'Second edited', 'Discard does not undo edits in another section');
  } finally { e.dom.window.close(); }
});

test('Embedded preview selects sections by click or keyboard and marks only authenticated position messages', () => {
  const dom = new JSDOM('<section data-iss-preview-section="0"><h2>Opening</h2><a href="/leave/"><span>Link</span></a></section><section data-iss-preview-section="1"><h2>Story</h2><form><button>Submit</button></form></section>', {url:'http://localhost:8082/?preview=true',runScripts:'outside-only'});
  const {window} = dom;
  try {
    const messages = [];
    const parent = {postMessage:message=>messages.push(message)};
    Object.defineProperty(window, 'parent', {value:parent});
    window.issEditorialPreviewFrame = {token:'current'};
    window.scrollTo = () => {};
    let revealed = null;
    window.HTMLElement.prototype.scrollIntoView = function () { revealed = this.dataset.issPreviewSection; };
    window.eval(fs.readFileSync('plugins/iss-editorial/assets/preview-frame.js', 'utf8'));
    let navigated = false;
    window.document.querySelector('a').addEventListener('click', () => { navigated = true; });
    window.document.querySelector('a span').click();
    assert.equal(navigated, false, 'Section selection prevents the public link action');
    assert.equal(messages.at(-1).section, 0);
    const story = window.document.querySelector('[data-iss-preview-section="1"]');
    assert.equal(story.tabIndex, 0);
    story.dispatchEvent(new window.KeyboardEvent('keydown', {key:'Enter',bubbles:true}));
    assert.equal(messages.at(-1).section, 1);
    assert.equal(window.document.querySelector('form').inert, true);
    const position = (token, source=parent) => window.dispatchEvent(new window.MessageEvent('message', {source,origin:window.location.origin,data:{type:'iss-preview-position',token,section:1,scroll:null}}));
    position('old'); position('current', {});
    assert.equal(revealed, null);
    position('current');
    assert.equal(revealed, '1');
    assert.equal(story.hasAttribute('data-iss-preview-active'), true);
    assert.equal(window.document.querySelectorAll('[data-iss-preview-active]').length, 1);
  } finally { window.close(); }
});
