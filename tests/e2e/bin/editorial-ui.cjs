/* DOM interaction checks. TinyMCE/media rendering still needs a real browser. */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

async function editor(format = 'landing', extra = {}) {
  const dom = new JSDOM('<div id="wpwrap"><form id="post"><input id="title" value="Page title"><textarea id="excerpt">Summary</textarea><select id="entity"><option value="event.general">Event</option><option value="event.festival">Festival</option></select><div class="iss-editorial-admin"><input class="iss-editorial-document-field"><input class="iss-editorial-enabled-field" value="1"><input class="iss-editorial-draft-token-field"><div class="iss-editorial-recovery"></div><p class="iss-editorial-autosave-status"></p><div class="iss-editorial-root"></div><button type="button" class="iss-editorial-preview-button">Preview</button></div><button type="submit">Update</button></form><div id="wp-link-backdrop" hidden></div><div id="wp-link-wrap" hidden></div></div>', { url: 'http://localhost:8082/wp-admin/post.php', runScripts: 'outside-only', pretendToBeVisual: true });
  const { window } = dom;
  window.document.getElementById('wpwrap').inert = false;
  const sections = extra.sections || { kapitel: { label: 'Kapitel', supports: ['body', 'links'] }, intro: { label: 'Intro', ui_hidden: true, supports: ['body'] } };
  const document = extra.document || { schema_version: 1, skin: 'standard', sections: [{ type: 'kapitel', title: 'First section', body: '<p>Original text</p>', links: [{ label: 'External', url: 'https://elsewhere.example/about/' }] }, { type: 'intro', title: 'Legacy intro', body: 'Keep this text' }] };
  if (document.entity_key) { window.document.getElementById('entity').value = document.entity_key; }
  const root = window.document.querySelector('.iss-editorial-root');
  root.dataset.sections = JSON.stringify(sections);
  root.parentElement.querySelector('.iss-editorial-document-field').value = JSON.stringify(document);
  const container = root.parentElement;
  container.dataset.format = format;
  container.dataset.postId = '99';
  const requests = [];
  let fail = false;
  let validationMessage = '';
  window.issEditorialAdmin = { workspace: true, livePreview: true, postId: 99, ajaxUrl: '/wp-admin/admin-ajax.php', previewUrl: '/?preview=true', previewNonce: 'test', baseToken: 'base', pageChoices: [{ id: 2, url: 'http://localhost:8082/about/', title: 'About' }], sections, skins: [], ...extra };
  const nativeSetTimeout = window.setTimeout.bind(window);
  window.setTimeout = extra.clock ? extra.clock.setTimeout : (fn, delay) => nativeSetTimeout(fn, [350, 1200, 2000].includes(delay) ? 10 : delay);
  if (extra.clock) { window.clearTimeout = extra.clock.clearTimeout; }
  window.fetch = async (_url, request) => {
    requests.push(new URLSearchParams(request.body));
    if (extra.beforeResponse) { await extra.beforeResponse(requests.at(-1)); }
    if (requests.at(-1).get('intent') === 'inspect') {
      return {ok:true,json:async()=>({success:true,data:{baseToken:'base',canonical:{document,title:'Page title',excerpt:'Summary'},draft:{...extra.recovery,token:'server-draft'}}})};
    }
    if (requests.at(-1).get('intent') === 'validate') {
      const candidate = JSON.parse(requests.at(-1).get('document'));
      return { ok: !fail && !validationMessage, json: async () => fail || validationMessage ? { success: false, data: { message: validationMessage || 'Draft conflict: reload required' } } : { success: true, data: { valid: true, document: extra.validateDocument ? extra.validateDocument(candidate) : candidate } } };
    }
    return { ok: !fail, json: async () => fail ? { success: false, data: { message: 'Draft conflict: reload required' } } : { success: true, data: { draftToken: 'draft-' + requests.length, previewUrl: '/?preview=true', validationMessage } } };
  };
  const rich = [];
  const hooks = new Map();
  window.wp = {
    editor: { initialize: (id, options) => rich.push({ id, options }), remove: () => {} },
    hooks: { addAction: (event, _namespace, callback) => hooks.set(event, callback), doAction: (event, data) => hooks.get(event)?.(data) }
  };
  if (extra.routeStations) {
    window.issRelationsRouteStations = {create: () => ({render: target => { target.textContent = 'Native route stations'; }, saveIfDirty: () => Promise.resolve(), getPreviewArgs: () => ({})})};
  }
  window.jQuery = node => ({ hide: () => { node.style.display = 'none'; } });
  window.HTMLElement.prototype.getClientRects = function () { return this.hidden || this.style.display === 'none' ? [] : [1]; };
  window.HTMLElement.prototype.scrollIntoView = function () {};
  window.HTMLElement.prototype.showPopover = function () { this.dataset.popoverOpen = 'true'; };
  window.HTMLElement.prototype.hidePopover = function () { delete this.dataset.popoverOpen; };
  window.ResizeObserver = class { observe() {} disconnect() {} };
  window.eval(fs.readFileSync('plugins/iss-core/assets/admin-request.js', 'utf8'));
  for (const asset of ['ui.js', 'dnd.js', 'rich-text.js', 'live-preview.js', 'help.js', 'workspace.js', 'pickers.js', 'admin.js']) {
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

function controlledClock() {
  let now = 0, id = 0;
  const timers = new Map();
  return {
    setTimeout(fn, delay = 0) { const key = ++id; timers.set(key, { fn, due: now + delay }); return key; },
    clearTimeout(key) { timers.delete(key); },
    async tick(ms) {
      const end = now + ms;
      while (true) {
        await new Promise(setImmediate);
        const next = [...timers].filter(([, timer]) => timer.due <= end).sort((a, b) => a[1].due - b[1].due)[0];
        if (!next) { break; }
        now = next[1].due; timers.delete(next[0]); next[1].fn();
      }
      now = end;
      await new Promise(setImmediate);
    }
  };
}

test('An unresponsive save times out loudly, releases the queue and preserves newer edits', async () => {
  const clock=controlledClock(); const e=await editor('landing',{clock});
  try {
    let calls=0;
    e.window.fetch=()=>{calls++;return new Promise(()=>{});};
    e.input(e.window.document.getElementById('title'),'Pending work'); await clock.tick(1200);
    await clock.tick(15000);
    assert.equal(calls,1);
    assert.equal(e.button('Erneut sichern').hidden,false);
    assert.match(e.container.querySelector('.iss-editorial-failure').textContent,/Speicherstand ist unklar/);
    assert.ok(e.root.querySelector('.iss-editorial-studio > .iss-editorial-failure'),'Alert is inside the expanded workspace');
    assert.notEqual(e.container.querySelector('.iss-editorial-failure').inert,true,'Recovery actions must remain interactive in the expanded workspace');
    e.input(e.window.document.getElementById('title'),'Newer local work');
    const sent=[];
    e.window.fetch=async(_url,request)=>{sent.push(new URLSearchParams(request.body));return {ok:true,json:async()=>({success:true,data:{draftToken:'reconciled',previewUrl:'/?preview=true'}})};};
    e.button('Erneut sichern').click(); await clock.tick(0);
    assert.equal(sent[0].get('title'),'Pending work','Reconcile the exact uncertain request first');
    assert.equal(sent[1].get('title'),'Newer local work');
    assert.equal(sent[1].get('draft_token'),'reconciled');
    assert.equal(e.button('Erneut sichern').hidden,true);
  } finally {e.dom.window.close();}
});

test('Lost response retries reconcile the prior snapshot before saving subsequent changes', async()=>{
  const e=await editor('landing',{draftToken:'old'});
  try {
    const sent=[];
    e.window.fetch=async(_url,request)=>{
      const params=new URLSearchParams(request.body);sent.push(params);
      if(sent.length===1){throw new TypeError('Connection lost after commit');}
      return {ok:true,json:async()=>({success:true,data:{draftToken:'server-'+sent.length,previewUrl:'/?preview=true'}})};
    };
    e.input(e.window.document.getElementById('title'),'Committed but not acknowledged');await e.settle();
    assert.match(e.container.querySelector('.iss-editorial-failure').textContent,/unterbrochen/);
    e.input(e.window.document.getElementById('title'),'Additional local edit');
    e.button('Erneut sichern').click();await e.settle();
    assert.equal(sent[1].get('title'),sent[0].get('title'));
    assert.equal(sent[2].get('title'),'Additional local edit');
    assert.equal(sent[2].get('draft_token'),'server-2');
  } finally {e.dom.window.close();}
});

test('Missing preview finish acknowledgement unlocks the form without replaying publication',async()=>{
  const clock=controlledClock();const e=await editor('landing',{...workspaceOptions(),clock});
  try {
    await clock.tick(0);const frame=readyFrame(e);
    previewMessage(e,frame,{type:'iss-preview-field-start',section:0,field:'title',session:'lost',sequence:0});
    previewMessage(e,frame,{type:'iss-preview-field-change',section:0,field:'title',session:'lost',sequence:1,value:'Acknowledged text'});
    let submitted=false;e.window.document.getElementById('post').requestSubmit=()=>{submitted=true;};
    e.button('Update').click();await clock.tick(5000);
    assert.equal(submitted,false);assert.equal(frame.isConnected,false);
    assert.equal(e.root.querySelector('.iss-editorial-studio__fields').inert,false);
    assert.equal(e.value().sections[0].title,'Acknowledged text');
    assert.match(e.root.querySelector('.iss-editorial-failure').textContent,/antwortet nicht/);
    e.button('Update').click();await clock.tick(0);assert.equal(submitted,true);
  }finally{e.dom.window.close();}
});

test('Invalid canonical content offers validated repair and does not block recovery discard',async()=>{
  const raw=JSON.stringify({schema_version:1,sections:[{type:'retired',unknown:'Keep until explicitly repaired'}]});
  const e=await editor('landing',{validationError:'Unsupported section',rawDocument:raw,recovery:{base:'base',modified:'Today',document:{schema_version:1,sections:[]},title:'Draft',excerpt:''}});
  try{
    e.button('Entwurf verwerfen').click();await e.settle();
    assert.equal(e.requests[0].get('intent'),'discard');
    e.button('Inhalt prüfen').click();
    const repair=e.container.querySelector('.iss-editorial-repair textarea');assert.equal(repair.value,raw);
    repair.value='{broken';e.button('Reparatur prüfen').click();await e.settle();
    assert.match(e.container.querySelector('.iss-editorial-failure').textContent,/kein gültiges JSON/);
    repair.value=JSON.stringify({schema_version:1,sections:[{type:'kapitel',title:'Repaired deliberately'}]});
    e.button('Reparatur prüfen').click();await e.settle();
    assert.equal(e.value().sections[0].title,'Repaired deliberately');
    assert.equal(e.root.inert,false);assert.equal(e.root.hidden,false);
    assert.ok(e.requests.some(p=>p.get('intent')==='validate'));
  }finally{e.dom.window.close();}
});

test('Heartbeat renews custom save credentials and distinguishes expired sessions',async()=>{
  const e=await editor();
  try{
    e.window.wp.hooks.doAction('heartbeat.tick',{'iss-editorial-nonces':{preview:'fresh',save:'fresh-save',rest:'fresh-rest'}});
    e.input(e.window.document.getElementById('title'),'Fresh credentials');await e.settle();
    assert.equal(e.requests.at(-1).get('nonce'),'fresh');
    e.window.wp.hooks.doAction('heartbeat.tick',{'iss-editorial-session-expired':true});
    assert.match(e.container.querySelector('.iss-editorial-failure').textContent,/Sitzung ist abgelaufen/);
    assert.doesNotMatch(e.container.querySelector('.iss-editorial-failure').textContent,/Anmeldung hat sich geändert/);
  }finally{e.dom.window.close();}
});

test('Recovery copies restore only the same page after validation',async()=>{
  const e=await editor();
  try{
    e.button('Sicherung wiederherstellen').click();
    const input=e.container.querySelector('.iss-editorial-repair textarea');
    const copy={postId:100,title:'Recovered identity',excerpt:'Recovered summary',document:{schema_version:1,sections:[{type:'kapitel',title:'Recovered section'}]}};
    input.value=JSON.stringify(copy);e.button('Reparatur prüfen').click();await e.settle();
    assert.equal(e.requests.length,0,'Another page cannot overwrite this page');
    assert.match(e.container.querySelector('[role="alert"]').textContent,/anderen Seite/);
    copy.postId=99;input.value=JSON.stringify(copy);e.button('Reparatur prüfen').click();await e.settle();
    assert.equal(e.requests[0].get('intent'),'validate');
    assert.equal(e.value().sections[0].title,'Recovered section');
    assert.equal(e.window.document.getElementById('title').value,'Recovered identity');
    assert.equal(e.requests.at(-1).get('title'),'Recovered identity');
  }finally{e.dom.window.close();}
});

test('A non-JSON server failure is visible and retains the working document',async()=>{
  const e=await editor();
  try{
    e.window.fetch=async()=>({ok:false,status:502,json:async()=>{throw new SyntaxError('HTML error page');}});
    e.input(e.window.document.getElementById('title'),'Retain this');await e.settle();
    assert.equal(e.window.document.getElementById('title').value,'Retain this');
    assert.equal(e.container.querySelector('.iss-editorial-failure').dataset.code,'invalid_response');
    assert.equal(e.button('Erneut sichern').hidden,false);
  }finally{e.dom.window.close();}
});

test('A stale recovery draft is compared before explicit rebasing or any write',async()=>{
  const e=await editor('landing',{recovery:{base:'older',modified:'Yesterday',title:'Old draft title',document:{schema_version:1,sections:[{type:'kapitel',title:'Old private section'}]}}});
  try{
    e.button('Entwurf weiterbearbeiten').click();await e.settle();
    assert.deepEqual(e.requests.map(p=>p.get('intent')),['inspect'],'Comparison is read-only');
    assert.equal(e.value().sections[0].title,'Old private section');
    assert.ok(e.button('Meine Fassung weiterbearbeiten'));
    e.button('Meine Fassung weiterbearbeiten').click();await e.settle();
    assert.equal(e.requests.at(-1).get('intent'),'autosave');
    assert.equal(e.requests.at(-1).get('draft_token'),'server-draft');
    assert.equal(e.requests.at(-1).get('title'),'Old draft title');
  }finally{e.dom.window.close();}
});

for (const [format, type] of [['place', 'epoche'], ['renamed_history', 'renamed_phase']]) {
  test(`${format}.${type}: vocabulary labels and ordered choices follow the registry without rewriting rejected input`, async () => {
    const e = await editor(format, {
      sections: { [type]: { label: 'Phase', supports: ['era_key', 'function_key'], vocabularies: {
        era_key: { label: 'Custom period', choices: [{value:'ancient',label:'Earlier'}, {value:'recent',label:'Recent'}] },
        function_key: { label: 'Custom use', choices: [{value:'research',label:'Research'}, {value:'workshop',label:'Workshop'}] }
      } } },
      document: { schema_version: 3, sections: [{type, title:'Retained phase', era_key:'retired-value', function_key:'research'}] }
    });
    try {
      const select = label => [...e.root.querySelectorAll('label')].find(node => node.querySelector('span')?.textContent === label)?.querySelector('select');
      assert.deepEqual([...select('Custom period').options].map(o => [o.value,o.textContent]), [['','Bitte wählen'],['ancient','Earlier'],['recent','Recent']]);
      assert.deepEqual([...select('Custom use').options].map(o => [o.value,o.textContent]), [['','Bitte wählen'],['research','Research'],['workshop','Workshop']]);
      assert.equal(select('Custom use').value, 'research');
      assert.equal(e.value().sections[0].era_key, 'retired-value', 'Opening never discards an unavailable saved choice');
      select('Custom period').value = 'recent'; select('Custom period').dispatchEvent(new e.window.Event('change', {bubbles:true}));
      select('Custom use').value = 'workshop'; select('Custom use').dispatchEvent(new e.window.Event('change', {bubbles:true}));
      await e.settle();
      assert.deepEqual(JSON.parse(e.requests.at(-1).get('document')).sections[0], {type, title:'Retained phase', era_key:'recent', function_key:'workshop'});
      select('Custom period').value = ''; select('Custom period').dispatchEvent(new e.window.Event('change', {bubbles:true}));
      assert.equal(e.value().sections[0].era_key, '', 'An explicit blank is retained for owner validation');
      assert.equal(e.value().sections[0].function_key, 'workshop');
    } finally { e.dom.window.close(); }
  });
}

for (const [format, type] of [['projekt', 'renamed_media'], ['landing', 'feature']]) {
  test(format + '.' + type + ': media layout choices, default and visibility follow the registry', async () => {
    const e = await editor(format, {
      sections: { [type]: { label: 'Media', supports: ['treatment', 'media_layout'],
        default_treatment: 'media.split', treatments: [{ slug: 'media.split', label: 'Split' }, { slug: 'media.panel', label: 'Panel' }],
        media_layout: { choices: [{ value: 'compact', label: 'Compact' }, { value: 'wide', label: 'Wide' }], default: 'wide', treatments: ['media.split'] } } },
      document: { schema_version: 3, skin: 'standard', sections: [{ type, title: 'Retained title' }] }
    });
    try {
      const choices = () => [...e.root.querySelectorAll('.iss-editorial-field--media-layout input')];
      assert.deepEqual(choices().map(node => [node.value, node.checked]), [['compact', false], ['wide', true]], 'Declared default may be the second choice');
      assert.equal(e.value().sections[0].media_layout, undefined, 'Rendering a default does not author a value');
      e.button('Darstellung').click();
      choices()[0].click();
      assert.equal(e.value().sections[0].media_layout, 'compact');
      e.root.querySelector('.iss-editorial-field--treatment input[value="media.panel"]').click();
      // Existing controls are rebuilt when the section is selected again.
      e.root.querySelector('.iss-editorial-outline__select').click();
      assert.equal(choices().length, 0, 'Hidden for the other treatment');
      assert.equal(e.value().sections[0].media_layout, 'compact', 'Hiding retains the saved choice');
      await e.settle();
      assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections[0].media_layout, 'compact', 'Private autosave retains hidden layout');
      e.button('Darstellung').click();
      e.root.querySelector('.iss-editorial-field--treatment input[value="media.split"]').click();
      e.root.querySelector('.iss-editorial-outline__select').click();
      assert.deepEqual(choices().map(node => [node.value, node.checked]), [['compact', true], ['wide', false]], 'Showing restores the selected choice');
      assert.equal(e.value().sections[0].title, 'Retained title');
    } finally { e.dom.window.close(); }
  });
}

test('Unrestricted media layouts stay visible and preserve rejected values for correction', async () => {
  const e = await editor('publication', {
    sections: { longread_chapter: { label: 'Chapter', supports: ['media_layout'], media_layout: {
      choices: [{ value: 'inline', label: 'Im Text' }, { value: 'aside-right', label: 'Rechts daneben' }], default: 'inline', treatments: []
    } } },
    document: { schema_version: 3, sections: [{ type: 'longread_chapter', media_layout: 'unregistered' }] }
  });
  try {
    assert.equal(e.root.querySelector('.iss-editorial-field--media-layout input:checked').value, 'inline');
    assert.equal(e.value().sections[0].media_layout, 'unregistered', 'Opening does not silently rewrite rejected input');
    e.button('Darstellung').click();
    e.root.querySelector('.iss-editorial-field--media-layout input[value="aside-right"]').click();
    assert.equal(e.value().sections[0].media_layout, 'aside-right');
  } finally { e.dom.window.close(); }
});

for (const type of ['renamed_row', 'gateway']) {
  test(type + ': declared text-image items add, edit and remove without link fields', async () => {
    const e = await editor('projekt', {
      sections: { [type]: { label: 'Rows', supports: ['items'], items_kind: 'text_image', items_label: 'Custom pairs' } },
      document: { schema_version: 1, skin: 'standard', sections: [{ type, items: [] }] }, pageChoices: []
    });
    try {
      assert.equal(e.root.querySelector('.iss-editorial-panel--items .iss-editorial-panel__label').textContent, 'Custom pairs');
      assert.match(e.root.textContent, /Noch keine Bild-Text-Paare/);
      e.button('Bild-Text-Paar hinzufügen').click();
      const fields = e.root.querySelector('.iss-editorial-gateway-item-row__fields');
      assert.equal(fields.querySelectorAll('input').length, 1, 'Only the label; no URL or page selector');
      assert.equal(fields.querySelector('select'), null);
      e.input(fields.querySelector('input'), 'New pair');
      e.input(fields.querySelector('textarea'), 'Pair text');
      assert.deepEqual(e.value().sections[0].items, [{ label: 'New pair', text: 'Pair text', media_refs: [] }]);
      await e.settle();
      assert.deepEqual(JSON.parse(e.requests.at(-1).get('document')).sections[0].items, e.value().sections[0].items);
      e.button('Entfernen', e.root.querySelector('.iss-editorial-gateway-item-row')).click();
      assert.deepEqual(e.value().sections[0].items, []);
    } finally { e.dom.window.close(); }
  });
}

for (const type of ['text_bild_reihe', 'map_img']) {
  test(type + ': declared cards override the historical row name', async () => {
    const e = await editor('projekt', {
      sections: { [type]: { label: 'Cards', supports: ['items'], items_kind: 'cards' } },
      document: { schema_version: 1, skin: 'standard', sections: [{ type, items: [] }] }, pageChoices: []
    });
    try {
      assert.equal(e.root.querySelector('.iss-editorial-panel--items .iss-editorial-panel__label').textContent, 'Ziele');
      e.button('Ziel hinzufügen').click();
      const inputs = e.root.querySelectorAll('.iss-editorial-gateway-item-row__fields input');
      assert.equal(inputs.length, 2, 'Card label and destination');
      e.input(inputs[0], 'Card'); e.input(inputs[1], 'https://example.invalid/destination/');
      assert.deepEqual(e.value().sections[0].items, [{ label: 'Card', text: '', url: 'https://example.invalid/destination/', page_id: '', media_refs: [] }]);
    } finally { e.dom.window.close(); }
  });
}

test('Renamed text items retain the line-based notes control', async () => {
  const e = await editor('projekt', {
    sections: { renamed_notes: { label: 'Notes', supports: ['items'], items_kind: 'text' } },
    document: { schema_version: 1, skin: 'standard', sections: [{ type: 'renamed_notes', items: ['First note'] }] }
  });
  try {
    const panel = e.root.querySelector('.iss-editorial-panel--items');
    assert.equal(panel.querySelector('.iss-editorial-gateway-item-row'), null);
    e.input(panel.querySelector('textarea'), 'First note\n\nSecond note');
    assert.deepEqual(e.value().sections[0].items, ['First note', 'Second note']);
  } finally { e.dom.window.close(); }
});

for (const format of ['landing', 'article', 'projekt', 'veranstaltung', 'place', 'fuehrung', 'publication', 'ausstellung', 'rueckblick']) {
  test(format + ': one workspace retains hidden sections, edits, reorder, trash and recovery storage', async () => {
    const e = await editor(format);
    try {
      assert.equal(e.root.querySelectorAll('.iss-editorial-outline__select').length, 2);
      assert.equal(e.window.document.querySelector('.iss-editorial-modal'), null);
      await e.settle(); assert.ok(e.rich.length, 'Shared native rich-text control');
      assert.equal(e.value().sections[0].links[0].url, 'https://elsewhere.example/about/');
      const title = e.root.querySelector('.iss-editorial-field--title textarea');
      e.input(title, 'Edited title');
      const handle = e.root.querySelector('[data-section-index="0"] .iss-editorial-card__drag-handle');
      handle.dispatchEvent(new e.window.KeyboardEvent('keydown', {key:'ArrowDown', bubbles:true}));
      assert.equal(e.value().sections[1].title, 'Edited title');
      e.button('In Papierkorb', e.root.querySelector('.iss-editorial-studio__inspector')).click();
      assert.equal(e.value().deleted_sections[0].title, 'Edited title');
      e.button('Wiederherstellen').click();
      await e.settle();
      assert.equal(e.value().sections[1].title, 'Edited title');
      assert.equal(e.value().deleted_sections.length, 0);
      e.input(e.window.document.getElementById('title'), 'Changed page title');
      e.input(e.window.document.getElementById('excerpt'), 'Changed summary');
      await e.settle();
      const request = e.requests.at(-1);
      assert.equal(request.get('title'), 'Changed page title');
      assert.equal(request.get('excerpt'), 'Changed summary');
      assert.equal(request.get('base'), 'base');
      assert.equal(e.requests.find(item => item.get('intent') === 'autosave').get('draft_token'), '', 'The first edit starts without an opening autosave');
      assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Entwurf gesichert/);
    } finally { e.dom.window.close(); }
  });
}

