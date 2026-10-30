import assert from 'node:assert/strict';
import path from 'node:path';
import { chromium } from 'playwright';

// Production changes that must fail these checks: losing original snapshots,
// splitting the request, clearing a failed draft, or bypassing per-action permission.
const browser = await chromium.launch({ headless: true, channel: 'msedge' });
try {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.setContent('<div id="root"></div>');
  await page.evaluate(() => { window.__PROGRAM_BUILDER_AUTO_INIT__ = false; });
  for (const file of ['vendor/jquery/jquery-4.0.0.min.js', 'kendo/js/kendo.all.min.js', 'src/crud-engine/CrudUtils.js', 'src/crud-engine/CrudKendoGridRenderer.js', 'src/crud-engine/CrudToolbarRenderer.js', 'src/crud-engine/CrudEngine.js', 'src/crud-engine/CrudDefinitionValidator.js', 'src/program-builder/program-builder.js']) {
    await page.addScriptTag({ path: path.resolve(file) });
  }
  const result = await page.evaluate(async () => {
    const calls = [], notices = [];
    window.CrudUtils.showMessage = message => notices.push(message);
    window.CrudUtils.confirm = () => Promise.resolve(true);
    let fail = false;
    const definition = {
      id: 'batch-test', permissions: { read:true, create:true, edit:true, delete:true },
      api: { read:{url:'/read'}, batch:{url:'/batch',method:'POST'} },
      dataModel: { primaryKey:'record_id', fields: {
        record_id:{type:'integer',editable:false},
        title:{type:'string',label:'Descricao',editable:true,validation:{required:true,maxLength:100}}
      } }, query:{defaultPageSize:20},
      grid:{editing:{mode:'batch'}, columns:[{field:'record_id'},{field:'title'}], pageable:true, sortable:true, filterable:true}
    };
    const renderer = new CrudKendoGridRenderer({definition,httpClient:{request: async request => {
      calls.push(request);
      if (request.url === '/batch' && fail) throw {message:'Linha 2: alteracao concorrente.'};
      return {data:[{record_id:1,title:'Original'},{record_id:2,title:'Remover'}],total:2};
    }}});
    renderer.render($('#root')); renderer.bindRowActions();
    await renderer.waitForInitialRead();
    const ds = renderer.grid.dataSource;
    const keyColumn = renderer.grid.columns.find(column=>column.field==='record_id');
    const integerFormat = keyColumn.format;
    const idReadonly = !ds.at(0).editable('record_id');
    const toolbar = new CrudToolbarRenderer({definition,handlers:{}});
    toolbar.render($('<div>').appendTo(document.body));
    toolbar.setBatchState(false,false);
    const saveInitiallyDisabled = !toolbar.buttons.batchSave.options.enable;
    renderer.grid.editCell(renderer.grid.tbody.find('tr[data-uid]').first().find('td').last());
    const editor = renderer.grid.editable.element.find('[name="title"]');
    const editorValidation = editor.attr('maxlength')==='100' && editor.attr('required')==='required';
    editor.val('').trigger('change');
    const cellInvalidBlocked = !(await renderer.saveBatch());
    renderer.grid.closeCell(true); renderer.discardBatch();
    ds.at(0).set('title','Alterado');
    renderer.toggleBatchDelete(ds.at(1));
    const created = renderer.addBatchRow(); created.set('title','Novo');
    const changes = renderer.collectBatchChanges();
    const update = changes.find(c=>c.operation==='update');
    const creation = changes.find(c=>c.operation==='create');
    const deletion = changes.find(c=>c.operation==='delete');
    const dirty = renderer.hasUnsavedChanges();
    const engine = Object.create(CrudEngine.prototype);
    engine.gridRenderer=renderer; engine.currentFilters=[];
    engine.sortFiltersByDefinition=items=>items;
    let filterReset=false; engine.filterRenderer={setValues:()=>{filterReset=true;}};
    const engineDirty=engine.hasUnsavedChanges();
    let destroyed=false;
    const originalDestroy=renderer.destroy.bind(renderer);
    renderer.destroy=()=>{destroyed=true; originalDestroy();};
    let renderBlocked=false;
    try { renderBlocked=engine.render()===false && !destroyed; } catch { renderBlocked=false; }
    renderer.destroy=originalDestroy;
    const engineFilterBlocked=engine.applyFilters([{field:'title',value:'abc'}])===false && engine.currentFilters.length===0 && filterReset;
    const openBlocked=await engine.openRecord('view',1)===null;
    const refreshBlocked=engine.refresh()===false;
    toolbar.setBatchState(true,false);
    const saveDirtyEnabled=toolbar.buttons.batchSave.options.enable;
    toolbar.setBatchState(true,true);
    const saveBusyDisabled=!toolbar.buttons.batchSave.options.enable;
    const blockedFilter = renderer.setFilters([{field:'title',value:'abc'}]);
    const filtersPreserved = renderer.filters.length === 0;
    const pageEvent = {preventDefault(){this.prevented=true;}};
    renderer.guardBatchNavigation(pageEvent);
    fail = true;
    const failed = await renderer.saveBatch();
    const kept = renderer.hasUnsavedChanges() && ds.at(0).title === 'Novo' && ds.get(1).title === 'Alterado';
    fail = false;
    const success = await renderer.saveBatch();
    const clean = !renderer.hasUnsavedChanges();
    const batchCalls = calls.filter(c=>c.url==='/batch');
    ds.get(1).set('title','');
    const invalid = await renderer.saveBatch();
    const invalidKept = renderer.hasUnsavedChanges();
    await renderer.cancelBatch();
    const canceled = !renderer.hasUnsavedChanges() && ds.get(1).title === 'Original';
    ds.get(1).set('title','x'.repeat(101));
    const longBlocked = !(await renderer.saveBatch());
    renderer.discardBatch();
    const tooMany = Array.from({length:101},(_,i)=>({operation:'create',clientId:'new-'+i,data:{title:'Novo'}}));
    let limitBlocked=false; try { renderer.validateBatch(tooMany); } catch { limitBlocked=true; }
    definition.permissions.edit = false;
    ds.get(1).set('title','Proibido');
    const forbidden = await renderer.saveBatch();
    return {renderBlocked,integerFormat,idReadonly,editorValidation,cellInvalidBlocked,saveInitiallyDisabled,engineDirty,engineFilterBlocked,openBlocked,refreshBlocked,saveDirtyEnabled,saveBusyDisabled,longBlocked,limitBlocked,update,creation,deletion,dirty,blockedFilter,filtersPreserved,pageBlocked:pageEvent.prevented,failed,kept,success,clean,batchCalls,invalid,invalidKept,canceled,forbidden,notices};
  });
  assert.equal(result.integerFormat,'{0:n0}');
  assert.equal(result.update.id,1);
  assert.deepEqual(result.update.data,{title:'Alterado'});
  assert.deepEqual(result.update.original,{title:'Original'});
  assert.deepEqual(result.creation.data,{title:'Novo'});
  assert.equal(Object.hasOwn(result.creation,'id'),false);
  assert.equal(result.deletion.id,2);
  assert.deepEqual(result.deletion.original,{title:'Remover'});
  assert.equal(new Set(result.batchCalls[0].data.changes.map(c=>c.clientId)).size,3);
  for (const name of ['renderBlocked','idReadonly','editorValidation','cellInvalidBlocked','saveInitiallyDisabled','engineDirty','engineFilterBlocked','openBlocked','refreshBlocked','saveDirtyEnabled','saveBusyDisabled','longBlocked','limitBlocked','dirty','filtersPreserved','pageBlocked','kept','success','clean','invalidKept','canceled']) assert.equal(result[name],true,name);
  for (const name of ['blockedFilter','failed','invalid','forbidden']) assert.equal(result[name],false,name);
  assert.equal(result.batchCalls.length,2,'Um request por tentativa; validacao nao chama servidor');
  assert.equal(result.batchCalls[0].data.changes.length,3);
  assert.ok(result.notices.some(message=>message.includes('Linha 2')));
  const reloadGuard = await page.evaluate(async () => {
    let finishRead, readCount=0;
    const definition={id:'reload-test',permissions:{read:true,create:true,edit:true,delete:true},api:{read:{url:'/read'},batch:{url:'/batch'}},
      dataModel:{primaryKey:'id',fields:{id:{type:'integer',editable:false},title:{type:'string',editable:true},date:{type:'date',editable:true}}},
      grid:{editing:{mode:'batch'},columns:[{field:'id'},{field:'title'},{field:'date'}]}};
    const renderer=new CrudKendoGridRenderer({definition,httpClient:{request:async request=>{
      if(request.url==='/batch'){return {};}
      if(++readCount>1){await new Promise(resolve=>{finishRead=resolve;});}
      return {data:[{id:1,title:'Original',date:'2026-10-15'}],total:1};
    }}});
    renderer.render($('<div>').appendTo(document.body)); await renderer.waitForInitialRead();
    const dateInitiallyClean=!renderer.hasUnsavedChanges();
    renderer.grid.dataSource.at(0).set('title','Alterado');
    const saving=renderer.saveBatch();
    await new Promise(resolve=>setTimeout(resolve,0));
    const busyDuringReload=renderer.batchSaving;
    const addDuringReload=renderer.addBatchRow();
    finishRead(); await saving;
    return {dateInitiallyClean,busyDuringReload,addBlocked:addDuringReload===null,cleanAfter:!renderer.hasUnsavedChanges()};
  });
  assert.deepEqual(reloadGuard,{dateInitiallyClean:true,busyDuringReload:true,addBlocked:true,cleanAfter:true},'Datas nao criam dirty falso; releitura pos-commit bloqueia novos rascunhos');
  const inFlight = await page.evaluate(async () => {
    let finish, saves=0, readCount=0;
    const definition={id:'pending-test',permissions:{read:true,create:true,edit:true,delete:true},api:{read:{url:'/read'},batch:{url:'/batch'}},
      dataModel:{primaryKey:'id',fields:{id:{type:'integer',editable:false},title:{type:'string',editable:true}}},
      grid:{editing:{mode:'batch'},columns:[{field:'id'},{field:'title'}]}};
    const renderer=new CrudKendoGridRenderer({definition,httpClient:{request:async request=>{
      if(request.url==='/batch'){saves++; await new Promise(resolve=>{finish=resolve;}); return {};}
      if(++readCount>1){throw {message:'Consulta indisponivel'};}
      return {data:[{id:1,title:'Antes'}],total:1};
    }}});
    renderer.render($('<div>').appendTo(document.body)); await renderer.waitForInitialRead();
    renderer.grid.dataSource.at(0).set('title','Depois');
    const first=renderer.saveBatch();
    const duplicate=await renderer.saveBatch();
    const cancel=await renderer.cancelBatch();
    const engine=Object.create(CrudEngine.prototype); engine.gridRenderer=renderer;
    const leave=await engine.confirmDiscardChanges();
    const busy=renderer.batchSaving && renderer.hasUnsavedChanges();
    finish(); const success=await first;
    return {saves,duplicate,cancel,leave,busy,success,clean:!renderer.hasUnsavedChanges(),resend:await renderer.saveBatch()};
  });
  assert.deepEqual(inFlight,{saves:1,duplicate:false,cancel:false,leave:false,busy:true,success:true,clean:true,resend:false},'Gravacao em curso nao duplica nem descarta; falha na releitura nao reenvia lote confirmado');
  const flags = await page.evaluate(() => {
    const b = Object.create(ProgramBuilder.prototype);
    const value = text => ({value:()=>text});
    b.pageTypeSelect=value('crud'); b.entityTypeSelect=value('api');
    b.gridEditModeSelect=value('batch'); b.apiCatalogBatchOperationSelect=value('save-all');
    b.apiCatalogSourceSelect=value('source'); b.findApiSourceSummary=()=>({providerType:'generic'});
    b.apiCatalogCreateOperationSelect=value(''); b.apiCatalogUpdateOperationSelect=value(''); b.apiCatalogDeleteOperationSelect=value('');
    b.allowCreateInput=$('<input type="checkbox">'); b.allowUpdateInput=$('<input type="checkbox">'); b.allowDeleteInput=$('<input type="checkbox">');
    b.syncProgramWriteFlagsForApi();
    return {create:b.allowCreateInput.is(':checked'),edit:b.allowUpdateInput.is(':checked'),remove:b.allowDeleteInput.is(':checked')};
  });
  assert.deepEqual(flags,{create:true,edit:true,remove:false},'Binding batch permite inclusao/alteracao e preserva decisao explicita da exclusao');
  assert.deepEqual(errors,[]);
  console.log('PASS: CRUD batch real Kendo: lote unico, snapshot, rollback local, cancelar, validacao, permissoes e navegacao.');
} finally { await browser.close(); }
