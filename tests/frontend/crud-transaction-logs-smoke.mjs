import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';
import {spawnSync} from 'node:child_process';
const generated=spawnSync(process.env.PHP_BINARY||'php',['backend/tests/Runtime/fixtures/transaction-log-definitions.php'],{encoding:'utf8'});
assert.equal(generated.status,0,generated.stderr);
const adminDefinitions=JSON.parse(generated.stdout.replace(/^\uFEFF/,''));
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<div id="root"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudKendoFormRenderer.js','src/crud-engine/CrudLayoutManager.js','src/crud-engine/CrudToolbarRenderer.js','src/crud-engine/CrudFilterRenderer.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudEngine.js','src/page-engine/PageDefinitionNormalizer.js','src/crud-engine/CrudDefinitionValidator.js']) await page.addScriptTag({path:path.resolve(file)});
 const adminValidation=await page.evaluate(definitions=>{
  return Object.entries(definitions).map(([name,definition])=>{
   try {new CrudDefinitionValidator().validate(definition,{securityPolicy:CrudUtils.normalizeSecurityPolicy({security:{mode:'production'}},{})});return {name,errors:[]};}
   catch(e){return {name,errors:e.payload?.error?.details?.errors||[e.message]};}
  });
 },adminDefinitions);
 assert.deepEqual(adminValidation,[{name:'transactions',errors:[]},{name:'events',errors:[]}]);
 const actorQueryChoices=await page.evaluate(definitions=>Object.values(definitions).flatMap(definition=>{
  const engine=Object.create(CrudEngine.prototype);engine.definition=new PageDefinitionNormalizer().normalize(definition);
  return engine.getSortableSortFields().concat(engine.getGroupableFields()).filter(item=>item.field.startsWith('actor_user_'));
 }),adminDefinitions);
 assert.deepEqual(actorQueryChoices,[]);
 const queryPipeline=await page.evaluate(async definitions=>{
  const results=[];
  for(const [name,filters] of [['transactions',[{field:'screen_id',operator:'eq',value:'ciw.despesas'},{field:'record_id',operator:'neq',value:'1004'},{field:'record_id',operator:'eq',value:'1003'}]],['events',[{field:'transaction_id',operator:'eq',value:'325'}]]]) {
   const engine=Object.create(CrudEngine.prototype);engine.root=$('#root');engine.options={initialFilters:filters};engine.definition=new PageDefinitionNormalizer().normalize(definitions[name]);
   engine.config=engine.normalizeConfig({});engine.securityPolicy=CrudUtils.normalizeSecurityPolicy(engine.config,{});engine.contextValues={};
   engine.httpClient={request:async request=>{results.push({name,summary:request.data?.take===500,filters:request.data?.filters||[]});return {data:[],total:0};}};
   CrudUtils.applyEndpointSecurity(engine.definition,engine.securityPolicy);engine.render();await engine.waitForInitialGridRead();
   engine.gridRenderer.destroy();engine.filterRenderer.destroy();kendo.destroy(engine.root);engine.root.empty();
  }
  return results.filter(x=>x.filters.length&&!x.summary).map(x=>({name:x.name,filters:x.filters.map(f=>({field:f.field,operator:f.operator,value:String(f.value)}))}));
 },adminDefinitions);
 assert.deepEqual(queryPipeline,[{name:'transactions',filters:[{field:'screen_id',operator:'eq',value:'ciw.despesas'},{field:'record_id',operator:'eq',value:'1003'},{field:'record_id',operator:'neq',value:'1004'}]},{name:'events',filters:[{field:'transaction_id',operator:'eq',value:'325'}]}]);
 const result=await page.evaluate(definitions=>{
  const logs={enabled:true,mode:'deletions',title:'Logs Exclusão',documentId:'runtime.deletions',screenId:'ciw.empresas',requiresRecord:false};
  const recordLogs={enabled:true,mode:'transactions',title:'Logs',documentId:'runtime.transactions',requiresRecord:true};
  const definition={id:'empresas',screenId:'ciw.empresas',title:'Empresas',logs,dataModel:{primaryKey:'empresa_id',fields:{empresa_id:{type:'integer',label:'ID'},nome:{type:'string',label:'Nome'}}},permissions:{create:true},form:{id:'logs-test',mode:'popup',layout:'sections',logs:recordLogs,fields:[{field:'nome'}]}};
  const engine=Object.create(CrudEngine.prototype);engine.definition=definition;engine.securityPolicy=CrudUtils.normalizeSecurityPolicy({security:{mode:'production'}});
  const urls=[];
  const capture=(record,config)=>{engine.openLogsWindow(record,config);const frame=$('.crud-log-frame').last();urls.push(frame.attr('src'));frame.closest('.k-window-content').data('kendoWindow').close();};
  capture({empresa_id:37},recordLogs);capture();
  let formLog;
  const form=new CrudKendoFormRenderer({definition,httpClient:{},onLogs:(record,config)=>{formLog={record,config};}});
  form.open('create',{});
  const button=form.window.element.find('button').filter((_,el)=>$(el).text()==='Logs').first();
  const enabled=button.length>0&&!button.prop('disabled');
  form.openCurrentRecordLogs();const createBlocked=formLog===undefined;form.close(true);
  form.open('create',{empresa_id:0,nome:'Novo'});form.openCurrentRecordLogs();const zeroCreateBlocked=formLog===undefined;form.close(true);
  form.open('create',{empresa_id:37,nome:'Novo'});form.openCurrentRecordLogs();const prefilledCreateBlocked=formLog===undefined;form.close(true);
  form.open('view',{empresa_id:37,nome:'Empresa'});form.openCurrentRecordLogs();form.close(true);
  const events={enabled:true,mode:'transactionEvents',documentId:'runtime.transaction-events',requiresRecord:true};
  engine.definition.logs=events;capture({empresa_id:71});
  const countBefore=$('.crud-log-frame').length;engine.openLogsWindow();const refusesMissing=$('.crud-log-frame').length===countBefore;
  const recordEvents={enabled:true,mode:'recordEvents',documentId:'runtime.record-events',requiresRecord:true};
  capture({empresa_id:37},recordEvents);const beforeMissing=$('.crud-log-frame').length;engine.openLogsWindow(undefined,recordEvents);const recordEventsRefusesMissing=$('.crud-log-frame').length===beforeMissing;
  // Custom documents continue through the existing document policy resolver.
  engine.definition.logs={enabled:true,documentId:'custom-log'};capture({empresa_id:2});
  const rowRenderer=Object.create(CrudKendoGridRenderer.prototype);
  const rowLink=rowRenderer.buildRowLinkHtml({id:'transaction_logs',label:'Logs',screenId:'admin.logs-transacoes',parameters:[{field:'transaction_id',sourceField:'id'}]},{id:71});
  const context=Object.create(CrudEngine.prototype);
  context.definition={contextFields:[{field:'transaction_id',filter:true}],dataModel:{fields:{transaction_id:{type:'integer'}}}};
  context.options={contextValues:{transaction_id:'71'}};context.initializeNavigationContext();
  const fixedFilters=context.withContextFilters([{field:'transaction_id',value:12},{field:'event_type',value:'progress.log.info'}]);
  context.options={contextValues:{}};context.initializeNavigationContext();const optionalContext=context.withContextFilters([]);
  definition.form.logs={enabled:true,documentId:'custom-log'};
  const oldForm=new CrudKendoFormRenderer({definition,httpClient:{},onLogs:()=>{throw new Error('Documento custom exige registro');}});
  oldForm.open('create',{});oldForm.openCurrentRecordLogs();oldForm.close(true);
  const virtualContext=Object.create(CrudEngine.prototype);virtualContext.definition={contextFields:[{field:'audit_screen_id',filter:true},{field:'audit_operation',filter:true}],dataModel:{fields:{audit_screen_id:{type:'string',validation:{maxLength:160}},audit_operation:{type:'string',options:[{value:'delete',text:'Exclusão'}]}}}};
  virtualContext.options={contextValues:{audit_screen_id:'ciw.empresas',audit_operation:'delete'}};virtualContext.initializeNavigationContext();const stringFilters=virtualContext.withContextFilters([]);
  let invalidOperation=false;virtualContext.options.contextValues.audit_operation='update';try{virtualContext.initializeNavigationContext();}catch{invalidOperation=true;}
  const opaque='  opaque-001/A?b=ç  ';const opaqueContext=Object.create(CrudEngine.prototype);opaqueContext.definition=new PageDefinitionNormalizer().normalize(definitions.transactions);opaqueContext.options={contextValues:{screen_id:'ciw.empresas',record_id:opaque}};opaqueContext.initializeNavigationContext();const opaqueAfterClear=opaqueContext.withContextFilters([]);const opaqueAfterOverride=opaqueContext.withContextFilters([{field:'record_id',operator:'eq',value:'OUTRO'}]);
  const capabilityErrors=[];new CrudDefinitionValidator().validateDeclarativeCapabilities(virtualContext.definition,capabilityErrors);
  return {urls,enabled,formLog,createBlocked,zeroCreateBlocked,prefilledCreateBlocked,recordEventsRefusesMissing,refusesMissing,rowLink,fixedFilters,optionalContext,stringFilters,opaqueAfterClear,opaqueAfterOverride,invalidOperation,capabilityErrors};
 },adminDefinitions);
 assert.equal(result.urls[0],'../production/app.html?screenId=admin.transacoes&context__screen_id=ciw.empresas&context__record_id=37');
 assert.equal(result.urls[1],'../production/app.html?screenId=admin.logs-transacoes&context__audit_screen_id=ciw.empresas&context__audit_operation=delete');
 assert.equal(result.enabled,false);assert.equal(result.createBlocked,true);assert.equal(result.zeroCreateBlocked,true);assert.equal(result.prefilledCreateBlocked,true);assert.equal(result.formLog.config.requiresRecord,true);
 assert.equal(result.urls[2],'../production/app.html?screenId=admin.logs-transacoes&filter__transaction_id=71');
 assert.match(result.rowLink,/screenId=admin.logs-transacoes/);assert.match(result.rowLink,/context__transaction_id=71/);
 assert.deepEqual(result.fixedFilters,[{field:'event_type',value:'progress.log.info'},{field:'transaction_id',operator:'eq',value:71}]);assert.deepEqual(result.optionalContext,[]);
 assert.equal(result.refusesMissing,true);assert.equal(result.urls[3],'../production/app.html?screenId=admin.logs-transacoes&context__audit_screen_id=ciw.empresas&context__audit_record_id=37');assert.equal(result.recordEventsRefusesMissing,true);assert.match(result.urls[4],/documents\/custom-log$/);
 assert.deepEqual(result.stringFilters,[{field:'audit_screen_id',operator:'eq',value:'ciw.empresas'},{field:'audit_operation',operator:'eq',value:'delete'}]);assert.deepEqual(result.opaqueAfterClear,[{field:'screen_id',operator:'eq',value:'ciw.empresas'},{field:'record_id',operator:'eq',value:'  opaque-001/A?b=ç  '}]);assert.deepEqual(result.opaqueAfterOverride,result.opaqueAfterClear);assert.equal(result.invalidOperation,true);assert.deepEqual(result.capabilityErrors,[]);assert.deepEqual(errors,[]);
 console.log('PASS: janela Kendo existente, filtros transação/registro, inclusão bloqueada, contextos fixos e documentos customizados.');
} finally {await browser.close();}