test('Native heartbeat releases stale dialogs and still displays a later takeover', async () => {
  const e = await editor('projekt', { userId: 17 });
  try {
    const dialog = e.window.document.createElement('div');
    dialog.id = 'post-lock-dialog';
    e.window.document.body.appendChild(dialog);
    const data = {};
    e.window.wp.hooks.doAction('heartbeat.send', data);
    assert.equal(data['iss-editorial-user'], 17);
    e.setFailure(true);
    e.window.wp.hooks.doAction('heartbeat.tick', { 'wp-refresh-post-lock': { new_lock: '123:17' } });
    await e.settle();
    assert.equal(dialog.getClientRects().length, 0, 'A confirmed native lock removes the old modal');
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Draft conflict/, 'Reacquiring a lock still checks the document version');
    const count = e.requests.length;
    dialog.style.display = 'block'; // Core shows a new takeover before invoking heartbeat.tick.
    e.window.wp.hooks.doAction('heartbeat.tick', { 'wp-refresh-post-lock': { lock_error: { text: 'Another editor has taken over' } } });
    assert.equal(dialog.getClientRects().length, 1, 'Real subsequent concurrent editing remains blocked');
    await e.settle();
    assert.equal(e.requests.length, count, 'A real lock does not trigger a save');
  } finally { e.dom.window.close(); }
});

test('Account changes retain unsaved fields and stop old-tab autosaves and publication', async () => {
  const e = await editor('projekt');
  try {
    const title = e.window.document.getElementById('title');
    e.input(title, 'Unsaved private title');
    const count = e.requests.length;
    e.window.wp.hooks.doAction('heartbeat.tick', { 'iss-editorial-session-changed': true });
    await e.settle();
    assert.equal(title.value, 'Unsaved private title');
    assert.match(e.container.querySelector('[role="alert"]').textContent, /Anmeldung hat sich geändert/);
    assert.equal(e.container.querySelector('[role="alert"] a').href, e.window.location.href);
    e.input(title, 'Still available for copying');
    let submitted = false;
    e.window.document.getElementById('post').requestSubmit = () => { submitted = true; };
    e.button('Update').click();
    e.window.wp.hooks.doAction('heartbeat.tick', { 'iss-editorial-session-changed': true });
    await e.settle();
    assert.equal(submitted, false);
    assert.equal(e.requests.length, count, 'No writes under the new account');
    assert.equal(e.container.querySelectorAll('[role="alert"]').length, 1);
    const leaving = new e.window.Event('beforeunload', { cancelable: true });
    e.window.dispatchEvent(leaving);
    assert.equal(leaving.defaultPrevented, true, 'Leaving still warns about unsaved work');
  } finally { e.dom.window.close(); }
});

