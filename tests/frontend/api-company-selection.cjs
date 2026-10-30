const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const sandbox = {window: {location:{href:'http://localhost/production/home.html'}}, URL};
vm.runInNewContext(fs.readFileSync(path.resolve(__dirname,'../../src/home-engine/ApiCompanySelection.js'),'utf8'),sandbox);
const Selection = sandbox.window.ApiCompanySelection;
const desc = {version:1,moduleId:'ciw',endpointModes:{read:{mode:'company',operation:'read'},update:{mode:'record',operation:'update'},options:{mode:'shared',operation:'options'}}};
const definition = {apiCompany:desc,endpoints:{read:{url:'/read'},update:{url:'/update'},options:{url:'/options'},local:{url:'/local'}}};
async function run() {
 const calls=[];let deferred;let revision=0;let allow=true;let changed=0;
 const base = {request:async req=>{calls.push(req);if(req.url.startsWith('/api/runtime/api-companies/')){
  if(req.method==='POST'){assert.equal(req.data.expectedSelectionRevision,revision);return {key:req.data.key,label:req.data.key,selectionRevision:++revision};}
  return {enabled:true,items:[{key:'a',label:'A'},{key:'b',label:'B'}],selected:revision?{key:'a',label:'A',selectionRevision:revision}:null,selectionRevision:revision};
 } if(req.data?.defer)return await new Promise(r=>{deferred=r;});return {data:[{id:1}],total:1};}};
 assert.equal(Selection.forDefinition({},base),null);
 const s=Selection.forDefinition(definition,base,{beforeChange:async()=>allow,onChange:async()=>changed++});await s.load();
 const client=s.client(base);assert.equal((await client.request({url:'/read',method:'POST'})).total,0);
 assert.equal(calls.filter(x=>x.url==='/read').length,0);
 await client.request({url:'/local',data:{value:1}});assert.equal(calls.at(-1).data.apiCompany,undefined);
 await client.request({url:'/options',method:'POST'});assert.equal(calls.at(-1).data,undefined);
 allow=false;assert.equal(await s.select('a'),false);assert.equal(revision,0);allow=true;
 await s.select('a');assert.equal(changed,1);
 await client.request({url:'/read',method:'POST',data:{context:{id:4}}});assert.equal(calls.at(-1).data.apiCompany.selectionRevision,1);
 const old=client.request({url:'/read',method:'POST',data:{defer:true}});await Promise.resolve();await s.select('b');deferred({data:[{id:'old'}]});
 await assert.rejects(old,e=>e.error.code==='API_COMPANY_CONTEXT_CHANGED');
 const sharedWrite=client.request({url:'/options',method:'POST',data:{defer:true}});await Promise.resolve();
 // Options remain a shared read; a configured shared CRUD write must still guard the visible form.
 deferred({data:[]});await sharedWrite;
 const oldMode=s.config.endpointModes.update;s.config.endpointModes.update={mode:'shared',operation:'update'};
 const sharedMutation=client.request({url:'/update',method:'POST',data:{defer:true}});await Promise.resolve();
 await assert.rejects(s.select('a'),e=>e.error.code==='API_COMPANY_BUSY');deferred({data:{id:1}});await sharedMutation;
 s.config.endpointModes.update=oldMode;
 const write=client.request({url:'/update',method:'POST',data:{defer:true}});await Promise.resolve();
 await assert.rejects(s.select('a'),e=>e.error.code==='API_COMPANY_BUSY');deferred({data:{id:1}});await write;
 let writes=0;const failing=s.client({request:async()=>{writes++;throw {error:{code:'API_COMPANY_RESULT_CONTEXT_CHANGED',details:{operationCompleted:true}}};}});
 await assert.rejects(failing.request({url:'/update',method:'POST'}));assert.equal(writes,1);
 s.destroy();await assert.rejects(client.request({url:'/read',method:'POST'}),e=>e.error.code==='API_COMPANY_CONTEXT_CHANGED');
 // A query started while the selection POST is in flight still belongs to the old selection.
 let finishSelection,finishDuringRead;
 const concurrentBase={request:request=>request.url.startsWith('/api/runtime/api-companies/')
   ? (request.method==='POST'?new Promise(resolve=>{finishSelection=resolve;}):Promise.resolve({enabled:true,items:[{key:'a',label:'A'},{key:'b',label:'B'}],selected:{key:'a',label:'A',selectionRevision:1},selectionRevision:1}))
   :new Promise(resolve=>{finishDuringRead=resolve;})};
 const concurrent=Selection.forDefinition(definition,concurrentBase);await concurrent.load();
 const switching=concurrent.select('b');await Promise.resolve();
 const during=concurrent.client(concurrentBase).request({url:'/read',method:'POST'});await Promise.resolve();
 finishSelection({key:'b',label:'B',selectionRevision:2});await switching;finishDuringRead({data:[{id:'company-a'}]});
 await assert.rejects(during,error=>error.error.code==='API_COMPANY_CONTEXT_CHANGED');
 console.log('PASS API company selection: eligibility, local/shared isolation, revision, dirty cancellation, delayed reads, busy writes, no retry.');
}
run().catch(e=>{console.error(e);process.exitCode=1;});
