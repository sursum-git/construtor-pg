import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';
const browser=await chromium.launch({headless:true,channel:'msedge'});
try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setContent('<div id="root"></div>');
  for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudConfigLoader.js','src/process-engine/ProcessDefinitionLoader.js','src/process-engine/ProcessDefinitionValidator.js','src/process-engine/ProcessEngine.js','src/crud-engine/CrudEngine.js']){await page.addScriptTag({path:path.resolve(file)});}
  const result=await page.evaluate(async()=>{
    let calls=0;
    const definition={schemaVersion:'1.0',pageType:'process',screenId:'ciw.container-versoes-gerar',program:{entity:'container_processos_progress',title:'Gerar versão'},permissions:{read:true,process:true},contextFields:[{field:'container_id',required:true,readonly:true}],logs:{enabled:true,mode:'recordEvents',screenId:'ciw.container-painel',contextField:'container_id',title:'Logs'},dataSource:{api:{process:{url:'/run',method:'POST'}}},process:{parameters:{fields:[{field:"container_id",type:"integer",required:true,readonly:true}]},wait:{mode:'none'},result:{type:'message'}}};
    const make=(def=definition)=>new ProcessEngine({root:'#root',definition:def,config:{},contextValues:{container_id:'17'},httpClient:{request:async()=>{calls++;return {};}}});
    const engine=await make().init();
    const count=$('#root .process-log-button').length;$('#root .process-log-button').trigger('click');
    const url=$('.crud-log-frame').attr('src')||'';const windows=$('.crud-log-window-content').length;
    $('.crud-log-window-content').closest('.k-window-content').data('kendoWindow')?.close();engine.destroy();
    const denied=CrudUtils.clone(definition);denied.logs.enabled=false;await make(denied).init();const deniedCount=$('#root .process-log-button').length;
    const malformed=CrudUtils.clone(definition);malformed.logs.screenId='bad?id=x';const invalid=make(malformed);await invalid.init();const invalidCount=$('#root .process-log-button').length;invalid.destroy();
    const admin=CrudUtils.clone(definition);admin.screenId='admin.logs-transacoes';const adminEngine=await make(admin).init();const recursive=$('#root .process-log-button').length;adminEngine.destroy();
    return {count,url,windows,deniedCount,invalidCount,recursive,calls};
  });
  assert.equal(result.count,1);assert.equal(result.windows,1);assert.equal(result.calls,0);
  const query=new URL(result.url,'http://localhost/production/').searchParams;
  assert.equal(query.get('screenId'),'admin.logs-transacoes');assert.equal(query.get('context__audit_screen_id'),'ciw.container-painel');assert.equal(query.get('context__audit_record_id'),'17');
  assert.equal(result.deniedCount,0);assert.equal(result.invalidCount,0);assert.equal(result.recursive,0);assert.deepEqual(errors,[]);
  console.log('PASS: contextual Kendo Logs reuses the audit viewer, validates scope and never executes the process.');
}finally{await browser.close();}