test('Recovery is explicit and restores title, summary and event structure with the document', async () => {
  const e = await editor('veranstaltung', { recovery: { base: 'base', modified: 'Today', title: 'Recovered title', excerpt: 'Recovered summary', document: { schema_version: 1, skin: 'standard', entity_key: 'event.festival', sections: [{ type: 'kapitel', title: 'Recovered section' }] } }, documentBindings: { entity_key: '#entity' } });
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
    assert.equal(e.window.document.activeElement, e.root.querySelector('.iss-editorial-outline__select'), 'Continue directly with the recovered sections');
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

test('Publish exposes unresolved recovery without saving either version, then resumes after the choice', async () => {
  const e = await editor('projekt', { recovery: { base: 'base', modified: 'Earlier today', document: { schema_version: 1, sections: [{ type: 'kapitel', title: 'Older private draft' }] } } });
  try {
    const form = e.window.document.getElementById('post');
    const recovery = e.container.querySelector('.iss-editorial-recovery');
    const saved = e.value();
    const submissions = [];
    let revealed = false;
    recovery.scrollIntoView = () => { revealed = true; };
    form.requestSubmit = submitter => { submissions.push(submitter); };
    e.button('Update').click();
    await e.settle();
    assert.equal(e.window.document.activeElement, recovery);
    assert.equal(revealed, true);
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /zuerst.*Entwurf/);
    assert.equal(e.requests.length, 0, 'No autosave or discard before the recovery choice');
    assert.equal(submissions.length, 0);
    assert.deepEqual(e.value(), saved);
    e.button('Entwurf weiterbearbeiten').click();
    await e.settle();
    e.button('Update').click();
    await e.settle();
    assert.deepEqual(submissions, [e.button('Update')]);
    assert.equal(e.requests.at(-1).get('intent'), 'submit');
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
    assert.equal(e.window.document.activeElement, e.container.querySelector('.iss-editorial-autosave-status'));
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
    assert.equal(e.window.document.activeElement, e.container.querySelector('.iss-editorial-autosave-status'));
  } finally { e.dom.window.close(); }
});

test('Recovering an unfinished draft shows the saved page without editing the draft through that snapshot', async () => {
  const recovered = {schema_version:1,skin:'standard',sections:[{type:'kapitel',title:'Recovered text',links:[{label:'',url:'',page_id:''}]}]};
  const e = await editor('landing', {recovery:{base:'base',modified:'Today',title:'Draft title',excerpt:'',document:recovered}});
  try {
    e.setValidation('Abschnitt 1: Link-Adresse fehlt');
    e.button('Entwurf weiterbearbeiten').click();
    await e.settle();
    const frame = readyFrame(e);
    assert.match(frame.src, /iss_editorial_snapshot=saved%3Abase/);
    assert.match(frame.src, /iss_editorial_canvas=0/);
    assert.equal(frame.hidden, false);
    assert.match(e.root.querySelector('.iss-editorial-live-preview__status').textContent, /Link-Adresse fehlt.*gespeicherte Fassung.*Formular/);
    const document = JSON.stringify(e.value());
    const count = e.requests.length;
    for (const type of ['iss-preview-select','iss-preview-insert','iss-preview-field-start','iss-preview-field-change','iss-preview-form']) {
      previewMessage(e, frame, {type,section:0,field:'title',session:'fallback',sequence:1,value:'Wrong snapshot'});
    }
    await e.settle();
    assert.equal(JSON.stringify(e.value()), document, 'Saved preview cannot select, insert or edit recovered sections');
    assert.equal(e.requests.length, count);
    assert.deepEqual(e.value().sections[0].links, recovered.sections[0].links, 'Unfinished entries remain recoverable');
    let submitted = false;
    e.window.document.getElementById('post').requestSubmit = () => { submitted = true; };
    e.button('Update').click();
    await e.settle();
    assert.equal(submitted, false);
    assert.equal(frame.isConnected, true, 'Publication validation must not clear the fallback');
    assert.equal(e.button('Erneut sichern').hidden, true, 'Validation is not a failed autosave');
    e.setValidation('');
    e.button('Links').click();
    e.button('Entfernen', e.root.querySelector('.iss-editorial-field--links')).click();
    await e.settle();
    const updated = readyFrame(e);
    assert.notEqual(updated, frame);
    assert.match(updated.src, /iss_editorial_canvas=1/);
    assert.match(updated.src, /iss_editorial_snapshot=draft-/);
    assert.equal(e.root.querySelector('.iss-editorial-live-preview__status').textContent, 'Vorschau aktuell');
  } finally { e.dom.window.close(); }
});

test('Explicit refresh of an unfinished recovered draft keeps the pending saved preview alive', async () => {
  const e = await editor('landing', {recovery:{base:'base',modified:'Today',title:'Draft',excerpt:'',document:{schema_version:1,skin:'standard',sections:[{type:'kapitel',links:[{label:'Only a label'}]}]}}});
  try {
    e.setValidation('Link-Adresse fehlt');
    e.button('Entwurf weiterbearbeiten').click();
    await e.settle();
    const pending = e.root.querySelector('iframe[hidden]');
    e.root.querySelector('[aria-label="Vorschau aktualisieren"]').click();
    await e.settle();
    assert.equal(pending.isConnected, true, 'Validation feedback must not cancel the fallback before its ready message');
    assert.equal(readyFrame(e), pending);
    assert.match(e.root.querySelector('.iss-editorial-live-preview__status').textContent, /gespeicherte Fassung/);
    assert.equal(e.button('Erneut sichern').hidden, true);
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
    assert.ok(e.requests.every(request => JSON.parse(request.get('document')).schema_version === 1));
    e.button('Textfarben aktivieren').click();
    assert.equal(e.value().schema_version, 2);
    assert.equal(e.value().sections[0].items[0].text, 'Literal &lt;b&gt;word&lt;/b&gt; &amp; label');
    assert.equal(e.value().deleted_sections[0].items[0].text, 'Keep &lt;i&gt;literal&lt;/i&gt;');
    await e.settle();
    const card = e.rich.find(item => item.options.tinymce.forced_root_block === false);
    assert.ok(card);
    assert.ok(!card.options.tinymce.toolbar1.includes('isslink'));
    assert.ok(!card.options.tinymce.valid_elements.includes('a['));
  } finally { e.dom.window.close(); }
});

test('Unsupported imported markup remains intact until the author explicitly simplifies it', async () => {
  const e = await editor('landing', { sections: {kapitel:{label:'Text',supports:[],rich_text:{body:'block'}}}, document: {schema_version:2,skin:'standard',sections:[{type:'kapitel',body:'<p style="color:red">Old <strong>text</strong></p>'}]} });
  try {
    await e.settle();
    assert.equal(e.rich.length, 0);
    assert.equal(e.value().sections[0].body, '<p style="color:red">Old <strong>text</strong></p>');
    e.button('Formatierung vereinfachen').click();
    assert.equal(e.rich.length, 1);
    assert.equal(e.value().sections[0].body, '<p>Old <strong>text</strong></p>');
  } finally { e.dom.window.close(); }
});

for (const format of ['fuehrung', 'renamed_legacy_owner']) {
  test(format + ': v1 basic bodies expose explicit repair and constrain new pasted markup', async () => {
    const original = '<div id="old-column" class="col"><div class="col-inner"><p>Tour <strong>history</strong>.</p><p><a href="/group/">Group tour</a></p></div></div>';
    const trash = [{type:'retired',body:'<div>Keep recovery exactly</div>'}];
    const e = await editor(format, {sections:{kapitel:{label:'Text',supports:[],legacy_body_policy:'basic',rich_text:{body:'block'}}},document:{schema_version:1,sections:[{type:'kapitel',body:original}],deleted_sections:trash}});
    try {
      await e.settle();
      assert.equal(e.rich.length, 0, 'Do not silently rewrite existing legacy markup');
      assert.equal(e.value().sections[0].body, original);
      assert.ok(e.root.querySelector('textarea[readonly]'));
      e.button('Formatierung vereinfachen').click();
      const clean = '<p>Tour <strong>history</strong>.</p><p><a href="/group/">Group tour</a></p>';
      assert.equal(e.value().sections[0].body, clean, 'Drop obsolete layout wrappers without nested paragraphs or lost prose/links');
      assert.equal(e.value().schema_version, 1);
      assert.deepEqual(e.value().deleted_sections, trash);
      const settings = e.rich[0].options.tinymce;
      assert.equal(settings.valid_elements, 'p,br,strong/b,em/i,ul,ol,li,a[href]');
      const pasted = {content:original}; settings.paste_preprocess(null,pasted);
      assert.equal(pasted.content, clean);
      const rich = e.window.issEditorialRichText;
      assert.equal(rich.hasUnsupportedMarkup(clean,'basic'),false);
      for (const bad of ['<div>Layout</div>','<b>Bold alias</b>','<span class="iss-ink-123abc">Colour</span>','<a href="/" target="_blank">Link</a>']) {
        assert.equal(rich.hasUnsupportedMarkup(bad,'basic'),true);
      }
      await e.settle();
      assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections[0].body,clean);
      e.button('Hinzufügen').click();
      e.root.querySelector('.iss-editorial-gesture[data-section-type="kapitel"]').click();
      assert.equal(e.value().sections.length,2,'Add a section after repairing the legacy body');
      e.button('In Papierkorb',e.root.querySelector('.iss-editorial-studio__inspector')).click();
      assert.equal(e.value().sections.length,1,'Delete the added section');
      assert.equal(e.value().sections[0].body,clean);
      e.button('Rückgängig').click();
      await e.settle();
      assert.equal(e.value().sections.length,2,'Undo remains available');
    } finally { e.dom.window.close(); }
  });
}

test('Live preview accepts only its current frame and token and retains valid output on failed refresh', async () => {
  const e = await editor();
  try {
    const shell = { root:e.window.document.createElement('div'), help: { hint: () => {} } };
    for (const key of ['previewMount','previewTools','previewStatus']) { shell[key]=e.window.document.createElement('div'); shell.root.appendChild(shell[key]); }
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
    preview.fallback('/?preview=true', 'saved:base', 'Incomplete link');
    assert.equal(first.isConnected, true);
    assert.equal(second.isConnected, false);
    assert.match(shell.root.querySelector('[role=status]').textContent, /vorige Fassung/);
    preview.update('/?preview=true', 'three');
    const third = shell.root.querySelector('iframe[hidden]');
    send(first, 'one'); assert.equal(third.hidden, true);
    send(third, 'three'); assert.equal(first.isConnected, false); assert.equal(third.hidden, false);
    select(first, 'one', 2); assert.deepEqual(selected, [1], 'Replaced frames cannot change the selected section');
    preview.update('/?preview=true', 'four');
    const fourth = shell.root.querySelector('iframe[hidden]');
    const stale = (source, token, origin='http://localhost:8082') => e.window.dispatchEvent(new e.window.MessageEvent('message', {source:source.contentWindow, origin, data:{type:'iss-preview-stale', token}}));
    stale(third, 'four'); stale(fourth, 'wrong'); stale(fourth, 'four', 'https://elsewhere.example');
    assert.equal(fourth.isConnected, true, 'Untrusted stale signals cannot cancel a pending preview');
    stale(fourth, 'four');
    assert.equal(fourth.isConnected, false, 'A stale response reports immediately without waiting for the timeout');
    assert.equal(third.isConnected, true, 'Previous valid preview survives a stale response');
    assert.match(shell.root.querySelector('[role=status]').textContent, /nicht mehr aktuell/);
    preview.destroy();
  } finally { e.dom.window.close(); }
});

