const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),assert=require('node:assert/strict');
const win={CrudUtils:{getPermission:()=>true,makeError:(code,message)=>({error:{code,message}}),unwrapError:error=>error.error||error}};
vm.runInNewContext(fs.readFileSync(path.resolve(__dirname,'../../src/crud-engine/CrudEngine.js'),'utf8'),{window:win,jQuery:()=>{throw Error("DOM not expected");}});
(async()=>{
 let fetched=0,closed=0,opened=0,messages=[];
 const engine=Object.assign(Object.create(win.CrudEngine.prototype),{
 definition:{form:{openOnLoadField:'container_id'}},contextValues:{container_id:7},apiCompanySelection:{selected:null,config:{endpointModes:{get:{mode:'record',operation:'get'}}}},
 formRenderer:{close:()=>closed++,open:()=>opened++,optionsReady:Promise.resolve()},releaseRuntimeLock:()=>{},updateLastUpdated:()=>{},
 fetchRecord:async()=>{fetched++;return {container_id:7};},showApiCompanyContextMessage:message=>messages.push(message)
 });
 await engine.openContextualForm();assert.equal(fetched,0);assert.equal(opened,0);assert.ok(messages.length);
 engine.apiCompanySelection.selected={key:'med',selectionRevision:2};await engine.applyApiCompanySelection();assert.equal(closed,1);assert.equal(fetched,1);assert.equal(opened,1);
 engine.fetchRecord=async()=>{throw {error:{code:'API_COMPANY_FORBIDDEN',message:'Registro pertence a outra empresa.'}};};
 await engine.applyApiCompanySelection();assert.ok(closed>=2);assert.equal(opened,1);assert.ok(messages.some(message=>String(message).includes('outra empresa')));
 const pending=[];engine.fetchRecord=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}));
 const old=engine.openContextualForm();const next=engine.applyApiCompanySelection();
 pending[1].resolve({container_id:7,company:'new'});await next;
 const closesAfterNew=closed;pending[0].reject({error:{code:'API_COMPANY_CONTEXT_CHANGED',message:'Resposta antiga'}});await old;
 assert.equal(closed,closesAfterNew);assert.ok(!messages.includes('Resposta antiga'));
 console.log('PASS contextual API form waits for company, reloads on selection, keeps selector on scope errors.');
})().catch(error=>{console.error(error);process.exitCode=1;});


