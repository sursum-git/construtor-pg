import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';
import {spawnSync} from 'node:child_process';
const generated=spawnSync(process.env.PHP_BINARY||'php',['backend/tests/Runtime/fixtures/transaction-log-definitions.php','--business'],{encoding:'utf8'});assert.equal(generated.status,0,generated.stderr);const business=JSON.parse(generated.stdout.replace(/^\uFEFF/,''));
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.setContent('<div id="root"></div><div id="readonly-root"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/page-engine/PageDefinitionNormalizer.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudEngine.js'])await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async business=>{
  const calls=[],urls=[];
  const definition=new PageDefinitionNormalizer().normalize(business.batch);try{new CrudDefinitionValidator().validate(definition);}catch(e){throw new Error(JSON.stringify(e.payload?.error?.details||{message:e.message}));}
  CrudUtils.applyEndpointSecurity(definition,CrudUtils.normalizeSecurityPolicy({security:{mode:'production'}}));
  const engine=Object.create(CrudEngine.prototype);engine.definition=definition;engine.securityPolicy=CrudUtils.normalizeSecurityPolicy({security:{mode:'production'}});
  const renderer=new CrudKendoGridRenderer({definition,httpClient:{request:async request=>{calls.push(request);return {data:[{tipo_anexo_id:1,descricao:'Um'},{tipo_anexo_id:2,descricao:'Dois'}],total:2};}},handlers:{logs:(record,logs)=>{engine.openLogsWindow(record,logs);const frame=$('.crud-log-frame').last();urls.push(frame.attr('src'));frame.closest('.k-window-content').data('kendoWindow').close();}}});
  engine.gridRenderer=renderer;renderer.render($('#root'));renderer.bindRowActions();await renderer.waitForInitialRead();
  const first=renderer.grid.dataSource.get(1),second=renderer.grid.dataSource.get(2);first.set('descricao','Rascunho');renderer.toggleBatchDelete(second);const created=renderer.addBatchRow();created.set('descricao','Novo');
  const before=JSON.stringify(renderer.collectBatchChanges()),snapshot=JSON.stringify(Array.from(renderer.batchOriginals));
  const row=renderer.grid.tbody.find('tr[data-uid="'+first.uid+'"]'),button=row.find('.crud-batch-logs');const exists=button.length===1;button.trigger('click');
  const kept=before===JSON.stringify(renderer.collectBatchChanges())&&snapshot===JSON.stringify(Array.from(renderer.batchOriginals))&&renderer.hasUnsavedChanges();
  const newDisabled=renderer.grid.tbody.find('tr[data-uid="'+created.uid+'"] .crud-batch-logs').prop('disabled');
  const deletedDisabled=renderer.grid.tbody.find('tr[data-uid="'+second.uid+'"] .crud-batch-logs').prop('disabled');
  if(typeof renderer.openBatchLogs==='function'){renderer.openBatchLogs(created);renderer.openBatchLogs(second);}
  const readonly=CrudUtils.clone(definition);readonly.permissions.delete=false;
  const other=new CrudKendoGridRenderer({definition:readonly,httpClient:{request:async()=>({data:[{tipo_anexo_id:3,descricao:'Três'}],total:1})},handlers:{logs:()=>{}}});other.render($('#readonly-root'));await other.waitForInitialRead();const availableWithoutDelete=other.grid.tbody.find('.crud-batch-logs').length===1&&other.grid.tbody.find('.crud-batch-delete').length===0;
  renderer.destroy();other.destroy();return {exists,kept,newDisabled,deletedDisabled,availableWithoutDelete,urls,calls:calls.map(c=>c.url)};
 },business);
 assert.equal(result.exists,true);assert.equal(result.kept,true);assert.equal(result.newDisabled,true);assert.equal(result.deletedDisabled,true);assert.equal(result.availableWithoutDelete,true);
 assert.deepEqual(result.urls,['../production/app.html?screenId=admin.logs-transacoes&context__audit_screen_id=ciw.tipos-anexo&context__audit_record_id=1']);assert.equal(result.calls.length,1);assert.match(result.calls[0],/endpoints\/read$/);assert.deepEqual(errors,[]);
 console.log('PASS: Logs por linha persistida no lote, sem incluir/excluir rascunhos ou alterar estado pendente.');
} finally {await browser.close();}