test('Section navigation keeps pending edits and the same preview frame', async () => {
  const e = await editor();
  try {
    await e.settle(); const frame = readyFrame(e);
    const field = () => e.root.querySelector('.iss-editorial-field--title textarea');
    e.input(field(), 'First edited');
    const selector = e.root.querySelector('select[aria-label="Abschnitt auswählen"]');
    selector.value = '1'; selector.dispatchEvent(new e.window.Event('change', {bubbles:true}));
    assert.equal(field().value, 'Legacy intro');
    assert.equal(e.value().sections[0].title, 'First edited');
    assert.equal(e.root.querySelector('iframe'), frame);
    e.input(field(), 'Second edited');
    e.root.querySelectorAll('.iss-editorial-outline__select')[0].click();
    assert.equal(field().value, 'First edited');
    await e.settle();
    const saved = JSON.parse(e.requests.at(-1).get('document'));
    assert.equal(saved.sections[0].title, 'First edited');
    assert.equal(saved.sections[1].title, 'Second edited');
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


test('Named palette colours preserve identity while custom hex colours remain exact', async () => {
  const e = await editor('landing', { textPalette: [{slug:'scarlet-red', name:'Rot', color:'#e81d25'}] });
  try {
    const rich = e.window.issEditorialRichText;
    const prose = '<p><span class="iss-ink-preset-scarlet-red iss-mark-ffeeaa">Text</span></p>';
    assert.equal(rich.sanitize(prose, 'block'), prose);
    assert.equal(rich.hasUnsupportedMarkup(prose, 'block'), false);
    assert.equal(rich.hasUnsupportedMarkup('<span class="iss-ink-preset-invented">X</span>', 'inline'), true);
    assert.equal(rich.sanitize('<span class="iss-ink-preset-invented">X</span>', 'inline'), '<span>X</span>');
    assert.equal(rich.sanitize('<a href="/story/"><span class="iss-ink-123abc">Linked</span></a>', 'inline'), '<a href="/story/"><span class="iss-ink-123abc">Linked</span></a>');
  } finally { e.dom.window.close(); }
});

test('Visual treatment choices preserve text and focus', async () => {
  const options = workspaceOptions();
  options.sections.feature.treatments.push({slug:'feature.media-panel',label:'Bild und Texttafel',schematic:'panel'});
  const e = await editor('landing', options);
  try {
    const inspector = e.root.querySelector('.iss-editorial-studio__inspector');
    const choice = inspector.querySelector('input[value="feature.media-panel"]');
    const before = e.value().sections[0].body;
    choice.focus(); choice.click();
    assert.equal(e.window.document.activeElement, inspector.querySelector('input[value="feature.media-panel"]'));
    assert.equal(e.value().sections[0].body, before);
    assert.equal(e.value().sections[0].treatment, 'feature.media-panel');
  } finally { e.dom.window.close(); }
});

function workspaceOptions() {
  return {workspace:true,livePreview:true,sections:{feature:{label:'Feature',description:'Bild und Text',group:'Bild',supports:['lead','links','treatment'],rich_text:{body:'block',lead:'block'},treatments:[{slug:'feature.media-text',label:'Bild neben Text',schematic:'split'}]},statement:{label:'Text',description:'Ein Text',group:'Text',supports:['links'],rich_text:{body:'block'}}},document:{schema_version:3,skin:'standard',sections:[{type:'feature',title:'First',body:'<p>Original</p>',treatment:'feature.media-text'},{type:'statement',title:'Second',body:'<p>Other</p>'}]}};
}
function previewMessage(e, frame, data, origin=e.window.location.origin, source=frame.contentWindow) {
  e.window.dispatchEvent(new e.window.MessageEvent('message',{origin,source,data:{token:frame.dataset.token,...data}}));
}
function readyFrame(e) {
  const frame=e.root.querySelector('iframe[hidden]') || e.root.querySelector('.iss-editorial-live-preview__frame');
  assert.ok(frame); previewMessage(e,frame,{type:'iss-preview-ready'}); return frame;
}

test('Opaque trash survives editor rendering and rejected restoration without changing draft tokens', async () => {
  const trash = { type: 'retired_kind', title: '<img src=x onerror=alert(1)>', body: '<legacy>Keep me</legacy>', media_refs: { old: ['shape'] }, extension: { unknown: [null, false, 4] }, deleted_at: 'Earlier', original_index: 0 };
  const e = await editor('projekt', { document: {schema_version:3,skin:'standard',sections:[{type:'kapitel',title:'Active'}],deleted_sections:[trash]} });
  try {
    const before = e.value();
    assert.equal(e.root.querySelector('.iss-editorial-card--deleted img'), null, 'Opaque media is not interpreted or loaded');
    assert.equal(e.root.querySelector('.iss-editorial-card--deleted h3').textContent, trash.title);
    e.setValidation('Unknown section type');
    e.button('Wiederherstellen').click(); await e.settle();
    assert.deepEqual(e.value(), before);
    assert.equal(e.requests.length, 1);
    assert.equal(e.requests[0].get('intent'), 'validate');
    assert.equal(JSON.parse(e.requests[0].get('document')).sections[0].type, 'retired_kind');
    assert.equal(e.container.querySelector('.iss-editorial-draft-token-field').value, '');
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Abschnitt bleibt im Papierkorb/, 'Restore errors stay visible outside the hidden inspector');
    e.setValidation('');
    e.input(e.window.document.getElementById('title'), 'Unrelated edit'); await e.settle();
    assert.deepEqual(JSON.parse(e.requests.at(-1).get('document')).deleted_sections, [trash]);
  } finally { e.dom.window.close(); }
});

test('Restore waits for validation and does not overwrite edits made during the request', async () => {
  let release;
  const clock = controlledClock();
  const e = await editor('projekt', { clock, beforeResponse: params => params.get('intent') === 'validate' ? new Promise(resolve => { release = resolve; }) : undefined });
  try {
    await clock.tick(0); readyFrame(e);
    e.button('In Papierkorb', e.root.querySelector('.iss-editorial-studio__inspector')).click();
    e.button('Wiederherstellen').click(); await clock.tick(0);
    const trash = e.value().deleted_sections;
    assert.equal(e.value().sections.length, 1);
    e.input(e.window.document.getElementById('title'), 'Concurrent local edit');
    release(); await clock.tick(0);
    assert.deepEqual(e.value().deleted_sections, trash);
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /inzwischen geändert/);
    await clock.tick(2000);
    assert.equal(e.requests.at(-1).get('title'), 'Concurrent local edit');
  } finally { e.dom.window.close(); }
});

test('Restore uses the server-normalized section for registered legacy aliases', async () => {
  const e = await editor('projekt', {
    document: {schema_version:3,skin:'standard',sections:[],deleted_sections:[{type:'old_chapter',title:'Legacy title',body:'<p>Retained text</p>'}]},
    validateDocument: candidate => { candidate.sections[0].type = 'kapitel'; return candidate; }
  });
  try {
    e.button('Wiederherstellen').click(); await e.settle();
    assert.equal(e.value().sections[0].type, 'kapitel');
    assert.equal(e.value().sections[0].body, '<p>Retained text</p>');
    assert.equal(e.value().deleted_sections.length, 0);
    e.button('Abschnitte').click(); e.root.querySelector('.iss-editorial-outline__select').click();
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea').value, 'Legacy title', 'The restored section has current editing controls');
  } finally { e.dom.window.close(); }
});

test('Native save queued during restoration includes the validated restored section', async () => {
  let release;
  const clock = controlledClock();
  const e = await editor('projekt', { clock, beforeResponse: params => params.get('intent') === 'validate' ? new Promise(resolve => { release = resolve; }) : undefined });
  try {
    await clock.tick(0); readyFrame(e);
    e.button('In Papierkorb', e.root.querySelector('.iss-editorial-studio__inspector')).click();
    e.button('Wiederherstellen').click(); await clock.tick(0);
    let submitted = false;
    e.window.document.getElementById('post').requestSubmit = () => { submitted = true; };
    e.button('Update').click();
    release(); await clock.tick(0);
    assert.equal(submitted, true);
    const last = e.requests.at(-1);
    assert.equal(last.get('intent'), 'submit');
    assert.equal(JSON.parse(last.get('document')).sections[0].title, 'First section');
    assert.equal(last.get('draft_token'), '', 'Validation does not advance the private draft version');
  } finally { e.dom.window.close(); }
});

test('Untouched opening uses a saved snapshot without autosave or a leaving warning', async () => {
  const clock = controlledClock(), e = await editor('projekt', { clock });
  try {
    await clock.tick(0);
    const frame = readyFrame(e);
    assert.equal(frame.dataset.token, 'saved:base');
    await clock.tick(20000);
    assert.equal(e.requests.length, 0);
    assert.equal(e.root.querySelector('iframe'), frame);
    const leaving = new e.window.Event('beforeunload', { cancelable: true });
    e.window.dispatchEvent(leaving);
    assert.equal(leaving.defaultPrevented, false);
  } finally { e.dom.window.close(); }
});

for (const interval of [600, 1500]) {
  test('Typing at ' + interval + ' ms secures changes but waits for preview idle', async () => {
    const clock = controlledClock(), e = await editor('projekt', { clock });
    try {
      await clock.tick(0);
      const frame = readyFrame(e), title = e.window.document.getElementById('title');
      title.focus();
      for (let i = 0; i < 10; i++) {
        e.input(title, 'Typing ' + i);
        await clock.tick(interval);
        assert.equal(e.root.querySelector('iframe'), frame);
        assert.equal(e.root.querySelector('iframe[hidden]'), null, 'No render while typing');
        assert.equal(e.window.document.activeElement, title);
      }
      await clock.tick(2000);
      assert.equal(e.requests.length, interval === 600 ? 1 : 10);
      assert.equal(e.requests.at(-1).get('title'), 'Typing 9');
      const latest = readyFrame(e);
      assert.notEqual(latest, frame);
      assert.equal(latest.dataset.token, e.container.querySelector('.iss-editorial-draft-token-field').value);
      await clock.tick(10000);
      assert.equal(e.root.querySelector('iframe'), latest, 'No repeated idle reload');
    } finally { e.dom.window.close(); }
  });
}

test('Blur flushes immediately, deduplicates queued saves and preserves validation feedback', async () => {
  const clock = controlledClock(), e = await editor('projekt', { clock });
  try {
    await clock.tick(0); readyFrame(e);
    const title = e.root.querySelector('.iss-editorial-field--title textarea');
    title.focus(); e.input(title, 'Blurred section'); title.blur();
    await clock.tick(0);
    assert.equal(e.requests.length, 1);
    assert.equal(JSON.parse(e.requests[0].get('document')).sections[0].title, 'Blurred section');
    const frame = readyFrame(e);
    title.focus(); title.blur();
    await clock.tick(3000);
    assert.equal(e.requests.length, 1);
    assert.equal(e.root.querySelector('iframe'), frame);
    e.setValidation('Link-Adresse fehlt');
    e.input(title, 'Unfinished section'); await clock.tick(2000);
    title.focus(); title.blur(); await clock.tick(0);
    assert.equal(e.requests.length, 2);
    assert.match(e.container.querySelector('.iss-editorial-autosave-status').textContent, /Link-Adresse fehlt/);
  } finally { e.dom.window.close(); }
});

test('Undo before autosave restores the saved status without a write', async () => {
  const clock = controlledClock(), e = await editor('projekt', { clock });
  try {
    await clock.tick(0); readyFrame(e);
    const title = e.window.document.getElementById('title');
    e.input(title, 'Temporary edit'); await clock.tick(600);
    e.input(title, 'Page title'); await clock.tick(2000);
    assert.equal(e.requests.length, 0);
    assert.equal(e.container.querySelector('.iss-editorial-autosave-status').textContent, 'Gespeicherte Fassung geladen.');
  } finally { e.dom.window.close(); }
});

test('Slow responses serialize latest changes and never render an obsolete snapshot', async () => {
  const clock = controlledClock(), responses = [];
  const e = await editor('projekt', { clock, beforeResponse: () => new Promise(resolve => responses.push(resolve)) });
  try {
    await clock.tick(0); const frame = readyFrame(e);
    const title = e.window.document.getElementById('title');
    e.input(title, 'First edit'); await clock.tick(1200);
    e.input(title, 'Latest edit'); await clock.tick(2000);
    title.focus(); title.blur(); await clock.tick(0);
    assert.equal(e.requests.length, 1, 'Only one request is in flight');
    responses.shift()(); await clock.tick(0);
    assert.equal(e.requests.length, 2);
    assert.equal(e.requests[1].get('draft_token'), 'draft-1');
    assert.equal(e.requests[1].get('title'), 'Latest edit');
    assert.equal(e.root.querySelector('iframe'), frame);
    assert.equal(e.root.querySelector('iframe[hidden]'), null);
    responses.shift()(); await clock.tick(0);
    assert.equal(readyFrame(e).dataset.token, 'draft-2', 'A response after idle refreshes the latest snapshot');
    await clock.tick(3000);
    assert.equal(e.requests.length, 2, 'Duplicate work queued by blur is skipped after acknowledgement');
  } finally { e.dom.window.close(); }
});

test('Explicit preview and native save flush before debounce and still check unchanged versions', async () => {
  const clock = controlledClock(), e = await editor('projekt', { clock });
  try {
    e.window.open = () => ({ location: {}, close() {} });
    await clock.tick(0); readyFrame(e);
    e.input(e.window.document.getElementById('title'), 'Immediate preview');
    e.button('Preview').click(); await clock.tick(0);
    assert.equal(e.requests.length, 1);
    assert.equal(e.requests[0].get('intent'), 'preview');
    let submitted = false;
    e.window.document.getElementById('post').requestSubmit = () => { submitted = true; };
    e.setFailure(true);
    e.button('Update').click(); await clock.tick(0);
    assert.equal(e.requests.length, 2);
    assert.equal(e.requests[1].get('intent'), 'submit');
    assert.equal(submitted, false, 'An unchanged document still detects a concurrent server change');
    e.setFailure(false);
    e.input(e.window.document.getElementById('title'), 'Immediate save');
    e.button('Update').click(); await clock.tick(0);
    assert.equal(e.requests.at(-1).get('title'), 'Immediate save');
    assert.equal(submitted, true);
    await clock.tick(3000);
    assert.equal(e.requests.length, 3);
  } finally { e.dom.window.close(); }
});

test('Canvas autosave waits for field completion before replacing the iframe', async () => {
  const clock = controlledClock(), e = await editor('landing', { ...workspaceOptions(), clock });
  try {
    await clock.tick(0); const frame = readyFrame(e);
    const field = { section: 0, field: 'title', session: 'cadence', sequence: 0 };
    previewMessage(e, frame, { ...field, type: 'iss-preview-field-start' });
    previewMessage(e, frame, { ...field, type: 'iss-preview-field-change', sequence: 1, value: 'Canvas edit' });
    await clock.tick(2500);
    assert.equal(e.requests.length, 1);
    assert.equal(e.root.querySelector('iframe'), frame);
    assert.equal(e.root.querySelector('iframe[hidden]'), null);
    previewMessage(e, frame, { ...field, type: 'iss-preview-field-end', sequence: 2 });
    await clock.tick(0);
    assert.equal(e.requests.length, 1, 'Ending an acknowledged edit needs no duplicate write');
    assert.notEqual(readyFrame(e), frame);
  } finally { e.dom.window.close(); }
});

test('Landing workspace keeps field focus and preview mounted, selects and reorders by object identity, inserts at a gap',async()=>{
  const e=await editor('landing',workspaceOptions());
  try {
    await e.settle(); const frame=readyFrame(e);
    const outgoing = []; frame.contentWindow.postMessage = data => outgoing.push(data);
    assert.equal(e.window.document.querySelector('[role=dialog]'),null);
    const title=e.root.querySelector('.iss-editorial-field--title textarea'); title.focus(); e.input(title,'Edited first');
    assert.equal(e.window.document.activeElement,title);
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea'),title);
    previewMessage(e,frame,{type:'iss-preview-select',section:1});
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea').value,'Second');
    const handle=e.root.querySelector('[data-section-index="1"] .iss-editorial-card__drag-handle');
    handle.dispatchEvent(new e.window.KeyboardEvent('keydown',{key:'ArrowUp',bubbles:true}));
    assert.equal(e.value().sections[0].title,'Second');
    assert.equal(outgoing.at(-1).section, 1, 'Selected object is highlighted at its old snapshot index until refresh');
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea').value,'Second','Selected object follows reorder');
    previewMessage(e,frame,{type:'iss-preview-select',section:0});
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea').value,'Edited first','Old frame index is mapped to the original object');
    previewMessage(e,frame,{type:'iss-preview-insert',section:1});
    const search=e.root.querySelector('.iss-editorial-palette input[type=search]');e.input(search,'Ein Text');search.dispatchEvent(new e.window.KeyboardEvent('keydown',{key:'Enter',bubbles:true}));
    assert.equal(e.value().sections[0].type,'statement','Gap inserts before the old snapshot object after reorder');
    assert.equal(e.value().sections.length,3);
    assert.equal(e.value().sections[2].title,'Edited first');
    assert.equal(frame.isConnected,true,'A pending save does not blank the canvas');
  } finally {e.dom.window.close();}
});

test('Canvas changes save before blur, defer frame replacement, reject untrusted paths and sessions, and Escape restores the original',async()=>{
  const e=await editor('landing',workspaceOptions());
  try {
    await e.settle(); const frame=readyFrame(e);
    const start={type:'iss-preview-field-start',section:0,field:'title',session:'a',sequence:0};
    previewMessage(e,frame,start,'https://bad.example');
    previewMessage(e,frame,{...start,token:'old'});
    previewMessage(e,frame,{...start,field:'__proto__'});
    assert.notEqual(e.root.querySelector('.iss-editorial-studio__fields').inert,true);
    previewMessage(e,frame,start);
    assert.equal(e.root.querySelector('.iss-editorial-studio__fields').inert,true);
    previewMessage(e,frame,{...start,type:'iss-preview-field-change',sequence:1,value:'Unfinished title'});
    previewMessage(e,frame,{...start,type:'iss-preview-field-change',sequence:2,session:'wrong',value:'Bad'});
    previewMessage(e,frame,{...start,type:'iss-preview-field-change',sequence:1,value:'Out of order'});
    assert.equal(e.value().sections[0].title,'Unfinished title');
    await e.settle();
    assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections[0].title,'Unfinished title','Autosave works during active typing');
    assert.equal(e.root.querySelectorAll('.iss-editorial-live-preview__frame').length,1,'No reload while editing');
    previewMessage(e,frame,{...start,type:'iss-preview-field-end',sequence:3,cancel:true});
    assert.equal(e.value().sections[0].title,'First');
    assert.equal(e.root.querySelector('.iss-editorial-studio__fields').inert,false);
    await e.settle(); assert.ok(e.root.querySelector('iframe[hidden]'),'Actual renderer reconciles after the edit');
  } finally {e.dom.window.close();}
});

