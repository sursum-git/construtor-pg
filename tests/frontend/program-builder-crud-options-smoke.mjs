import assert from 'node:assert/strict';
import path from 'node:path';
import {pathToFileURL} from 'node:url';
import {chromium} from 'playwright';

const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
  const page=await browser.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(pathToFileURL(path.resolve('examples/pages/program-builder-technical-properties.html')).href);
  await page.waitForFunction(()=>window.programBuilderDemoApp?.gridEditModeSelect);
  const result=await page.evaluate(()=>{
    const app=window.programBuilderDemoApp;
    const crudOptions={pageSize:30,gridFields:[{field:'codigo',hidden:false,width:160}],filterFields:['codigo'],defaultGroup:[{field:'codigo',dir:'asc'}]};
    const version={id:12,status:'draft',programCode:'op1000',programTitle:'Consulta',pageType:'crud',module:'operacional',screenId:'tests.query-options',crudOptions};
    app.populateProgramForm(version);
    const direct=app.collectProgramPayload().crudOptions;
    app.populateProgramForm({...version,crudOptions:undefined,builderConfig:{crudOptions}});
    const persisted=app.collectProgramPayload().crudOptions;
    app.resetProgramForm();
    const reset=app.collectProgramPayload().crudOptions;
    return {crudOptions,direct,persisted,reset};
  });
  assert.deepEqual(result.direct,result.crudOptions);
  assert.deepEqual(result.persisted,result.crudOptions);
  assert.equal(result.reset,null);
  assert.deepEqual(errors,[]);
  console.log('PASS: editor preserves CRUD query options from imported and persisted drafts, reset clears previous options.');
} finally {await browser.close();}
