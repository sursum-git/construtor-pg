import assert from 'node:assert/strict';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {chromium} from 'playwright';
const generated=spawnSync(process.env.PHP_BINARY||'php',['backend/tests/Runtime/fixtures/transaction-log-definitions.php'],{encoding:'utf8'});
assert.equal(generated.status,0,generated.stderr);
const definitions=JSON.parse(generated.stdout.replace(/^\uFEFF/,''));
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.setContent('<div id="root"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/page-engine/PageDefinitionNormalizer.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudKendoFormRenderer.js']) await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(definitions=>{
  const definition=new PageDefinitionNormalizer().normalize(definitions.events);
  try{new CrudDefinitionValidator().validate(definition,{securityPolicy:CrudUtils.normalizeSecurityPolicy({security:{mode:'production'}},{})});}catch(e){throw new Error(JSON.stringify(e.payload?.error?.details||{message:e.message}));}
  const form=new CrudKendoFormRenderer({definition,httpClient:{}});
  const open=record=>{form.open('view',Object.assign({id:7,event_type:'runtime.entity.updated'},record));const root=form.window.element;return {rows:root.find('.crud-audit-diff tbody tr').map((_,row)=>[$(row).find('td').map((__,cell)=>$(cell).text()).get()]).get(),text:root.find('.crud-audit-diff').text(),images:root.find('.crud-audit-diff img').length,actor:root.find('[data-field="actor_user_name"] .crud-readonly-value').text(),actorId:root.find('[data-field="actor_user_id"] .crud-readonly-value').text()};};
  const before={nome:'Antes',ativo:false,quantidade:0,opcional:null,itens:[1,2],senha:'never-render-this',nested:{token:'never-render-nested',ok:0}};
  const after={nome:'<img src=x onerror="window.auditXss=true">',ativo:true,quantidade:2,opcional:'',itens:[],senha:'never-render-after',nested:{token:'never-render-after-nested',ok:false}};
  const diff=Object.fromEntries(Object.keys(before).map(key=>[key,{before:before[key],after:after[key]}]));
  const update=open({actor_user_name:'Pessoa de teste',actor_user_id:'user-test',before_data:before,after_data:after,diff_data:diff});form.setFieldValue('diff_data',{});const refreshedEmpty=form.window.element.find('.crud-audit-diff tbody tr').length===0;form.close(true);
  const create=open({before_data:[],after_data:{id:7,ativo:false,quantidade:0},diff_data:{id:{before:null,after:7},ativo:{before:null,after:false},quantidade:{before:null,after:0}}});form.close(true);
  const remove=open({before_data:{id:7,ativo:false},after_data:[],diff_data:{id:{before:7,after:null},ativo:{before:false,after:null}}});form.close(true);
  const request=open({event_type:'runtime.request',before_data:{nome:'payload'},after_data:{nome:'outro payload'},diff_data:[]});form.close(true);
  const legacy=open({event_type:'despesas.api.update',before_data:{nome:'payload novo'},after_data:{nome:'atual'},diff_data:{nome:{before:'payload novo',after:'atual'}},metadata:{}});form.close(true);
  const legacyBatch=open({event_type:'despesas.api.batch.update',before_data:{nome:'payload novo'},after_data:{nome:'atual'},diff_data:{nome:{before:'payload novo',after:'atual'}},metadata:{audit:{available:false,version:1,source:'progress-transaction'}}});form.close(true);
  const confirmed=open({event_type:'despesas.api.update',before_data:{nome:'Anterior real'},after_data:{nome:'Atual real'},diff_data:{nome:{before:'Anterior real',after:'Atual real'}},metadata:{audit:{available:true,version:1,source:'progress-transaction'}}});form.close(true);
  const unsupported=open({event_type:'despesas.api.update',before_data:{nome:'Antes'},after_data:{nome:'Depois'},diff_data:{nome:{before:'Antes',after:'Depois'}},metadata:{audit:{available:true,version:2,source:'progress-transaction'}}});form.close(true);
  const identified={available:true,version:1,source:'progress-transaction',operation:'update',recordId:71};
  const identifiedUpdate=open({event_type:'empresas_progress.api.update',before_data:{nome:'Antes'},after_data:{nome:'Depois'},diff_data:{nome:{before:'Antes',after:'Depois'}},metadata:JSON.stringify({audit:identified})});form.close(true);
  const identifiedCreate=open({event_type:'tipos_anexo_progress.api.create',before_data:{},after_data:{tipo_anexo_id:72,descricao:'Novo'},diff_data:{descricao:{before:null,after:'Novo'}},metadata:{audit:{...identified,operation:'create',recordId:72}}});form.close(true);
  const identifiedDelete=open({event_type:'tipos_anexo_progress.api.delete',before_data:{tipo_anexo_id:73,descricao:'Removido'},after_data:{},diff_data:{descricao:{before:'Removido',after:null}},metadata:{audit:{...identified,operation:'delete',recordId:73}}});form.close(true);
  const maliciousIdentity=open({event_type:'tipos_anexo_progress.api.update',before_data:{nome:'Antes'},after_data:{nome:'Depois'},diff_data:{nome:{before:'Antes',after:'Depois'}},metadata:{audit:{...identified,recordId:'<img src=x onerror="window.auditXss=true">',operation:'<img src=x>'}}});form.close(true);
  const batchSummary=open({event_type:'tipos_anexo_progress.api.batch',diff_data:{},metadata:{audit:{...identified,operation:'batch',changesCount:3}}});form.close(true);
  const item=definition.form.tabs.flatMap(tab=>tab.fields||[]).find(field=>field.renderAs==='audit-diff');
  const invalid=CrudUtils.clone(definitions.events);const badItem=invalid.crud.form.tabs.flatMap(tab=>tab.fields||[]).find(field=>field.renderAs==='audit-diff');
  if(badItem)badItem.auditDiff.url='javascript:invalid';let invalidRejected=false;try{new CrudDefinitionValidator().validate(invalid);}catch{invalidRejected=true;}
  const incomplete=CrudUtils.clone(definitions.events);delete incomplete.crud.form.tabs.flatMap(tab=>tab.fields||[]).find(field=>field.renderAs==='audit-diff').auditDiff.metadataField;
  let incompleteRejected=false;try{new CrudDefinitionValidator().validate(incomplete);}catch{incompleteRejected=true;}
  return {update,create,remove,request,legacy,legacyBatch,confirmed,unsupported,identifiedUpdate,identifiedCreate,identifiedDelete,maliciousIdentity,batchSummary,refreshedEmpty,incompleteRejected,invalidRejected,configured:!!item,xss:window.auditXss===true};
 },definitions);
 assert.equal(result.configured,true);assert.equal(result.refreshedEmpty,true);
 assert.deepEqual(result.update.rows,[['nome','Antes','<img src=x onerror="window.auditXss=true">'],['ativo','false','true'],['quantidade','0','2'],['opcional','null','""'],['itens','[1,2]','[]'],['senha','[protegido]','[protegido]'],['nested','{"token":"[protegido]","ok":0}','{"token":"[protegido]","ok":false}']]);
 assert.equal(result.update.images,0);assert.equal(result.xss,false);assert.ok(!result.update.text.includes('never-render'));
 assert.equal(result.update.actor,'Pessoa de teste');assert.equal(result.update.actorId,'user-test');
 assert.deepEqual(result.create.rows,[['id','Ausente','7'],['ativo','Ausente','false'],['quantidade','Ausente','0']]);
 assert.deepEqual(result.remove.rows,[['id','7','Ausente'],['ativo','false','Ausente']]);
 assert.deepEqual(result.legacy.rows,[]);assert.ok(result.legacy.text.includes('Comparação anterior/atual não disponível'));
 assert.deepEqual(result.legacyBatch.rows,[]);assert.deepEqual(result.unsupported.rows,[]);
 assert.deepEqual(result.confirmed.rows,[['nome','Anterior real','Atual real']]);
 assert.match(result.identifiedUpdate.text,/Registro 71.*Alteração/);assert.match(result.identifiedCreate.text,/Registro 72.*Inclusão/);assert.match(result.identifiedDelete.text,/Registro 73.*Exclusão/);
 assert.equal(result.maliciousIdentity.images,0);assert.ok(!result.maliciousIdentity.text.includes('<img'));
 assert.match(result.batchSummary.text,/Lote confirmado/);assert.match(result.batchSummary.text,/eventos de cada registro/);assert.ok(!/não disponível|não contém comparação|não registrou alteração/.test(result.batchSummary.text));
 assert.equal(result.create.actor,'');assert.equal(result.create.actorId,'');
 assert.deepEqual(result.request.rows,[]);assert.match(result.request.text,/não contém comparação/);assert.equal(result.invalidRejected,true);assert.equal(result.incompleteRejected,true);assert.deepEqual(errors,[]);
 console.log('PASS: ator legível, comparação auditada create/update/delete, null/false/zero/arrays, sem inferência do request ou HTML executável.');
} finally {await browser.close();}