test('Canvas invalid drafts remain recoverable and form editing survives a preview or save failure',async()=>{
  const e=await editor('landing',workspaceOptions());
  try {
    await e.settle(); const frame=readyFrame(e);
    const data={section:0,field:'title',session:'incomplete'};
    previewMessage(e,frame,{...data,type:'iss-preview-field-start'});
    previewMessage(e,frame,{...data,type:'iss-preview-field-change',sequence:1,value:''});
    e.setValidation('Titel fehlt');
    previewMessage(e,frame,{...data,type:'iss-preview-field-end',sequence:2});
    await e.settle();
    assert.equal(e.value().sections[0].title,'');
    assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections[0].title,'');
    assert.equal(frame.isConnected,true);
    assert.match(e.root.querySelector('.iss-editorial-studio__notice').textContent,/Titel fehlt/);
    const title=e.root.querySelector('.iss-editorial-field--title textarea');
    e.setValidation('');e.setFailure(true);e.input(title,'Recovered');await e.settle();
    assert.match(e.root.querySelector('.iss-editorial-studio__notice').textContent,/Nicht gesichert/);
    assert.equal(title.isConnected,true);
    assert.equal(e.value().sections[0].title,'Recovered');
    e.setFailure(false);e.button('Erneut sichern').click();await e.settle();
    assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections[0].title,'Recovered');
  } finally {e.dom.window.close();}
});

test('Frame plain-text keyboard edits wait for acknowledgement, stream changes, and Escape restores rendered text',()=>{
  const dom=new JSDOM('<section data-iss-preview-section="0"><h1 data-iss-field="title" data-iss-profile="plain">Original</h1></section>',{url:'http://localhost:8082',runScripts:'outside-only'});
  const {window}=dom;const sent=[];const parent={postMessage:data=>sent.push(data)};
  try {
    Object.defineProperty(window,'parent',{value:parent});window.issEditorialPreviewFrame={token:'t',editing:true};
    window.eval(fs.readFileSync('plugins/iss-editorial/assets/preview-frame.js','utf8'));
    const title=window.document.querySelector('h1');
    assert.equal(window.document.querySelectorAll('.iss-preview-section-tools').length, 0);
    const section = title.closest('section');
    assert.equal(section.querySelectorAll('button').length, 0, 'The section has no duplicate editing menu');
    assert.equal(section.querySelector('.iss-preview-section-label').getAttribute('aria-hidden'), 'true');
    title.click(); assert.equal(sent.at(-1).type, 'iss-preview-select');
    title.dispatchEvent(new window.KeyboardEvent('keydown',{key:'Enter',bubbles:true}));
    assert.equal(sent.at(-1).type,'iss-preview-field-start');
    assert.equal(title.hasAttribute('data-iss-editing'),false);
    const session=sent.at(-1).session;
    const ack=data=>window.dispatchEvent(new window.MessageEvent('message',{origin:window.location.origin,source:parent,data:{type:'iss-preview-field-ack',token:'t',session,...data}}));
    ack({accepted:true,sequence:0,profile:'plain',value:'Original'});
    assert.equal(title.hasAttribute('data-iss-editing'),true);
    title.textContent='Typing';title.dispatchEvent(new window.Event('input',{bubbles:true}));
    assert.equal(sent.at(-1).value,'Typing');
    title.dispatchEvent(new window.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
    assert.equal(sent.at(-1).cancel,true);const sequence=sent.at(-1).sequence;
    ack({accepted:true,sequence});assert.equal(title.textContent,'Original');
    assert.equal(title.hasAttribute('contenteditable'),false);
  } finally {window.close();}
});

test('Workspace waits for an active canvas edit before reordering and exposes native controls without replacing the workspace',async()=>{
  const e=await editor('landing',workspaceOptions());
  try {
    await e.settle();const frame=readyFrame(e);
    const data={section:0,field:'title',session:'move'};
    previewMessage(e,frame,{...data,type:'iss-preview-field-start'});
    previewMessage(e,frame,{...data,type:'iss-preview-field-change',sequence:1,value:'Typed'});
    const handle=e.root.querySelector('[data-section-index="0"] .iss-editorial-card__drag-handle');
    handle.dispatchEvent(new e.window.KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));
    assert.equal(e.value().sections[0].title,'Typed','Reorder waits for the frame finish acknowledgement');
    previewMessage(e,frame,{...data,type:'iss-preview-field-end',sequence:2});
    assert.equal(e.value().sections[1].title,'Typed');
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea').value,'Typed');
    const before=JSON.stringify(e.value());
    const workspace = e.root.querySelector('.iss-editorial-studio');
    e.button('Angaben & Beziehungen …').click();assert.equal(e.root.querySelector('.iss-editorial-studio'), workspace);
    e.root.querySelector('[aria-label="Arbeitsfläche vergrößern"]').click();
    assert.equal(e.root.querySelector('.iss-editorial-studio'), workspace);
    assert.equal(JSON.stringify(e.value()),before,'View changes preserve the same document');
    await e.settle();assert.match(e.root.querySelector('.iss-editorial-live-preview__frame').src,/iss_editorial_canvas=1/);
  } finally {e.dom.window.close();}
});


for (const [format, openingSkin, version, visible] of [
  ['renamed_format', 'custom-skin', 3, true], ['projekt', '', 3, false],
  ['landing', 'frontpage', 3, true], ['landing', '', 3, false],
  ['renamed_format', 'custom-skin', 2, false], ['renamed_format', 'custom-skin', 1, false]
]) {
  test(`${format} opening ${openingSkin || 'absent'}, v${version}: capability and version gate the treatment without losing saved input`, async () => {
    const e = await editor(format, {
      openingSkin,
      sections: { renamed_hero: { label: 'Hero', supports: ['treatment'], default_treatment: 'hero.normal', treatments: [
        { slug: 'hero.normal', label: 'Normal' },
        { slug: 'hero.opening', label: 'Opening', role: 'opening', min_version: 3 }
      ] } },
      document: { schema_version: version, skin: 'different-skin', sections: [{ type: 'renamed_hero', treatment: 'hero.opening', title: 'Keep saved title' }] }
    });
    try {
      assert.equal(!!e.root.querySelector('.iss-editorial-field--treatment input[value="hero.opening"]'), visible);
      assert.equal(e.value().sections[0].treatment, 'hero.opening', 'Unavailable saved treatment survives for server validation');
      assert.equal(e.value().skin, 'different-skin', 'Capability does not silently change the document skin');
      e.button('Darstellung').click();
      e.root.querySelector('.iss-editorial-field--treatment input[value="hero.normal"]').click();
      await e.settle();
      assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections[0].treatment, 'hero.normal', 'Explicit correction enters private recovery');
    } finally { e.dom.window.close(); }
  });
}

test('Workspace opening guidance follows its owner and invalid opening remains visible after reorder', async () => {
  const options = workspaceOptions(); options.isFrontPage = true; options.openingSkin = 'frontpage';
  options.document.skin = 'frontpage'; options.skins = [{slug:'frontpage',label:'Startseite'}];
  options.document.sections[0].treatment = 'feature.opening'; options.document.sections[0].media_refs = [{id:1}];
  options.sections.feature.treatments.push({slug:'feature.opening',label:'Seitenauftakt',schematic:'opening',role:'opening',min_version:3});
  const e = await editor('landing', options);
  try {
    assert.match(e.root.querySelector('.iss-editorial-opening-note').textContent, /ersetzt den Auftakt/);
    e.root.querySelectorAll('.iss-editorial-outline__select')[1].click();
    assert.equal(e.root.querySelector('.iss-editorial-opening-note').hidden, true, 'Section 2 never claims to replace the template opening');
    e.root.querySelectorAll('.iss-editorial-outline__select')[0].click();
    const handle=e.root.querySelector('[data-section-index="0"] .iss-editorial-card__drag-handle');
    handle.dispatchEvent(new e.window.KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));
    assert.match(e.root.querySelector('.iss-editorial-opening-note').textContent, /Seitenauftakt prüfen/);
    assert.equal(e.root.querySelector('.iss-editorial-opening-note').hidden, false, 'Invalid opening does not lose its warning');
    assert.match(e.root.querySelector('.iss-editorial-studio__position').selectedOptions[0].textContent, /^Abschnitt 2 von 2 · /);
  } finally { e.dom.window.close(); }
});

test('Workspace trash undo restores its own section and leaving restores native status and scrolling', async () => {
  const e = await editor('landing', workspaceOptions());
  try {
    assert.equal(e.window.document.body.classList.contains('iss-editorial-studio-open'), true);
    assert.ok(e.root.querySelector('.iss-editorial-studio__status .iss-editorial-autosave-status'));
    e.button('In Papierkorb', e.root.querySelector('.iss-editorial-studio__inspector')).click();
    assert.equal(e.value().sections.length, 1);
    e.button('Rückgängig').click();
    await e.settle();
    assert.equal(e.value().sections[0].title, 'First');
    assert.equal(e.value().deleted_sections.length, 0);
    const content = e.value();
    e.button('Angaben & Beziehungen …').click();
    assert.equal(e.window.document.body.classList.contains('iss-editorial-studio-open'), false);
    assert.equal(e.container.querySelectorAll('.iss-editorial-autosave-status').length, 1);
    assert.equal(e.root.contains(e.container.querySelector('.iss-editorial-autosave-status')), true);
    assert.deepEqual(e.value(), content);
  } finally { e.dom.window.close(); }
});


test('Native link dialog remains interactive when WordPress moves it out of the expanded workspace', async () => {
  const e = await editor();
  try {
    const original = JSON.stringify(e.value());
    const dialog = e.window.document.getElementById('wp-link-wrap');
    const backdrop = e.window.document.getElementById('wp-link-backdrop');
    assert.notEqual(dialog.inert, true); assert.notEqual(backdrop.inert, true);
    e.window.document.body.append(backdrop, dialog);
    e.root.querySelector('[aria-label="Arbeitsfläche verkleinern"]').click();
    e.root.querySelector('[aria-label="Arbeitsfläche vergrößern"]').click();
    assert.notEqual(dialog.inert, true); assert.notEqual(backdrop.inert, true);
    assert.equal(e.button('Update').inert, true, 'Covered native save controls remain outside the keyboard path');
    assert.equal(JSON.stringify(e.value()), original);
  } finally { e.dom.window.close(); }
});

test('Native authentication and competing-editor dialogs remain interactive in the expanded workspace', async () => {
  const e=await editor();
  try{
    for(const id of ['wp-auth-check-wrap','post-lock-dialog']){
      const modal=e.window.document.createElement('div');modal.id=id;
      e.window.document.getElementById('wpwrap').appendChild(modal);
    }
    e.root.querySelector('[aria-label="Arbeitsfläche verkleinern"]').click();
    e.root.querySelector('[aria-label="Arbeitsfläche vergrößern"]').click();
    for(const id of ['wp-auth-check-wrap','post-lock-dialog']){
      let modal=e.window.document.getElementById(id);
      while(modal){assert.notEqual(modal.inert,true,id+' must not have an inert ancestor');modal=modal.parentElement;}
    }
    assert.equal(e.button('Update').inert,true);
  }finally{e.dom.window.close();}
});

test('Minimizing the workspace uncovers native controls without submitting or changing the draft', async () => {
  const e = await editor('landing', workspaceOptions());
  try {
    const native = e.button('Update'); native.id = 'publish'; native.scrollIntoView = () => {};
    let submitted = false;
    e.window.document.getElementById('post').addEventListener('submit', () => { submitted = true; });
    const before = e.value();
    assert.equal(native.inert, true, 'Covered native control is outside the keyboard path');
    e.root.querySelector('[aria-label="Arbeitsfläche verkleinern"]').click();
    assert.notEqual(native.inert, true);
    assert.equal(submitted, false);
    assert.equal(e.window.document.body.classList.contains('iss-editorial-studio-open'), false);
    assert.deepEqual(e.value(), before);
  } finally { e.dom.window.close(); }
});

test('Starter compositions require an explicit choice, reuse the document, and preserve trash', async () => {
  const options = workspaceOptions();
  options.document.sections = [];
  options.document.deleted_sections = [{type:'statement',title:'Retained'}];
  options.starters = [{label:'Text with image',sections:[{type:'feature',title:'Introduction',treatment:'feature.media-text'},{type:'statement'}]}];
  const e = await editor('article', options);
  try {
    assert.equal(e.value().sections.length, 0);
    e.button('Text with image').click();
    assert.equal(e.value().sections[0].title, 'Introduction');
    assert.equal(e.value().sections[0].treatment, 'feature.media-text');
    assert.equal(e.value().deleted_sections[0].title, 'Retained');
    assert.equal(e.root.querySelector('.iss-editorial-starter'), null);
    await e.settle();
    assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections.length, 2);
  } finally { e.dom.window.close(); }
});

test('Tour owner controls remain inside the shared workspace and native form', async () => {
  const e = await editor('fuehrung', {...workspaceOptions(),routeStations:{enabled:true}});
  try {
    assert.match(e.root.querySelector('.iss-editorial-studio__owner-controls').textContent, /Native route stations/);
    assert.equal(e.root.querySelectorAll('.iss-editorial-studio').length, 1);
    e.button('Angaben & Beziehungen …').click();
    assert.notEqual(e.button('Update').inert, true);
  } finally { e.dom.window.close(); }
});

