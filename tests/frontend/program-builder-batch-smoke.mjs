import assert from 'node:assert/strict';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

// Uses the existing editor fixture without changing demo sources or backend data.
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  const errors=[]; page.on('pageerror',error=>errors.push(error.message));
  await page.goto(pathToFileURL(path.resolve('examples/pages/program-builder-technical-properties.html')).href);
  await page.waitForFunction(()=>window.programBuilderDemoApp?.apiCatalogBatchOperationSelect);
  await page.evaluate(()=>{
    const app=window.programBuilderDemoApp;
    app.resetProgramForm();
    const source={code:'batch_source',name:'Fonte de teste batch',providerType:'generic',baseUrl:'https://example.test',operations:[
      {code:'read',name:'Ler',type:'list',path:'/records',method:'POST'},
      {code:'save_all',name:'Salvar lote',type:'batch',path:'/records',method:'POST'}
    ]};
    app.state.apiSources.push(source); app.state.apiSourceDetails.batch_source=source; app.refreshApiSourceSelectors();
    const entity={...app.collectEntityPayload(),code:'batch_entity',name:'Entidade teste lote',entityType:'api',apiSourceCode:'batch_source',apiListOperationCode:'read',apiBatchOperationCode:'save_all',fields:[
      {code:'record_id',label:'ID',dataType:'integer',primaryKey:true,readonlyField:true},
      {code:'title',label:'Descricao',dataType:'string',length:100,required:true}
    ]};
    app.state.entities.push({code:entity.code,name:entity.name,entityType:'api'});
    app.builderEntitySelect.setDataSource(new kendo.data.DataSource({data:app.state.entities}));
    app.populateEntityForm(entity,[]);
  });
  await page.waitForFunction(()=>window.programBuilderDemoApp.apiCatalogBatchOperationSelect.value()==='save_all');
  const result=await page.evaluate(()=>{
    const app=window.programBuilderDemoApp;
    app.populateProgramForm({programCode:'test_batch',programTitle:'Teste de lote',pageType:'crud',gridEditMode:'batch',builderEntityCode:'batch_entity',module:'cadastros',screenId:'tests.batch',allowCreate:true,allowUpdate:true,allowDelete:false});
    const program=app.collectProgramPayload(), entity=app.collectEntityPayload();
    const selectValues=app.gridEditModeSelect.dataSource.data().map(item=>item.value);
    const batchChoices=app.apiCatalogBatchOperationSelect.dataSource.data().map(item=>item.code);
    const modeVisible=app.gridEditModeField.css('display')!=='none';
    app.resetProgramForm();
    return {programMode:program.gridEditMode,entityBatch:entity.apiBatchOperationCode,entityRead:entity.apiListOperationCode,deleteAllowed:program.allowDelete,selectValues,batchChoices,modeVisible,resetMode:app.gridEditModeSelect.value()};
  });
  assert.deepEqual(result,{programMode:'batch',entityBatch:'save_all',entityRead:'read',deleteAllowed:false,selectValues:['form','batch'],batchChoices:['save_all'],modeVisible:true,resetMode:'form'});
  assert.deepEqual(errors,[]);
  console.log('PASS: Editor Kendo carrega binding batch, exporta programa/entidade e restaura modo form em novo rascunho.');
} finally {await browser.close();}
