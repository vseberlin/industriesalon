const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const {JSDOM}=require('jsdom');
const settle=()=>new Promise(resolve=>setTimeout(resolve,20));
async function setup() {
  const dom=new JSDOM('<div id="route"></div>',{url:'http://localhost/wp-admin/post.php',runScripts:'outside-only'});
  const {window}=dom;
  window.eval(fs.readFileSync('plugins/iss-core/assets/admin-request.js','utf8'));
  window.eval(fs.readFileSync('plugins/iss-relations/assets/route-station-editor.js','utf8'));
  const calls=[];let gate=null;let failed=false;
  window.fetch=async(_url,request={})=>{
    if(request.method!=='POST'){return {ok:true,json:async()=>({success:true,data:{objects:[],stories:[]}})};}
    const body=JSON.parse(request.body);calls.push(body);
    if(gate){const pending=gate;gate=null;await pending;}
    return {ok:!failed,json:async()=>failed?{code:'iss_relations_route_conflict',message:'Route changed on server'}:{ok:true,locked:false,canonical:[],canonicalHash:'base',draftToken:'draft-'+calls.length,relations:body.relations||[],trash:body.trash||[],draft:{base_hash:'base'},previewArgs:{}}};
  };
  const editor=window.issRelationsRouteStations.create({postId:99,config:{postId:99,locked:false,canonicalHash:'base',draftToken:'draft-0',restRoot:'/wp-json/iss-relations/v1',nonce:'test',choiceNonce:'test',ajaxUrl:'/wp-admin/admin-ajax.php',relations:[{place_id:1,role:'stop',route_title:'Original'}],places:[{id:1,title:'Place'}]}});
  const mount=window.document.getElementById('route');editor.render(mount);await settle();
  const input=value=>{const field=[...mount.querySelectorAll('label')].find(e=>e.textContent==='Stations-Titel').querySelector('input');field.value=value;field.dispatchEvent(new window.Event('input',{bubbles:true}));};
  return {dom,window,mount,editor,calls,input,block:promise=>{gate=promise;},fail:value=>{failed=value;}};
}
test('Route edits made during a slow save survive and are saved with the returned version',async()=>{
  const e=await setup();
  try{
    let release;e.block(new Promise(resolve=>{release=resolve;}));
    e.input('First edit');const save=e.editor.saveIfDirty();await settle();
    e.input('Newer edit');release();await save;
    assert.equal(e.calls.length,2);
    assert.equal(e.calls[0].relations[0].route_title,'First edit');
    assert.equal(e.calls[1].relations[0].route_title,'Newer edit');
    assert.equal(e.calls[1].draftToken,'draft-1');
    assert.equal(e.editor.recoveryCopy().relations[0].route_title,'Newer edit');
    assert.equal(e.editor.isDirty(),false);
  }finally{e.dom.window.close();}
});
test('Route conflicts stay visible and preserve unsaved work and unload protection',async()=>{
  const e=await setup();
  try{
    e.input('Keep local station');e.fail(true);
    await assert.rejects(e.editor.saveIfDirty(),/Route changed/);
    assert.equal(e.mount.querySelector('[role="alert"]').hidden,false);
    assert.equal(e.editor.recoveryCopy().relations[0].route_title,'Keep local station');
    const event=new e.window.Event('beforeunload',{cancelable:true});e.window.dispatchEvent(event);assert.equal(event.defaultPrevented,true);
    e.fail(false);await e.editor.saveIfDirty();
    assert.equal(e.mount.querySelector('[role="alert"]').hidden,true);
  }finally{e.dom.window.close();}
});
test('Incomplete route stations cannot silently disappear on save',async()=>{
  const e=await setup();
  try{
    [...e.mount.querySelectorAll('button')].find(b=>b.textContent==='Station hinzufügen').click();
    await assert.rejects(e.editor.saveIfDirty(),/einen Ort wählen/);
    assert.equal(e.calls.length,0);
    assert.equal(e.editor.recoveryCopy().relations.length,2);
    assert.match(e.mount.querySelector('[role="alert"]').textContent,/unvollständige Station/);
  }finally{e.dom.window.close();}
});