test('One sidebar switches between outline, chooser, editing and page controls without replacing the preview', async () => {
  const e = await editor('fuehrung', {...workspaceOptions(),routeStations:{enabled:true}});
  try {
    const studio = e.root.querySelector('.iss-editorial-studio');
    const preview = e.root.querySelector('.iss-editorial-live-preview');
    assert.equal(studio.dataset.panel, 'outline');
    assert.equal(e.root.querySelector('.iss-editorial-studio__inspector').hidden, true);
    e.button('Hinzufügen').click();
    assert.equal(studio.dataset.panel, 'insert');
    assert.equal(e.root.querySelector('.iss-editorial-studio__outline').hidden, true);
    e.root.querySelector('.iss-editorial-gesture').click();
    assert.equal(studio.dataset.panel, 'inspector');
    assert.equal(e.root.querySelector('.iss-editorial-palette').hidden, true);
    e.input(e.root.querySelector('.iss-editorial-field--title textarea'), 'New section title');
    e.button('Fertig').click();
    await e.settle();
    assert.equal(studio.dataset.panel, 'outline');
    assert.equal(studio.dataset.view, 'preview');
    assert.ok(JSON.parse(e.requests.at(-1).get('document')).sections.some(s => s.title === 'New section title'));
    e.button('Seite').click();
    assert.equal(studio.dataset.panel, 'settings');
    assert.equal(e.root.querySelector('.iss-editorial-studio__settings').hidden, false);
    assert.equal(e.root.querySelector('.iss-editorial-studio__owner-controls').closest('.iss-editorial-studio__outline'), null);
    assert.equal(e.root.querySelector('.iss-editorial-live-preview'), preview);
  } finally { e.dom.window.close(); }
});

test('Chooser guidance follows event availability while retained sections stay editable', async () => {
  const sections = {
    kapitel: {label:'Kapitel', description:'Shared text', group:'Text', portable:true, supports:['body']},
    programm: {label:'Programm', description:'Festival programme', group:'Text', supports:['body']},
    intro: {label:'Intro', description:'Retained introduction', ui_hidden:true, supports:['body']}
  };
  const e = await editor('veranstaltung', {sections, document:{schema_version:3, entity_key:'event.festival', sections:[{type:'intro', body:'Keep intro'}, {type:'programm', body:'Keep programme'}]}, documentBindings:{entity_key:'#entity'}, sectionContexts:{entity_key:{'event.festival':['kapitel','intro','programm'], 'event.general':['kapitel','intro']}}});
  try {
    e.button('Hinzufügen').click();
    const choices = () => [...e.root.querySelectorAll('.iss-editorial-gesture')];
    assert.deepEqual(choices().map(node => node.dataset.sectionType), ['programm','kapitel']);
    const programme = choices()[0];
    assert.equal(e.window.document.getElementById(programme.getAttribute('aria-describedby')).textContent, 'Festival programme');
    assert.equal(e.window.document.getElementById(programme.getAttribute('aria-labelledby')).textContent, 'Programm');
    assert.equal(choices()[1].querySelector('small'), null, 'Portable tiles stay compact');
    e.input(e.root.querySelector('.iss-editorial-palette input[type="search"]'), 'Festival');
    assert.equal(choices().length, 1, 'Search includes the owner explanation');
    e.button('Abschnitte').click();
    e.root.querySelector('.iss-editorial-outline__select').click();
    assert.equal(e.root.querySelector('.iss-editorial-studio__guidance').textContent, 'Retained introduction');
    e.button('Hinzufügen').click();
    const search = e.root.querySelector('.iss-editorial-palette input[type="search"]');
    e.input(search, 'Festival');
    const entity = e.window.document.getElementById('entity');
    entity.value = 'event.general'; entity.dispatchEvent(new e.window.Event('change', {bubbles:true}));
    assert.equal(choices().length, 0, 'An already-open chooser immediately drops unavailable choices');
    assert.equal(search.value, 'Festival', 'Refreshing availability preserves the search');
    e.input(search, '');
    assert.deepEqual(choices().map(node => node.dataset.sectionType), ['kapitel'], 'Changed structure refreshes insertion choices');
    e.button('Abschnitte').click();
    e.root.querySelectorAll('.iss-editorial-outline__select')[1].click();
    assert.equal(e.root.querySelector('.iss-editorial-studio__guidance').textContent, 'Festival programme');
    assert.equal(e.value().sections[1].body, 'Keep programme', 'Restricted saved content is retained for correction, not silently deleted');
    await e.settle();
    assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections.length, 2);
    assert.ok(e.value().sections.every(section => !('description' in section) && !('portable' in section)), 'Guidance never becomes document data');
  } finally { e.dom.window.close(); }
});

test('Automatic content source hints track the selected owner through save and finish', async () => {
  const e = await editor('landing', {sections:{dynamic_slot:{label:'Automatische Inhalte', description:'Select a source', supports:['treatment','slot_key'], slots:{projects:{label:'Projects',treatment:'slot.projects'},team:{label:'Team',treatment:'slot.team'}}, treatments:[{slug:'slot.projects',label:'Projects',hint:'Maintained in projects'},{slug:'slot.team',label:'Team',hint:'Maintained in team'}]}}, document:{schema_version:3,sections:[{type:'dynamic_slot',slot_key:'projects',treatment:'slot.projects'}]}});
  try {
    e.root.querySelector('.iss-editorial-outline__select').click();
    e.button('Darstellung').click();
    let select = e.root.querySelector('.iss-editorial-field--slot-key select');
    assert.equal(select.labels[0].textContent, 'Automatische Inhalte');
    assert.equal(e.window.document.getElementById(select.getAttribute('aria-describedby')).textContent, 'Maintained in projects');
    select.value = 'team'; select.dispatchEvent(new e.window.Event('change', {bubbles:true}));
    select = e.root.querySelector('.iss-editorial-field--slot-key select');
    assert.equal(e.window.document.getElementById(select.getAttribute('aria-describedby')).textContent, 'Maintained in team');
    e.button('Fertig').click(); await e.settle();
    assert.equal(e.root.querySelector('.iss-editorial-studio').dataset.panel, 'outline');
    const saved = JSON.parse(e.requests.at(-1).get('document')).sections[0];
    assert.equal(saved.slot_key, 'team'); assert.equal(saved.treatment, 'slot.team');
    assert.equal(saved.hint, undefined);
  } finally { e.dom.window.close(); }
});

test('Finish keeps failed or invalid drafts in the editing panel and permits retry', async () => {
  const e = await editor('article', workspaceOptions());
  try {
    e.root.querySelector('.iss-editorial-outline__select').click();
    const studio = e.root.querySelector('.iss-editorial-studio');
    e.input(e.root.querySelector('.iss-editorial-field--title textarea'), 'Recoverable title');
    e.setValidation('Please complete the link');
    e.button('Fertig').click(); await e.settle();
    assert.equal(studio.dataset.panel, 'inspector');
    assert.match(e.root.querySelector('.iss-editorial-studio__notice').textContent, /Entwurf gesichert/);
    e.setValidation(''); e.setFailure(true);
    e.button('Fertig').click(); await e.settle();
    assert.equal(studio.dataset.panel, 'inspector');
    assert.equal(e.button('Fertig').disabled, false);
    assert.equal(e.value().sections[0].title, 'Recoverable title');
    e.setFailure(false); e.button('Fertig').click(); await e.settle();
    assert.equal(studio.dataset.panel, 'outline');
  } finally { e.dom.window.close(); }
});

