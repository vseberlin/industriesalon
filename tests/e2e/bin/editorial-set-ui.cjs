/** Interaction regressions for reviewed Set references, pagination and signed guest links. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const settle = () => new Promise(resolve => setTimeout(resolve, 15));

test('Set picker rejects unreviewed attachments, retains provenance, and pages through Sets and items', async () => {
  const dom = new JSDOM('<div id="picker"></div>', {runScripts:'outside-only', pretendToBeVisual:true});
  const {window} = dom;
  const sets = Array.from({length:31}, (_, i) => ({id:i+1,title:'Set '+(i+1)}));
  const entry = (id,status) => ({id,kind:'wp_media',source:'wp-media',sourceId:String(id+100),status,label:'File '+id,preview:{mime:'image/jpeg'}});
  const paths = [];
  window.wp = {apiFetch: async ({path}) => {
    paths.push(path); const url = new URL(path,'http://localhost'); const page = Number(url.searchParams.get('page') || 1);
    return path.includes('/editorial-set-items') ? {items:page===1?[entry(1,'rejected'),entry(2,'pending'),entry(3,'approved')]:[entry(4,'promoted')],total:61}
      : {items:sets.slice((page-1)*30,page*30),total:31};
  }};
  window.eval(fs.readFileSync('plugins/iss-editorial/assets/set-media-picker.js','utf8'));
  const root = window.document.getElementById('picker');
  const picker = window.issEditorialSetMediaPicker.create(root, {mediaType:'image'});
  await settle();
  const cards = root.querySelectorAll('.iss-archive-object-picker__item');
  assert.equal(cards.length,3);
  assert.equal(cards[0].querySelector('button').disabled,true);
  assert.equal(cards[1].querySelector('button').disabled,true);
  cards[2].querySelector('button').click();
  assert.equal(picker.getSelectedMedia()[0].editorial_set_item_id,'3');
  assert.equal(picker.getSelectedMedia()[0].id,'103');
  const more = Array.from(root.querySelectorAll('button')).find(b=>b.textContent.includes('Weitere Sets'));
  assert.ok(more,'Set pagination survives initial item selection'); more.click(); await settle();
  assert.ok(root.textContent.includes('Set 31'));
  const next = Array.from(root.querySelectorAll('.iss-archive-object-picker__pager button')).find(b=>b.textContent==='Weiter');
  next.click(); await settle();
  assert.ok(root.textContent.includes('File 4'));
  root.querySelector('.iss-archive-object-picker__item button').click();
  assert.equal(picker.getSelectedMedia().length,2,'Selection survives item page changes');
  assert.ok(paths.some(path=>path.includes('/editorial-sets?') && path.includes('page=2')));
  picker.close(); dom.window.close();
});

test('Guest form accepts a signed context link while retaining rights and consent checks', () => {
  const php = fs.readFileSync('ops/event-drop/interface/index.php','utf8');
  const body = php.match(/function validateMeta\(meta\) \{([\s\S]*?)\n        \}/)[1];
  const validate = new Function('meta', body);
  const meta = {participant_id:'Guest',event:'projekt__fixture',attribution:'Photographer',consent:'1',context:'42',key:'signed-test-key',code:'',token:''};
  assert.deepEqual(validate(meta),[]);
  assert.ok(validate({...meta,consent:''}).length);
  assert.ok(validate({...meta,key:''}).length);
  assert.ok(validate({...meta,context:''}).length);
});
