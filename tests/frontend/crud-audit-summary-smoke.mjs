import assert from 'node:assert/strict';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {chromium} from 'playwright';
const generated=spawnSync(process.env.PHP_BINARY||'php',['backend/tests/Runtime/fixtures/transaction-log-definitions.php'],{encoding:'utf8'});
assert.equal(generated.status,0,generated.stderr);const definitions=JSON.parse(generated.stdout.replace(/^\uFEFF/,''));
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.setContent('<div id="root"></div>');
 for(const file of ['kendo/styles/default-urban.css','src/styles/crud-engine.css'])await page.addStyleTag({path:path.resolve(file)});
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/page-engine/PageDefinitionNormalizer.js','src/crud-engine/CrudConfigLoader.js','src/crud-engine/CrudDefinitionLoader.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudLayoutManager.js','src/crud-engine/CrudToolbarRenderer.js','src/crud-engine/CrudFilterRenderer.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudKendoFormRenderer.js','src/crud-engine/CrudEngine.js'])await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async definitions=>{
  const invalid=CrudUtils.clone(definitions.events);invalid.crud.auditSummary.url='https://untrusted.invalid';let invalidRejected=false;try{new CrudDefinitionValidator().validate(invalid);}catch{invalidRejected=true;}
  if(!invalidRejected)throw new Error('auditSummary deve recusar URL livre');
  const base={id:1388,transaction_id:443,actor_user_name:'Pessoa de teste',actor_user_id:'user-test',event_type:'despesas.api.update',before_data:{descricao:'Anterior real'},after_data:{descricao:'Atual real'},diff_data:{descricao:{before:'Anterior real',after:'Atual real'}},metadata:{audit:{available:true,version:1,source:'progress-transaction'}}};
  const batchRows=[2,3].map(id=>({...base,id:1400+id,event_type:'tipos_anexo_progress.api.update',before_data:{tipo_anexo_id:id,descricao:'Anterior '+id},after_data:{tipo_anexo_id:id,descricao:'Atual '+id},diff_data:{descricao:{before:'Anterior '+id,after:'Atual '+id}},metadata:{audit:{available:true,version:1,source:'progress-transaction',resource:'tipos_anexo',operation:'update',recordId:id}}}));
  const batch={...base,id:1410,event_type:'tipos_anexo_progress.api.batch',before_data:{},after_data:{},diff_data:{},metadata:{audit:{available:true,version:1,source:'progress-transaction',resource:'tipos_anexo',operation:'batch',changesCount:2}}};
  const cases={confirmed:[base],batch:[batch,...batchRows],unavailableBatch:[{...batch,metadata:{audit:{available:false,reason:'invalid'}}}],query:[{...base,event_type:'despesas.api.get',metadata:{}}],legacy:[{...base,metadata:{}}],empty:[],partial:[{...base,event_type:'runtime.request',diff_data:{},metadata:{}}],failure:[base],unselected:[base],recordScope:[base],deletionScope:[base]};const states={};
  for(const [name,records] of Object.entries(cases)) {
   const engine=Object.create(CrudEngine.prototype);engine.root=$('#root');engine.options={initialFilters:name==='unselected'?[]:[{field:'transaction_id',operator:'eq',value:'443'}]};engine.definition=new PageDefinitionNormalizer().normalize(definitions.events);engine.config=engine.normalizeConfig({});engine.securityPolicy=CrudUtils.normalizeSecurityPolicy(engine.config,{});engine.contextValues=name==='recordScope'?{audit_screen_id:'ciw.tipos-anexo',audit_record_id:21}:name==='deletionScope'?{audit_screen_id:'ciw.tipos-anexo',audit_operation:'delete'}:{};const calls=[];
   engine.httpClient={request:async request=>{calls.push(request);if(name==='failure'&&request.data?.take===500)throw new Error('Falha de leitura autorizada');return {data:records,total:name==='partial'?501:records.length};}};
   CrudUtils.applyEndpointSecurity(engine.definition,engine.securityPolicy);engine.render();await engine.waitForInitialGridRead();await engine.auditSummaryReady;
   const panel=engine.root.find('.crud-audit-summary');states[name]={state:panel.attr('data-state'),text:panel.text(),tables:panel.find('.crud-audit-diff-table').length,rows:panel.find('tbody tr').map((_,tr)=>[$(tr).find('td').map((__,td)=>$(td).text()).get()]).get(),summaryCalls:calls.filter(call=>call.data?.take===500).length,summaryFilter:calls.find(call=>call.data?.take===500)?.data.filters};
   if(name==='recordScope'||name==='deletionScope'){engine.clearFilters();states[name].afterClear=engine.currentFilters;states[name].clearState=panel.attr('data-state');}
   engine.gridRenderer.destroy();engine.filterRenderer.destroy();kendo.destroy(engine.root);engine.root.empty();
  }
  const form=new CrudKendoFormRenderer({definition:new PageDefinitionNormalizer().normalize(definitions.transactions),config:{literals:{enabled:false}},httpClient:{request:async request=>request.url.endsWith('/admin.logs-transacoes')?definitions.events:{data:[base],total:1}}});
  form.open('view',{id:443,actor_user_name:'Pessoa de teste',actor_user_id:'user-test'});window.auditSummaryTestForm=form;
  return states;
 },definitions);
 assert.deepEqual(result.confirmed.rows,[['descricao','Anterior real','Atual real']]);assert.match(result.confirmed.text,/443/);assert.match(result.confirmed.text,/Pessoa de teste/);
 assert.deepEqual(result.recordScope.summaryFilter,[{field:'transaction_id',operator:'eq',value:'443'},{field:'audit_screen_id',operator:'eq',value:'ciw.tipos-anexo'},{field:'audit_record_id',operator:'eq',value:21}]);
 assert.deepEqual(result.deletionScope.summaryFilter,[{field:'transaction_id',operator:'eq',value:'443'},{field:'audit_screen_id',operator:'eq',value:'ciw.tipos-anexo'},{field:'audit_operation',operator:'eq',value:'delete'}]);
 assert.deepEqual(result.recordScope.afterClear,result.recordScope.summaryFilter.slice(1));assert.deepEqual(result.deletionScope.afterClear,result.deletionScope.summaryFilter.slice(1));assert.equal(result.recordScope.clearState,'awaiting-selection');
 assert.equal(result.confirmed.summaryCalls,1);assert.deepEqual(result.confirmed.summaryFilter,[{field:'transaction_id',operator:'eq',value:'443'}]);
 assert.equal(result.batch.tables,2);assert.match(result.batch.text,/Registro 2.*Alteração/);assert.match(result.batch.text,/Registro 3.*Alteração/);assert.match(result.batch.text,/Lote confirmado/);assert.ok(!/não disponível|não contém comparação|não registrou alteração|não possui diferenças/.test(result.batch.text));assert.deepEqual(result.batch.rows,[['descricao','Anterior 2','Atual 2'],['descricao','Anterior 3','Atual 3']]);
 assert.equal(result.unavailableBatch.tables,0);assert.match(result.unavailableBatch.text,/Comparação anterior\/atual não disponível/);
 assert.equal(result.query.tables,0);assert.match(result.query.text,/Esta transação não registrou alteração de dados/);
 assert.equal(result.legacy.tables,0);assert.match(result.legacy.text,/Comparação anterior\/atual não disponível/);
 assert.equal(result.failure.state,'error');assert.match(result.failure.text,/Não foi possível carregar/);assert.ok(!result.failure.text.includes('não registrou alteração'));
 assert.equal(result.partial.state,'incomplete');assert.match(result.partial.text,/limite/);
 assert.equal(result.unselected.summaryCalls,0);assert.equal(result.unselected.text.includes('Abra um evento para visualizar os valores anteriores e atuais.'),true);
 await page.waitForSelector('.crud-linked-page-host .crud-audit-summary .crud-audit-diff-table');
 const formState=await page.evaluate(()=>{const f=window.auditSummaryTestForm;return {firstTab:f.window.element.find('.crud-form-tabs li[role="tab"]').first().text(),logs:f.window.element.find('button').filter((_,b)=>$(b).text()==='Logs').length,frames:f.window.element.find('iframe').length,tables:f.window.element.find('.crud-linked-page-host .crud-audit-diff-table').length};});
 const linkedClear=await page.evaluate(async()=>{const child=Object.values(window.auditSummaryTestForm.linkedPageEngines)[0].engine;const before=child.currentFilters;child.clearFilters();await child.auditSummaryReady;return {context:child.options.contextValues,after:child.currentFilters,before,table:child.root.find('.crud-audit-diff-table').length};});
 assert.equal(linkedClear.context?.transaction_id,'443');assert.deepEqual(linkedClear.after,linkedClear.before);assert.ok(linkedClear.table>0);
 assert.equal(formState.firstTab,'Alterações e eventos');assert.equal(formState.logs,0);assert.equal(formState.frames,0);assert.ok(formState.tables>0);assert.deepEqual(errors,[]);
 await page.locator('.crud-form-footer-appbar button').filter({hasText:/^Cancelar$/}).last().click();
 await page.waitForFunction(()=>!document.querySelector('.crud-form'));
 const stringLinked = await page.evaluate(async definitions=>{
  const parent=CrudUtils.clone(definitions.transactions);const tab=parent.crud.form.tabs.find(item=>item.type==='linkedPage');tab.linkedPage.screenId='admin.transacoes';tab.linkedPage.filters=[{field:'record_id',sourceField:'id'},{field:'screen_id',value:'ciw.empresas'}];
  const f=new CrudKendoFormRenderer({definition:new PageDefinitionNormalizer().normalize(parent),config:{literals:{enabled:false}},httpClient:{request:async request=>request.url.endsWith('/admin.transacoes')?definitions.transactions:{data:[],total:0}}});f.open('view',{id:37});
  const entry=Object.values(f.linkedPageEngines)[0];await new Promise((resolve,reject)=>{const started=Date.now();const timer=setInterval(()=>{if(entry.host.find('.crud-linked-page-error').length||entry.host.attr('data-render-state')==='ready'){clearInterval(timer);resolve();}else if(Date.now()-started>3000){clearInterval(timer);reject(new Error('linkedPage string não inicializou'));}},10);});
  const child=entry.engine;const result={error:entry.host.find('.crud-linked-page-error').text(),context:child.contextValues};if(!result.error){child.clearFilters();result.filters=child.currentFilters;}f.close();return result;
 },definitions);
 assert.equal(stringLinked.error,'');assert.equal(stringLinked.context.record_id,'37');assert.ok(stringLinked.filters.some(filter=>filter.field==='record_id'&&filter.value==='37'));
 await page.evaluate(async definitions=>{
  const rows=[{id:443,actor_user_name:'Pessoa de teste',actor_user_id:'user-test'},{id:503,actor_user_name:'Pessoa de teste',actor_user_id:'user-test'}];
  const confirmed={id:1388,transaction_id:443,event_type:'despesas.api.update',before_data:{descricao:'Antes'},after_data:{descricao:'Depois'},diff_data:{descricao:{before:'Antes',after:'Depois'}},metadata:{audit:{available:true,version:1,source:'progress-transaction'}}};
  const query={id:1503,transaction_id:503,event_type:'despesas.api.get',diff_data:{},metadata:{}};
  const engine=Object.create(CrudEngine.prototype);engine.root=$('#root');engine.options={};engine.definition=new PageDefinitionNormalizer().normalize(definitions.transactions);engine.config=engine.normalizeConfig({literals:{enabled:false}});engine.securityPolicy=CrudUtils.normalizeSecurityPolicy(engine.config,{});engine.contextValues={};
  window.reopenAuditRequests=[];
  engine.httpClient={request:async request=>{
   window.reopenAuditRequests.push({url:request.url,id:request.data?.id});
   if(request.url.endsWith('/admin.logs-transacoes'))return definitions.events;
   if(request.url.includes('/admin.transacoes/'))return request.url.includes('/get')?rows.find(row=>String(row.id)===String(request.data.id)):{data:rows,total:rows.length};
   if(request.url.includes('/admin.logs-transacoes/'))return request.url.includes('/get')?confirmed:{data:[String(request.data.filters?.[0]?.value)==='503'?query:confirmed],total:1};
   return {};
  }};
  CrudUtils.applyEndpointSecurity(engine.definition,engine.securityPolicy);engine.render();await engine.waitForInitialGridRead();window.reopenAuditEngine=engine;
 },definitions);
 await page.locator('#root .crud-row-actions-toggle[data-id="443"]').click();
 await page.locator('.crud-row-actions-popup [data-action="view"]').click();
 await page.waitForSelector('.crud-linked-page-host .crud-audit-summary .crud-audit-diff-table');
 await page.locator('.crud-linked-page-host .crud-row-actions-toggle[data-id="1388"]').click();
 await page.locator('.crud-row-actions-popup [data-action="view"]').click();
 await page.waitForFunction(()=>document.querySelectorAll('.crud-form').length===2);
 const ownership=await page.evaluate(()=>({parentId:window.reopenAuditEngine.formRenderer.data.id,parentGets:window.reopenAuditRequests.filter(r=>r.url.includes('/admin.transacoes/')&&r.url.includes('/get')).map(r=>r.id),childGets:window.reopenAuditRequests.filter(r=>r.url.includes('/admin.logs-transacoes/')&&r.url.includes('/get')).map(r=>r.id)}));
 assert.equal(ownership.parentId,443);assert.deepEqual(ownership.parentGets,[443]);assert.deepEqual(ownership.childGets,[1388]);
 await page.locator('.crud-form-footer-appbar button').filter({hasText:/^Cancelar$/}).last().click();
 await page.waitForFunction(()=>document.querySelectorAll('.crud-form').length===1);
 await page.locator('.crud-form-footer-appbar button').filter({hasText:/^Cancelar$/}).last().click();
 await page.waitForFunction(()=>!document.querySelector('.crud-form'));
 await page.locator('#root .crud-row-actions-toggle[data-id="503"]').click();
 await page.locator('.crud-row-actions-popup [data-action="view"]').click();
 await page.waitForFunction(()=>window.reopenAuditEngine.formRenderer.data.id===503&&document.querySelector('.crud-linked-page-host .crud-audit-summary[data-state="ready"]'),null,{timeout:3000});
 assert.match(await page.locator('.crud-linked-page-host .crud-audit-summary').innerText(),/Esta transação não registrou alteração de dados/);
 await page.locator('.crud-form-footer-appbar button').filter({hasText:/^Cancelar$/}).last().click();await page.waitForFunction(()=>!document.querySelector('.crud-form'));
 await page.locator('#root .crud-row-actions-toggle[data-id="443"]').click();await page.locator('.crud-row-actions-popup [data-action="view"]').click();
 await page.waitForFunction(()=>window.reopenAuditEngine.formRenderer.data.id===443&&document.querySelector('.crud-linked-page-host .crud-audit-summary .crud-audit-diff-table'),null,{timeout:3000});
 assert.deepEqual(errors,[]);
 console.log('PASS: comparação automática acima da grade e no linkedPage inicial; consultas/legado/limite/erro distintos; sem Logs recursivo ou iframe novo; ciclo443→Cancelar→503→443 e ações isoladas por grade.');
} finally {await browser.close();}