test('Preview device sizing preserves CSS viewport dimensions with portable transforms', async () => {
  const e = await editor('article', workspaceOptions());
  try {
    const viewport = e.root.querySelector('.iss-editorial-live-preview__viewport');
    Object.defineProperty(viewport,'clientWidth',{value:300});
    Object.defineProperty(viewport,'clientHeight',{value:500});
    e.button('Telefon').click();
    const css = e.root.querySelector('.iss-editorial-live-preview style').textContent;
    assert.match(css,/width:390px/);
    assert.match(css,/height:650px/);
    assert.match(css,/transform:scale\(0\.769/);
    assert.doesNotMatch(css,/zoom:/);
  } finally { e.dom.window.close(); }
});


test('Optional document bindings save cleared text and recover an intentionally empty value', async () => {
  const e = await editor('veranstaltung', { document: { schema_version: 1, entity_key: 'event.general', hero_subtitle: 'Before', sections: [] }, documentBindings: { hero_subtitle: '#excerpt' } });
  try {
    e.input(e.window.document.getElementById('excerpt'), '');
    await e.settle();
    assert.equal(JSON.parse(e.requests.at(-1).get('document')).hero_subtitle, '');
  } finally { e.dom.window.close(); }
  const r = await editor('veranstaltung', { recovery: { base: 'base', title: 'Recovered', excerpt: 'Summary', document: {schema_version: 1, entity_key: 'event.general', hero_subtitle: '', sections: []} }, documentBindings: {hero_subtitle: '#excerpt'} });
  try {
    r.button('Entwurf weiterbearbeiten').click();
    await r.settle();
    assert.equal(r.window.document.getElementById('excerpt').value, '');
    assert.equal(r.value().hero_subtitle, '');
  } finally { r.dom.window.close(); }
});

for (const portable of [true, false]) {
  test((portable ? 'Shared Material' : 'Place Material') + ': media selection preserves the declared download contract', async () => {
    const image = {id: '7', kind: 'media', source: 'wp-media', mime: 'image/png', label: 'Saved image'};
    const pdf = {id: '8', kind: 'media', source: 'wp-media', mime: 'application/pdf', label: 'Saved document'};
    const e = await editor(portable ? 'article' : 'place', {
      sections: {material: {label: 'Begleitende Dateien', supports: ['media_refs', 'links'], portable, editor: {media_kind:'files',media_hide_images:!portable}}},
      document: {schema_version: 3, sections: [{type: 'material', title: 'Downloads', media_refs: [image, pdf]}]}
    });
    try {
      let options;
      const callbacks = {};
      const attachments = [image, pdf, {id: '9', mime: 'audio/mpeg', title: 'Recording', url: '/recording.mp3'}];
      const selection = {add() {}, map: fn => attachments.map(a => fn({toJSON: () => a}))};
      e.window.wp.media = config => {
        options = config;
        return {on: (name, fn) => {callbacks[name] = fn;}, state: () => ({get: () => selection}), open: () => callbacks.open()};
      };
      e.window.wp.media.attachment = () => ({fetch() {}});
      e.button('Dateien suchen').click();
      assert.equal(options.library.type, portable ? undefined : 'application');
      callbacks.select();
      await e.settle();
      assert.deepEqual(Array.from(e.value().sections[0].media_refs, ref => ref.id), portable ? ['7','8','9'] : ['8','9']);
      assert.equal(e.value().sections[0].media_refs.find(ref => ref.id === '8').label, 'Saved document');
      const saved = JSON.parse(e.requests.at(-1).get('document'));
      assert.equal(saved.sections[0].media_refs.length, portable ? 3 : 2, 'Autosave keeps every selected supported file');
      if (portable) {
        assert.doesNotMatch(e.root.textContent, /Bild wird im Material nicht gerendert/);
        let setOptions;
        e.window.issEditorialSetMediaPicker = {create: (_root, config) => {setOptions = config;}};
        e.button('Aus Set auswählen').click();
        assert.equal(setOptions.mediaType, '', 'The owner picker accepts all approved attachment types');
        setOptions.onConfirm(setOptions.initialSelection);
        assert.equal(e.value().sections[0].media_refs.length, 3, 'Confirming a Set selection cannot drop the saved image');
        e.button('Entfernen', e.root.querySelector('.iss-editorial-media-item')).click();
        assert.deepEqual(Array.from(e.value().sections[0].media_refs, ref => ref.id), ['8','9'], 'Only explicit removal drops the image');
      }
    } finally { e.dom.window.close(); }
  });
}

for (const format of ['article', 'ausstellung']) {
  test(format + ': shared archive picker retains captions and Set identity through edits and reselection', async () => {
    const reference = {kind: 'archive_object', source: 'iss-archive', id: '15127', label: 'Saved object', thumbnail: '/object.jpg', set_id: '27', set_title: 'Saved Set', member_id: '4543', member_caption: 'Saved caption'};
    const e = await editor(format, {sections: {objektfokus: {label: 'Archivobjekte', portable: true, supports: ['anchor', 'object_refs'], editor: {reference_captions:true}}}, document: {schema_version: 3, sections: [{type: 'objektfokus', anchor: 'sources', object_refs: [reference]}]}});
    try {
      e.input(e.root.querySelector('.iss-editorial-media-item input'), 'Edited label');
      e.input(e.root.querySelector('.iss-editorial-media-item textarea'), 'Edited caption');
      await e.settle();
      let options;
      e.window.issArchiveObjectPicker = {create: (_mount, config) => { options = config; }};
      e.button('Archivobjekte auswählen').click();
      assert.equal(options.contextPostId, 99);
      assert.deepEqual(Array.from(options.initialSelection), ['15127']);
      options.onConfirm([{id: '26215', title: 'New source', setId: 13, memberId: 14, memberCaption: 'New Set caption'}, {id: '15127', title: 'Owner title changed'}]);
      await e.settle();
      assert.deepEqual(e.value().sections[0].object_refs[1], {...reference, label: 'Edited label', member_caption: 'Edited caption'});
      assert.equal(e.value().sections[0].object_refs[0].member_caption, 'New Set caption');
      assert.equal(e.value().sections[0].object_refs[0].set_id, '13');
      e.button('Fertig').click(); await e.settle();
      assert.equal(e.root.querySelector('.iss-editorial-studio').dataset.view, 'preview');
      e.root.querySelector('.iss-editorial-outline__select').click();
      e.button('Entfernen', e.root.querySelector('.iss-editorial-media-item')).click();
      await e.settle();
      assert.equal(e.value().sections[0].object_refs.length, 1);
      assert.equal(e.value().sections[0].object_refs[0].id, '15127');
      assert.equal(JSON.parse(e.requests.at(-1).get('document')).sections[0].object_refs[0].member_caption, 'Edited caption');
    } finally { e.dom.window.close(); }
  });
}

test('Place insertion limits preserve retained duplicates, editing, reorder and trash recovery', async () => {
  const sections = {
    intro: {label:'Einleitung', description:'Am Anfang; einmal hinzufügen.', max_instances:1, supports:['media_refs']},
    epoche: {label:'Epoche', description:'Repeatable history', supports:['body']},
    gegenwart: {label:'Gegenwart', description:'Nach der Geschichte; einmal hinzufügen.', max_instances:1, supports:['media_refs','links']}
  };
  const orderNotice = 'Feste Seitenfolge: Einleitung → Epochen → Gegenwart.';
  const e = await editor('place', {sections, orderNotice, document:{schema_version:3, sections:[{type:'intro',title:'Retained first',body:'First body'},{type:'epoche',title:'Historic phase',body:'History'},{type:'intro',title:'Retained second',body:'Second body'}]}});
  try {
    assert.equal(e.root.querySelector('.iss-editorial-studio__outline .iss-editorial-studio__guidance').textContent, orderNotice);
    const choices = () => [...e.root.querySelectorAll('.iss-editorial-gesture')].map(node => node.dataset.sectionType);
    e.button('Hinzufügen').click();
    assert.deepEqual(choices().sort(), ['epoche','gegenwart']);
    const presentChoice = e.root.querySelector('[data-section-type="gegenwart"]');
    presentChoice.click();
    assert.equal(e.value().sections.filter(s => s.type === 'gegenwart').length, 1);
    presentChoice.click();
    assert.equal(e.value().sections.filter(s => s.type === 'gegenwart').length, 1, 'Stale chooser/insert callbacks cannot bypass the insertion limit');
    e.button('Hinzufügen').click();
    assert.deepEqual(choices(), ['epoche']);
    e.button('Abschnitte').click();
    e.root.querySelector('[data-section-index="2"] .iss-editorial-outline__select').click();
    e.input(e.root.querySelector('.iss-editorial-field--title textarea'), 'Edited retained second');
    assert.equal(e.value().sections[2].title, 'Edited retained second', 'Retained repeated slots remain editable');
    e.button('Abschnitte').click();
    e.root.querySelector('[data-section-index="2"] .iss-editorial-card__drag-handle').dispatchEvent(new e.window.KeyboardEvent('keydown', {key:'ArrowUp',bubbles:true}));
    assert.equal(e.value().sections[1].title, 'Edited retained second', 'Existing repeated slots can still be ordered');
    for (let n = 0; n < 2; n++) {
      e.button('Abschnitte').click();
      e.root.querySelector('[data-section-index="0"] .iss-editorial-outline__select').click();
      e.button('In Papierkorb', e.root.querySelector('.iss-editorial-studio__inspector')).click();
      e.button('Hinzufügen').click();
      assert.equal(choices().includes('intro'), n === 1, 'Insertion becomes available only when all existing introductions are in trash');
    }
    e.root.querySelector('[data-section-type="intro"]').click();
    assert.equal(e.value().sections.filter(s => s.type === 'intro').length, 1);
    e.button('Wiederherstellen').click();
    await e.settle();
    assert.equal(e.value().sections.filter(s => s.type === 'intro').length, 2, 'Recovery restores content even after a new introduction was added');
    e.button('Hinzufügen').click();
    assert.deepEqual(choices(), ['epoche']);
    await e.settle();
    const saved = JSON.parse(e.requests.at(-1).get('document'));
    assert.ok(saved.sections.some(s => s.title === 'Edited retained second'));
    assert.ok(saved.deleted_sections.some(s => s.title === 'Retained first'));
  } finally { e.dom.window.close(); }
});

test('Sidebar navigation keeps its hierarchy and contextual help without changing section order or saving', async () => {
  const e = await editor();
  try {
    await e.settle(); const original = JSON.stringify(e.value()); const requests = e.requests.length;
    const top = e.root.querySelector('.iss-editorial-studio__panel-tabs');
    const help = e.root.querySelector('.iss-editorial-studio__panel-head > .iss-editorial-help-button');
    const active = () => top.querySelector('[aria-pressed="true"]').textContent;
    assert.equal(e.root.querySelectorAll('.iss-editorial-help-button').length, 1);
    assert.equal(active(), 'Abschnitte');
    e.root.querySelector('.iss-editorial-outline__select').click();
    assert.equal(active(), 'Abschnitte'); assert.equal(help.dataset.help, 'section:kapitel');
    e.button('Links').click(); assert.equal(help.dataset.help, 'links');
    const previous = e.root.querySelector('[aria-label="Vorherigen Abschnitt auswählen"]');
    const next = e.root.querySelector('[aria-label="Nächsten Abschnitt auswählen"]');
    const selector = e.root.querySelector('select[aria-label="Abschnitt auswählen"]');
    assert.deepEqual(Array.from(selector.options, option => option.textContent), ['Abschnitt 1 von 2 · First section', 'Abschnitt 2 von 2 · Legacy intro']);
    assert.equal(previous.disabled, true); assert.match(next.dataset.helpText, /nächsten Abschnitt/);
    next.click(); assert.equal(selector.value, '1');
    assert.equal(next.disabled, true); assert.equal(previous.disabled, false);
    previous.click(); assert.equal(selector.value, '0');
    e.root.querySelector('.iss-editorial-studio__inspector').scrollTop = 400;
    selector.focus(); selector.value = '1'; selector.dispatchEvent(new e.window.Event('change', {bubbles:true}));
    assert.equal(e.root.querySelector('[aria-current="true"]').title, 'Legacy intro · Intro');
    assert.equal(e.window.document.activeElement, selector, 'Keyboard section selection keeps focus on the selector');
    assert.equal(e.root.querySelector('.iss-editorial-studio__inspector').scrollTop, 0, 'Short windows reveal the new section from its start');
    assert.equal(next.disabled, true); assert.equal(help.dataset.help, 'section:intro');
    assert.match(selector.title, /Legacy intro/);
    e.button('Hinzufügen', top).click(); assert.equal(active(), 'Hinzufügen'); assert.equal(help.dataset.help, 'arrange');
    e.button('Seite', top).click(); assert.equal(active(), 'Seite'); assert.equal(help.dataset.help, 'page');
    e.button('Abschnitte', top).click(); assert.equal(active(), 'Abschnitte');
    await e.settle(); assert.equal(JSON.stringify(e.value()), original); assert.equal(e.requests.length, requests);
  } finally { e.dom.window.close(); }
});

test('Help search and contextual navigation preserve the document, preview and save requests', async () => {
  const e = await editor('projekt', { publishing: {status:'publish',canPublish:false}, sections: {kapitel:{label:'Kapitel',description:'Ein Thema vertiefen.',help:['Eine eigene Überschrift verwenden.'],supports:['body']}} });
  try {
    await e.settle(); const original = JSON.stringify(e.value()); const count = e.requests.length;
    const frame = e.root.querySelector('iframe');
    const help = e.root.querySelector('.iss-editorial-help');
    e.button('Hilfe').click();
    assert.equal(help.hidden, false);
    assert.equal(e.window.document.activeElement, help.querySelector('h3'));
    const search = help.querySelector('input'); e.input(search, 'Mediathek');
    assert.ok(e.button('Bilder auswählen', help));
    assert.equal(e.button('Kapitel', help), undefined);
    const enter = new e.window.KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}); search.dispatchEvent(enter);
    assert.equal(enter.defaultPrevented,true,'Searching help must never submit the WordPress form');
    assert.equal(help.querySelector('h3').textContent,'Bilder auswählen');
    e.input(search, 'nothingmatches');
    assert.match(help.querySelector('nav').textContent, /Keine passende Hilfe/);
    e.input(search, ''); e.button('Kapitel', help).click();
    assert.match(help.querySelector('article').textContent, /Eine eigene Überschrift/);
    help.dispatchEvent(new e.window.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
    assert.equal(help.hidden,true); assert.equal(e.window.document.activeElement,e.button('Hilfe'));
    e.root.querySelector('.iss-editorial-outline__select').click();
    const contextual = e.root.querySelector('.iss-editorial-studio__panel-head > .iss-editorial-help-button'); contextual.click();
    const popup = e.root.querySelector('.iss-editorial-help-popup');
    assert.equal(popup.getAttribute('role'),'dialog'); assert.match(popup.textContent,/Eine eigene Überschrift/);
    e.button('Mehr dazu',popup).click();
    assert.equal(popup.hidden,true); assert.equal(help.hidden,false); assert.equal(help.querySelector('h3').textContent,'Kapitel');
    e.button('Hilfe schließen',help).click();
    assert.equal(e.window.document.activeElement,contextual);
    await e.settle();
    assert.equal(JSON.stringify(e.value()),original); assert.equal(e.requests.length,count);
    assert.equal(e.root.querySelector('iframe'),frame);
  } finally { e.dom.window.close(); }
});

for (const [status, canPublish, expected] of [
  ['publish', false, /öffentliche Seite/],
  ['draft', false, /Zur Überprüfung einreichen/],
  ['draft', true, /Veröffentlichen/],
  ['future', true, /geplante Fassung/],
  ['private', true, /private Fassung/]
]) {
  test('Help explains the actual save effect for ' + status + ', publish capability ' + canPublish, async () => {
    const e = await editor('projekt', { publishing: {status,canPublish} });
    try {
      assert.match(e.root.querySelector('.iss-editorial-studio__save-guidance').textContent,expected);
      e.button('Hilfe').click(); e.button('Speichern und Freigeben').click();
      assert.match(e.root.querySelector('.iss-editorial-help__article').textContent, expected);
      assert.match(e.root.querySelector('.iss-editorial-help__article').textContent, /Verkleinere die Arbeitsfläche/);
    } finally { e.dom.window.close(); }
  });
}

