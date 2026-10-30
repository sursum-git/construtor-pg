
import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.setContent('<div id="root"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/page-engine/PageDefinitionNormalizer.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudConfigLoader.js','src/crud-engine/CrudDefinitionLoader.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudLayoutManager.js','src/crud-engine/CrudToolbarRenderer.js','src/crud-engine/CrudFilterRenderer.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudKendoFormRenderer.js','src/crud-engine/CrudEngine.js'])await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async()=>{
  const definition={schemaVersion:'1.0',pageType:'crud',id:'context-edit',module:'tests',entity:'records',title:'Registro',permissions:{read:true,create:false,edit:true,delete:true},api:{read:{url:'/read'},get:{url:'/get',method:'POST'},update:{url:'/update',method:'POST'},delete:{url:'/delete',method:'POST'}},dataModel:{primaryKey:'id',fields:{id:{type:'integer',label:'ID',readonly:true},name:{type:'string',label:'Nome',editable:true}}},contextFields:[{field:'id',required:true,readonly:true,filter:true}],query:{defaultPageSize:20},grid:{columns:[{field:'id',title:'ID'}]},form:{id:'context-edit',mode:'embedded',openOnLoadField:'id',returnScreenId:'ciw.records',layout:'sections',sections:[{id:'main',fields:[{field:'id'},{field:'name'}]}],fields:[{field:'id'},{field:'name'}]}};
  const notices=[],calls=[];CrudUtils.showMessage=(message)=>notices.push(message);let stored={id:17,name:'Antes'},deleted=false;
  const client={request:async req=>{calls.push(req.url);if(req.url==='/get'){if(deleted)throw new Error('Registro inexistente');return {...stored};}if(req.url==='/update'){stored={id:17,name:req.data.name};return {...stored};}if(req.url==='/delete'){deleted=true;return {success:true};}return {};}};
  const engine=await new CrudEngine({root:'#root',definition,config:{},contextValues:{id:'17'},httpClient:client,runtimeMessages:false}).init();
  await engine.openRecord('edit',17);engine.formRenderer.inputs.name.input.val('Depois');engine.formRenderer.save();
  for(let i=0;i<20&&(!calls.includes('/update')||engine.formRenderer.mode!=='view');i++)await new Promise(r=>setTimeout(r,10));
  await new Promise(r=>setTimeout(r,20));
  const saved={name:engine.formRenderer.data.name,forms:$('#root .crud-form').length,mode:engine.formRenderer.mode,requests:[...calls],ready:$('#root').attr('data-render-state')};
  await engine.refresh();
  const getCount=calls.filter(x=>x==='/get').length;
  const removed=await engine.deleteRecord(17,{confirm:false,skipRecordPreparation:true,record:{id:17,name:'Depois'}});
  await new Promise(r=>setTimeout(r,20));
  const postDelete={removed,getCount:calls.filter(x=>x==='/get').length,priorGetCount:getCount,forms:$('#root .crud-form').length,text:$('#root').text(),back:$('#root a').last().attr('href'),ready:$('#root').attr('data-render-state')};
  await engine.refresh();postDelete.getAfterRefresh=calls.filter(x=>x==='/get').length;
  $('#root').empty();let reads=0,writes=0;
  const failingClient={request:async req=>{
    if(req.url==='/get'){if(++reads>2)throw new Error('Falha de releitura');return {id:17,name:'Antes'};}
    if(req.url==='/update'){writes++;return {id:17,name:'Salvo'};}return {};
  }};
  const failing=await new CrudEngine({root:'#root',definition,config:{},contextValues:{id:'17'},httpClient:failingClient,runtimeMessages:false}).init();
  await failing.openRecord('edit',17);failing.formRenderer.inputs.name.input.val('Salvo');failing.formRenderer.save();
  await new Promise(r=>setTimeout(r,40));
  const refreshFailure={reads,writes,ready:$('#root').attr('data-render-state'),text:$('#root').text()};
  return {saved,postDelete,notices,refreshFailure};
 });
 assert.equal(result.saved.name,'Depois');assert.equal(result.saved.mode,'view');assert.equal(result.saved.forms,1);assert.equal(result.saved.ready,'ready');assert.ok(result.saved.requests.includes('/update'));assert.equal(result.saved.requests.filter(x=>x==='/get').length,3);
 assert.equal(result.postDelete.removed,true);assert.equal(result.postDelete.getCount,result.postDelete.priorGetCount);assert.equal(result.postDelete.getAfterRefresh,result.postDelete.priorGetCount);assert.equal(result.postDelete.forms,0);assert.equal(result.postDelete.ready,'ready');assert.equal(result.postDelete.back,'../production/app.html?screenId=ciw.records&restoreFrom=context-edit');assert.match(result.postDelete.text,/exclu/i);assert.ok(!result.notices.some(x=>/Erro ao salvar|Erro ao excluir/.test(x)));assert.equal(result.refreshFailure.writes,1);assert.equal(result.refreshFailure.reads,3);assert.equal(result.refreshFailure.ready,'error');assert.match(result.refreshFailure.text,/Registro salvo/);assert.deepEqual(errors,[]);
 console.log('PASS: contextual edit commit reloads view without grid; delete returns ready state without re-reading removed record.');
}finally{await browser.close();}
