
import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage(); const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<div id="root"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/page-engine/PageDefinitionNormalizer.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudConfigLoader.js','src/crud-engine/CrudDefinitionLoader.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudLayoutManager.js','src/crud-engine/CrudToolbarRenderer.js','src/crud-engine/CrudFilterRenderer.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudKendoFormRenderer.js','src/crud-engine/CrudEngine.js']) await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async()=>{
  const calls=[];
  const definition={schemaVersion:'1.0',pageType:'crud',id:'context-form',module:'tests',entity:'records',title:'Painel',permissions:{read:true,create:false,edit:false,delete:false},api:{read:{url:'/read'},get:{url:'/get',method:'POST'}},dataModel:{primaryKey:'container_id',fields:{container_id:{type:'integer',label:'Container',readonly:true},name:{type:'string',label:'Nome',readonly:true}}},contextFields:[{field:'container_id',filter:true,readonly:true,required:true}],query:{defaultPageSize:20},grid:{columns:[{field:'container_id',title:'Container'}]},form:{id:'context-form',mode:'embedded',returnScreenId:'ciw.containers',openOnLoadField:'container_id',layout:'tabs',fields:[{field:'container_id'},{field:'name'}],tabs:[{id:'dados',title:'Dados',sections:[{id:'main',fields:[{field:'container_id'},{field:'name'}]}]}]}};
  const client={request:async req=>{calls.push(req);return {container_id:req.data.container_id,name:'Container real selecionado'};}};
  const make=(context,def=definition)=>new CrudEngine({root:'#root',definition:def,config:{},contextValues:context,httpClient:client,runtimeMessages:false});
  const engine=await make({container_id:'17'}).init().catch(e=>{throw new Error(JSON.stringify(e.payload||e));});
  const state={calls:calls.map(c=>({url:c.url,id:c.data.container_id,context:c.data.context})),forms:$('#root .crud-form').length,grids:$('#root .k-grid').length,windows:$('.k-window').length,mode:engine.formRenderer.mode,name:engine.formRenderer.data.name,ready:$('#root').attr('data-render-state'),back:$('#root .crud-form-footer-appbar a').attr('href'),backLabel:$('#root .crud-form-footer-appbar a').text(),navButtons:$('#root .crud-form-nav-button').length};
  let destroyed=0;engine.formRenderer.linkedPageEngines.previous={engine:{destroy:()=>{destroyed++;}}};await engine.refresh();state.refreshed=calls.length;state.destroyed=destroyed;
  const rejects=[];
  for(const context of [{},{container_id:'1e2'},{container_id:'2147483648'}]) {try{await make(context).init();rejects.push(false);}catch{rejects.push(true);}}
  state.rejects=rejects;state.requestsAfterInvalid=calls.length;
  const mismatch=make({container_id:'17'});mismatch.httpClient={request:async()=>({container_id:18,name:'wrong'})};try{await mismatch.init();state.mismatch=false;}catch{state.mismatch=true;}
  const denied=CrudUtils.clone(definition);denied.permissions.read=false;
  try{await make({container_id:'17'},denied).init();state.denied=false;}catch{state.denied=true;}
  state.requestsAfterDenied=calls.length;
  return state;
 });
 assert.equal(result.back,'../production/app.html?screenId=ciw.containers&restoreFrom=context-form');assert.equal(result.backLabel,'Voltar');assert.equal(result.navButtons,0);assert.equal(result.destroyed,1);assert.equal(result.forms,1);assert.equal(result.grids,0);assert.equal(result.windows,0);assert.equal(result.mode,'view');assert.equal(result.name,'Container real selecionado');assert.equal(result.ready,'ready');
 assert.deepEqual(result.calls,[{url:'/get',id:17,context:{container_id:17}}]);assert.equal(result.refreshed,2);assert.deepEqual(result.rejects,[true,true,true]);assert.equal(result.requestsAfterInvalid,2);assert.equal(result.mismatch,true);assert.equal(result.denied,true);assert.equal(result.requestsAfterDenied,2);assert.deepEqual(errors,[]);
 console.log('PASS: contextual embedded view calls get only; refresh, invalid/missing context, mismatched record and denied read.');
}finally{await browser.close();}