test('Control explanations support focus, Escape, click, outside dismissal and existing descriptions', async () => {
  const e = await editor();
  try {
    e.root.querySelector('.iss-editorial-outline__select').click();
    const finish = e.button('Fertig'); const popup = e.root.querySelector('.iss-editorial-help-popup');
    finish.setAttribute('aria-describedby','existing-guidance'); finish.focus();
    assert.equal(popup.hidden,false); assert.equal(popup.getAttribute('role'),'tooltip');
    assert.match(popup.textContent,/privaten Entwurf/); assert.equal(finish.hasAttribute('title'),false);
    assert.equal(finish.getAttribute('aria-describedby'),'existing-guidance iss-editorial-help-popup');
    finish.dispatchEvent(new e.window.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
    assert.equal(popup.hidden,true); assert.equal(e.window.document.activeElement,finish);
    assert.equal(finish.getAttribute('aria-describedby'),'existing-guidance');
    const help = e.root.querySelector('.iss-editorial-studio__panel-head > .iss-editorial-help-button'); help.click();
    assert.equal(popup.getAttribute('role'),'dialog'); assert.equal(help.getAttribute('aria-expanded'),'true');
    e.button('Schließen',popup).click();
    assert.equal(popup.hidden,true); assert.equal(e.window.document.activeElement,help);
    help.click(); e.root.dispatchEvent(new e.window.Event('pointerdown',{bubbles:true}));
    assert.equal(popup.hidden,true); assert.equal(help.getAttribute('aria-expanded'),'false');
  } finally { e.dom.window.close(); }
});

for (const [format, railControl, visible] of [['projekt', true, true], ['renamed_owner', true, true], ['projekt', false, false], ['landing', undefined, false]]) {
  test(`${format}: rail availability follows the owner capability and preserves hidden feature values`, async () => {
    const rail = {enabled:true,placement:'bottom',mode:'section-index',treatment:'overlay'};
    const document = {schema_version:3,skin:'standard',features:{rail},sections:[{type:'kapitel',title:'Retained chapter',body:'<p>Retained prose.</p>',links:[]}],deleted_sections:[{type:'retired',opaque:{keep:'Exact'}}]};
    const e = await editor(format, {railControl, document});
    try {
      const control = e.root.querySelector('.iss-editorial-rail-feature-control');
      assert.equal(!!control, visible);
      assert.deepEqual(e.value(), document, 'Opening the owner settings never authors defaults or drops hidden values');
      if (visible) {
        const placement = control.querySelector('[aria-label="Position der Lesenavigation"]');
        const treatment = control.querySelector('[aria-label="Darstellung der Lesenavigation"]');
        assert.equal(placement.value, 'bottom'); assert.equal(treatment.value, 'overlay');
        placement.value = 'top'; placement.dispatchEvent(new e.window.Event('change', {bubbles:true}));
        treatment.value = 'line'; treatment.dispatchEvent(new e.window.Event('change', {bubbles:true}));
        await e.settle();
        assert.deepEqual(JSON.parse(e.requests.at(-1).get('document')).features.rail, {...rail,placement:'top',treatment:'line'});
        assert.deepEqual(e.value().sections, document.sections); assert.deepEqual(e.value().deleted_sections, document.deleted_sections);
      } else {
        e.input(e.window.document.getElementById('title'), 'Unrelated title edit'); await e.settle();
        assert.deepEqual(JSON.parse(e.requests.at(-1).get('document')).features.rail, rail);
      }
    } finally {e.dom.window.close();}
  });
}

test('Rail toggle updates dependent controls and retains choices when switched off and on', async () => {
  const document = {schema_version:3,skin:'dossier',features:{},sections:[{type:'kapitel',title:'Retained chapter'}]};
  const e = await editor('projekt', {railControl:true, railPlacementBySkin:{dossier:'horizontal'}, skins:[{slug:'dossier',label:'Dossier'}], document});
  try {
    const control = e.root.querySelector('.iss-editorial-rail-feature-control');
    const enabled = control.querySelector('input[type="checkbox"]');
    const placement = control.querySelector('[aria-label="Position der Lesenavigation"]');
    const treatment = control.querySelector('[aria-label="Darstellung der Lesenavigation"]');
    assert.equal(enabled.checked,false); assert.equal(placement.disabled,true); assert.equal(treatment.disabled,true);
    assert.equal(placement.value,'horizontal'); assert.equal(treatment.value,'quiet');
    assert.deepEqual(e.value().features,{},'Showing defaults does not write them');
    enabled.click();
    assert.equal(placement.disabled,false); assert.equal(treatment.disabled,false);
    placement.value='bottom'; placement.dispatchEvent(new e.window.Event('change',{bubbles:true}));
    treatment.value='card'; treatment.dispatchEvent(new e.window.Event('change',{bubbles:true}));
    enabled.click();
    assert.equal(placement.disabled,true); assert.equal(treatment.disabled,true);
    assert.deepEqual(e.value().features.rail,{enabled:false,placement:'bottom',mode:'contextual',treatment:'card'});
    enabled.click(); await e.settle();
    assert.equal(placement.disabled,false); assert.equal(treatment.disabled,false);
    assert.deepEqual(JSON.parse(e.requests.at(-1).get('document')).features.rail,{enabled:true,placement:'bottom',mode:'contextual',treatment:'card'});
  } finally {e.dom.window.close();}
});

test('Recovered rail values hydrate the owner controls and reach native submit unchanged', async () => {
  const recovered = {schema_version:3,skin:'standard',features:{rail:{enabled:true,placement:'left',mode:'anchor-nav',treatment:'sticky'}},sections:[{type:'kapitel',title:'Recovered chapter',body:'<p>Recovered prose.</p>',links:[]}],deleted_sections:[{type:'retired',opaque:{keep:'Exact'}}]};
  const e = await editor('renamed_owner', {railControl:true, recovery:{base:'base',modified:'Today',document:recovered}});
  try {
    assert.equal(e.root.querySelector('.iss-editorial-rail-feature-control'), null, 'Unresolved recovery does not expose stale controls');
    e.button('Entwurf weiterbearbeiten').click(); await e.settle();
    const control = e.root.querySelector('.iss-editorial-rail-feature-control');
    assert.equal(control.querySelector('input').checked, true);
    assert.equal(control.querySelector('[aria-label="Position der Lesenavigation"]').value, 'left');
    assert.equal(control.querySelector('[aria-label="Darstellung der Lesenavigation"]').value, 'sticky');
    let submitted = false;
    e.window.document.getElementById('post').requestSubmit = () => {submitted = true;};
    e.button('Update').click(); await e.settle();
    assert.equal(submitted,true); assert.equal(e.requests.at(-1).get('intent'),'submit');
    const payload = JSON.parse(e.requests.at(-1).get('document'));
    assert.deepEqual(payload.features,recovered.features); assert.deepEqual(payload.sections,recovered.sections); assert.deepEqual(payload.deleted_sections,recovered.deleted_sections);
  } finally {e.dom.window.close();}
});

for (const [type, treatment, fields, kind = 'text_image'] of [
  ['renamed_rows', 'renamed.agenda', ['eyebrow','group','detail']],
  ['text_bild_reihe', 'text-bild-reihe.agenda', []],
  ['renamed_rows', 'renamed.editorial', ['eyebrow']],
  ['renamed_rows', 'renamed.group', ['group']],
  ['renamed_rows', 'renamed.detail', ['detail']],
  ['text_bild_reihe', 'text-bild-reihe.agenda', ['eyebrow','group','detail'], 'cards']
]) {
  test(`${type}.${treatment} ${kind}: row extras follow declared fields and retain hidden values`, async () => {
    const item = {label:'Retained row',text:'Retained prose.',eyebrow:'2026',group:'Workshop',detail:'Retained detail.',media_refs:[]};
    const document = {schema_version:3,skin:'standard',sections:[{type,treatment,items:[item]}],deleted_sections:[{type:'retired',opaque:'Keep'}]};
    const e = await editor('renamed_owner', {document, pageChoices:[], sections:{[type]:{label:'Rows',supports:['items'],items_kind:kind,treatments:[{slug:treatment,label:'Rows',item_fields:fields}]}}});
    try {
      const extras = e.root.querySelector('.iss-editorial-gateway-item-row__fields details');
      const expected = kind === 'text_image' ? fields : [];
      assert.equal(!!extras, expected.length > 0);
      if (extras) {
        assert.deepEqual([...extras.querySelectorAll('input,textarea')].map(node=>node.value), ['eyebrow','group','detail'].filter(field=>expected.includes(field)).map(field=>item[field]));
        const inputs = [...extras.querySelectorAll('input,textarea')];
        for (const input of inputs) { e.input(input, input.value + ' edited'); }
      } else { e.input(e.window.document.getElementById('title'), 'Unrelated title edit'); }
      await e.settle();
      const expectedItem = {...item}; for (const field of expected) { expectedItem[field] += ' edited'; }
      assert.deepEqual(e.value().sections[0].items[0], expectedItem);
      assert.deepEqual(JSON.parse(e.requests.at(-1).get('document')).sections[0].items[0], expectedItem);
      assert.deepEqual(e.value().deleted_sections, document.deleted_sections);
    } finally { e.dom.window.close(); }
  });
}

test('Row extras survive treatment switching, private recovery and native submit', async () => {
  const document = {schema_version:3,skin:'standard',sections:[{type:'rows',treatment:'rows.agenda',items:[{label:'Row',text:'Prose',eyebrow:'2026',group:'Group',detail:'Exact detail',media_refs:[]}]}],deleted_sections:[{type:'retired',opaque:'Exact'}]};
  const sections = {rows:{label:'Rows',supports:['items','treatment'],items_kind:'text_image',default_treatment:'rows.visual',treatments:[{slug:'rows.visual',label:'Visual',item_fields:[]},{slug:'rows.editorial',label:'Editorial',item_fields:['eyebrow']},{slug:'rows.agenda',label:'Agenda',item_fields:['eyebrow','group','detail']}]}};
  const e = await editor('renamed_owner', {sections, recovery:{base:'base',modified:'Today',document}});
  try {
    e.button('Entwurf weiterbearbeiten').click(); await e.settle();
    const controls = () => e.root.querySelectorAll('.iss-editorial-gateway-item-row__fields details input,.iss-editorial-gateway-item-row__fields details textarea');
    assert.equal(controls().length,3);
    for (const [treatment,count] of [['rows.visual',0],['rows.editorial',1],['rows.agenda',3]]) {
      e.button('Darstellung').click();
      e.root.querySelector(`.iss-editorial-field--treatment input[value="${treatment}"]`).click();
      e.root.querySelector('.iss-editorial-outline__select').click();
      assert.equal(controls().length,count);
      assert.deepEqual(e.value().sections[0].items,document.sections[0].items,'Hidden fields stay in the document');
    }
    let submitted = false; e.window.document.getElementById('post').requestSubmit = () => {submitted=true;};
    e.button('Update').click(); await e.settle();
    assert.equal(submitted,true); assert.equal(e.requests.at(-1).get('intent'),'submit');
    const payload=JSON.parse(e.requests.at(-1).get('document'));
    assert.deepEqual(payload.sections,document.sections); assert.deepEqual(payload.deleted_sections,document.deleted_sections);
  } finally { e.dom.window.close(); }
});

for (const failure of ['', 'second-page', 'changing-source']) {
  test('Album import consumes all pages atomically: ' + (failure || '241 items'), async () => {
    const original = [{source_kind:'wp_media',source_id:'999',visible:true,label:'Existing',position:1}];
    const e = await editor('renamed_album_owner', {contentRestRoot:'/wp-json/iss-content/v1', sections:{album:{label:'Album',supports:['album_source','sheets']}}, document:{schema_version:3,sections:[{type:'album',album_source:{kind:'editorial_set',set_id:'7'},sheets:original}]}});
    try {
      const normalFetch=e.window.fetch, pages=[];
      e.window.fetch=async (url, options)=>{
        if(options.body) return normalFetch(url, options);
        const page=Number(new URL(url,'http://localhost').searchParams.get('page'));pages.push(page);
        if(failure==='second-page' && page===2) return {ok:false};
        const count=page<3?120:1;
        return {ok:true,json:async()=>({page,total:failure==='changing-source'&&page===2?240:241,perPage:120,items:Array.from({length:count},(_,i)=>({id:(page-1)*120+i+1,sourceId:(page-1)*120+i+1000,setId:7,kind:'wp_media',status:'approved',preview:{mime:'image/jpeg'}}))})};
      };
      e.button('Aus Quelle importieren / synchronisieren').click(); await e.settle();
      if(failure) {
        assert.deepEqual(e.value().sections[0].sheets,original,'No partial merge after a later-page failure/change');
        assert.match(e.root.textContent,/Set konnte nicht geladen werden/);
      } else {
        assert.deepEqual(pages,[1,2,3]);
        assert.equal(e.value().sections[0].sheets.filter(s=>s.source_set_id==='7').length,241);
        assert.match(e.root.textContent,/241 Bild\(er\) importiert/);
      }
    } finally {e.dom.window.close();}
  });
}

test('Renamed route owner and map use declared capabilities', async()=>{
  const e=await editor('renamed_route',{...workspaceOptions(),routeStations:{enabled:true},sections:{map:{label:'Map',supports:[],editor:{route_stations:true,title_from_post:true,title_hint:'Owner title hint'}}},document:{schema_version:3,sections:[{type:'map'}]}});
  try {
    assert.match(e.root.textContent,/Native route stations/);
    e.root.querySelector('.iss-editorial-outline__select').click();
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea').placeholder,'Page title');
    assert.match(e.root.textContent,/Owner title hint/);
    assert.ok(e.button('Route / Stationen bearbeiten'));
  } finally {e.dom.window.close();}
});

test('Renamed contextual title uses the nearest preceding declared section and retains hidden question',async()=>{
  const e=await editor('renamed_dossier',{sections:{phase:{label:'Phase',supports:['contribution_question'],editor:{hidden_fields:['contribution_question']}},invite:{label:'Invite',supports:['no_body'],editor:{title_context:{preceding_type:'phase',placeholder:'Automatic',hint:'Context: {title}',fallback_title:'Previous',missing_hint:'Insert after a phase'}}}},document:{schema_version:3,sections:[{type:'phase',title:'Earlier',contribution_question:'Retain hidden'},{type:'phase',title:'Nearest'},{type:'invite'}]}});
  try {
    assert.doesNotMatch(e.root.textContent,/Eigene Forschungsfrage/);
    assert.equal(e.value().sections[0].contribution_question,'Retain hidden');
    [...e.root.querySelectorAll('.iss-editorial-outline__select')].at(-1).click();
    assert.match(e.root.textContent,/Context: Nearest/);
    assert.equal(e.root.querySelector('.iss-editorial-field--title textarea').placeholder,'Automatic');
  } finally {e.dom.window.close();}
});

test('Legacy summaries and rich text share one control-aware href policy',async()=>{
  const e=await editor();
  try {
    const sanitize=e.window.issEditorialRichText.sanitize;
    assert.equal(sanitize('<p><a href="java&#x09;script:bad()">Keep</a> <strong>bold</strong></p>','basic'),'<p>Keep <strong>bold</strong></p>');
    assert.equal(sanitize('<a href="/safe/" target="_blank">Safe</a>','basic'),'<a href="/safe/">Safe</a>');
    assert.equal(sanitize('<p><span class="iss-ink-123abc">Legacy</span></p>','basic'),'<p>Legacy</p>');
  } finally {e.dom.window.close();}
});

for (const field of ['quote_treatment', 'section_treatment', 'gallery_layout']) {
  test(`${field}: registered chooser adapts legacy state without hydration writes and retains bounded options`, async () => {
    const section = {type:'renamed_gesture', [field]:'source', orientation:'media-right', title:'Keep title', quote:'Keep quotation', attribution:'Keep source', media_refs:[], object_refs:[]};
    const e = await editor('renamed_owner', {skins:[{slug:'standard',label:'Standard'},{slug:'exhibition',label:'Exhibition'}], document:{schema_version:3,skin:'exhibition',sections:[section]}, sections:{renamed_gesture:{label:'Shared gesture',supports:['quote','treatment',field,'orientation'],legacy_treatment_field:field,default_treatment:'voice.pull',treatments:[{slug:'voice.pull',label:'Plain',legacy_value:'pull'},{slug:'voice.source',label:'Source',legacy_value:'source'},{slug:'voice.station',label:'Station',legacy_value:'source'}],treatment_contexts:{standard:{allowed:['voice.pull','voice.source'],default:'voice.pull'},exhibition:{allowed:['voice.pull','voice.source','voice.station'],default:'voice.station',legacy:{source:'voice.station'}}}}}});
    assert.equal(e.root.querySelector('.iss-editorial-treatment-choice input:checked').value,'voice.station');
    assert.equal(e.value().sections[0].treatment,undefined);
    assert.equal(e.value().sections[0][field],'source');
    const plain=e.root.querySelector('.iss-editorial-treatment-choice input[value="voice.pull"]');
    plain.checked=true; plain.dispatchEvent(new e.window.Event('change',{bubbles:true}));
    assert.equal(e.value().sections[0].treatment,'voice.pull');
    assert.equal(e.value().sections[0][field],'pull');
    for(const key of ['orientation','title','quote','attribution']) assert.equal(e.value().sections[0][key],section[key]);
    assert.equal(e.root.querySelectorAll('.iss-editorial-field--quote-treatment, .iss-editorial-field--section-treatment, .iss-editorial-field--gallery-layout').length,0);
    e.dom.window.close();
  });
}

test('Treatment context changes refresh choices while retaining an explicit existing treatment', async () => {
  const e = await editor('ausstellung', {skins:[{slug:'standard',label:'Standard'},{slug:'exhibition',label:'Exhibition'}],document:{schema_version:3,skin:'exhibition',sections:[{type:'voice',title:'Keep',treatment:'voice.station',orientation:'media-right'}]},sections:{voice:{label:'Voice',supports:['treatment'],default_treatment:'voice.pull',treatments:[{slug:'voice.pull',label:'Plain'},{slug:'voice.station',label:'Station'}],treatment_contexts:{standard:{allowed:['voice.pull'],default:'voice.pull'},exhibition:{allowed:['voice.pull','voice.station'],default:'voice.station'}}}}});
  const select=[...e.root.querySelectorAll('select')].find(n=>[...n.options].some(o=>o.value==='exhibition'));
  assert.ok(select);
  select.value='standard'; select.dispatchEvent(new e.window.Event('change',{bubbles:true}));
  await e.settle();
  assert.equal(e.value().sections[0].treatment,'voice.station');
  assert.equal(e.value().sections[0].orientation,'media-right');
  assert.equal(e.root.querySelectorAll('.iss-editorial-treatment-choice').length,1);
  assert.match(e.root.textContent,/gespeicherte Darstellung bleibt erhalten/);
  e.dom.window.close();
});


test('Canonical gallery treatment controls the media picker without a legacy field', async () => {
  const e = await editor('projekt', {document:{schema_version:3,skin:'standard',sections:[{type:'galerie',title:'Gallery',treatment:'galerie.viewport',media_refs:[]}]},sections:{galerie:{label:'Gallery',supports:['treatment','gallery_layout','media_refs'],legacy_treatment_field:'gallery_layout',default_treatment:'galerie.grid',treatments:[{slug:'galerie.grid',label:'Grid',legacy_value:'grid'},{slug:'galerie.viewport',label:'Viewport',legacy_value:'viewport'}]}}});
  try {
    assert.match(e.root.textContent,/Vollbild verwendet genau ein 16:9-Bild/);
    assert.equal(e.value().sections[0].gallery_layout,undefined);
    const grid=e.root.querySelector('.iss-editorial-treatment-choice input[value="galerie.grid"]');
    grid.checked=true; grid.dispatchEvent(new e.window.Event('change',{bubbles:true}));
    assert.doesNotMatch(e.root.textContent,/Vollbild verwendet genau ein 16:9-Bild/);
    assert.equal(e.value().sections[0].gallery_layout,'grid');
  } finally { e.dom.window.close(); }
});
