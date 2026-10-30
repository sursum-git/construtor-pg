import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<div id="root"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudKendoFormRenderer.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudEngine.js','src/crud-engine/CrudDefinitionValidator.js']) await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async()=>{
  const notices=[],calls=[];CrudUtils.showMessage=m=>notices.push(m);
  const definition={id:'despesas',title:'Despesas',permissions:{read:true,create:true,edit:true},api:{read:{url:'/read'},create:{url:'/create'},'options.ids':{url:'/options',method:'POST'},'options.empresa_id':{url:'/empresas',method:'POST'}},
   dataModel:{primaryKey:'despesa_id',fields:{log_imposto:{type:'integer',label:'Imposto',editor:'dropdown',options:[{value:0,text:'Não'},{value:1,text:'Sim'}]},despesa_id:{type:'integer',label:'Despesa'},empresa_id:{type:'integer',label:'Empresa',editor:'dropdown',optionsSource:{endpointId:'options.empresa_id'}},calculada:{type:'boolean',label:'Calculada',defaultValue:false},percentual:{type:'number',label:'Percentual',decimals:4,defaultValue:0,visibleWhen:{field:'calculada',operator:'eq',value:true}},ids:{type:'array',itemType:'integer',label:'Despesas',editor:'multiselect',optionsSource:{endpointId:'options.ids'}}}},
   contextFields:[{field:'despesa_id',filter:true,createDefault:true,readonly:true,required:true}],
   query:{defaultPageSize:20},grid:{columns:[{field:'despesa_id'},{field:'log_imposto'}],rowLinks:[{id:'empresas',label:'Empresas',screenId:'ciw.empresas-despesa',parameters:[{field:'despesa_id',sourceField:'despesa_id'}]}]},
   form:{id:'form-test',mode:'popup',layout:'sections',fields:[{field:'despesa_id'},{field:'empresa_id'},{field:'calculada'},{field:'percentual'},{field:'ids'}],sections:[{id:'geral',fields:[{field:'despesa_id'},{field:'empresa_id'},{field:'calculada'},{field:'percentual'},{field:'ids'}]}]}};
  const client={request:async req=>{calls.push(req);if(req.url==='/options')return {data:[{value:2,text:'Água'},{value:3,text:'Energia'}]};if(req.url==='/empresas')return {data:[{value:8,text:'Empresa oito'}]};return {data:[{despesa_id:7,log_imposto:0,ids:[2,3]},{despesa_id:8,log_imposto:1,ids:[]}],total:2};}};
  const engine=Object.create(CrudEngine.prototype);engine.definition=definition;engine.options={contextValues:{despesa_id:'7'}};engine.initializeNavigationContext();
  const missing=Object.create(CrudEngine.prototype);missing.definition=definition;missing.options={contextValues:{}};let requiredRejected=false;try{missing.initializeNavigationContext();}catch(e){requiredRejected=true;}
  const invalid=Object.create(CrudEngine.prototype);invalid.definition=definition;invalid.options={contextValues:{despesa_id:'1e2'}};let invalidRejected=false;try{invalid.initializeNavigationContext();}catch(e){invalidRejected=true;}
  let opened;engine.formRenderer={open:(mode,data)=>{opened=data;}};engine.openCreate();
  engine.sortFiltersByDefinition=x=>x;engine.renderActiveFilters=()=>{};engine.updateLastUpdated=()=>{};let filters;engine.gridRenderer={setFilters:x=>{filters=x;}};engine.applyFilters([]);
  const form=new CrudKendoFormRenderer({definition,httpClient:client,contextValues:engine.contextValues});form.open('create',opened);await form.optionsReady;
  const multi=form.inputs.ids.input.data('kendoMultiSelect');multi.value([2,3]);const drop=form.inputs.empresa_id.input.data('kendoDropDownList');drop.value(8);
  const initialPercent=form.inputs.percentual.wrapper.css('display');
  form.inputs.calculada.input.data('kendoSwitch').check(true);form.inputs.calculada.input.data('kendoSwitch').trigger('change');
  const visiblePercent=form.inputs.percentual.wrapper.css('display');form.inputs.percentual.input.data('kendoNumericTextBox').value(1.2345);
  const values=form.collectValues();const readonly=form.inputs.despesa_id.readonly;
  multi.value([]);const cleared=form.collectValues().ids;
  const renderer=new CrudKendoGridRenderer({definition,httpClient:client});renderer.render($('#root'));await renderer.waitForInitialRead();
  const row=renderer.grid.dataSource.at(0);const preserved=Array.from(row.ids);const link=renderer.grid.tbody.find('a.crud-row-link').first();
  const href=link.attr('href');const label=link.text();
  const visibleFlags=renderer.grid.tbody.find('tr[data-uid]').map((i,tr)=>$(tr).find('td').eq(1).text()).get();
  const labels=[renderer.formatFieldValue('log_imposto',{log_imposto:0}),renderer.formatColumnValue('log_imposto',1),renderer.formatExportValue({log_imposto:0},{field:'log_imposto'})];
  const integerPreserved=typeof renderer.grid.dataSource.at(0).log_imposto==='number'&&renderer.grid.dataSource.at(0).log_imposto===0;
  form.close(true);renderer.destroy();
  const relation=CrudUtils.clone(definition);
  relation.dataModel.primaryKey='empresa_despesa_id';
  relation.dataModel.fields.despesa_id.editor='dropdown';relation.dataModel.fields.despesa_id.optionsSource={endpointId:'options.despesa_id',copyFields:['eh_imposto']};
  relation.dataModel.fields.eh_imposto={type:'boolean',label:'É imposto',readonly:true,editable:false};
  relation.dataModel.fields.empresa_id.readonlyOnEdit=true;
  relation.dataModel.fields.percentual.enabledWhen={field:'eh_imposto',operator:'eq',value:true};
  relation.dataModel.fields.percentual.visibleWhen=null;
  relation.api['options.despesa_id']={url:'/despesa-options',method:'POST'};
  const relClient={request:async req=>req.url==='/despesa-options'?{data:[{value:7,text:'Despesa sete',fields:{eh_imposto:true}}]}:client.request(req)};
  const relForm=new CrudKendoFormRenderer({definition:relation,httpClient:relClient,contextValues:{despesa_id:7}});
  relForm.open('create',{despesa_id:7});await relForm.optionsReady;
  const copied=relForm.collectValues().eh_imposto,enabled=!relForm.inputs.percentual.readonly;
  const parentLabel=relForm.inputs.despesa_id.input.data('kendoDropDownList').text();relForm.close(true);
  relForm.open('edit',{empresa_despesa_id:99,despesa_id:7,empresa_id:8,ids:[],calculada:false});await relForm.optionsReady;
  const immutable=relForm.inputs.empresa_id.readonly;relForm.close(true);
  const badForm=new CrudKendoFormRenderer({definition,httpClient:{request:async()=>{throw new Error('Falha teste');}}});
  badForm.open('create',{});await badForm.optionsReady;const blocked=badForm.optionsFailed;badForm.save();badForm.close(true);
  const validator=new CrudDefinitionValidator(),validErrors=[];validator.validateDeclarativeCapabilities(definition,validErrors);
  const badDefinition=CrudUtils.clone(definition);badDefinition.grid.rowLinks[0].url='javascript:bad';const invalidErrors=[];validator.validateDeclarativeCapabilities(badDefinition,invalidErrors);
  return {visibleFlags,labels,integerPreserved,copied,enabled,parentLabel,immutable,blocked,validErrors,invalidErrors,initialPercent,visiblePercent,requiredRejected,invalidRejected,opened,filters,values,readonly,cleared,preserved,href,label,notices,calls:calls.map(c=>c.url)};
 });
 assert.deepEqual(result.visibleFlags,['Não','Sim']);assert.deepEqual(result.labels,['Não','Sim','Não']);assert.equal(result.integerPreserved,true);
 assert.equal(result.copied,true);assert.equal(result.enabled,true);assert.equal(result.parentLabel,'Despesa sete');assert.equal(result.immutable,true);assert.equal(result.blocked,true);assert.deepEqual(result.validErrors,[]);assert.ok(result.invalidErrors.length>0);
 assert.equal(result.initialPercent,'none');assert.notEqual(result.visiblePercent,'none');assert.equal(result.values.percentual,1.2345);assert.equal(result.values.calculada,true);
 assert.equal(result.requiredRejected,true);assert.equal(result.invalidRejected,true);
 assert.deepEqual(result.opened,{despesa_id:7});assert.equal(result.readonly,true);
 assert.equal(result.filters.some(x=>x.field==='despesa_id'&&x.value===7),true);
 assert.deepEqual(result.values.ids,[2,3]);assert.equal(result.values.empresa_id,8);assert.deepEqual(result.cleared,[]);assert.deepEqual(result.preserved,[2,3]);
 assert.equal(result.label,'Empresas');assert.match(result.href,/screenId=ciw.empresas-despesa/);assert.match(result.href,/context__despesa_id=7/);assert.deepEqual(errors,[]);
 console.log('PASS: contexto obrigatório, defaults/filtro fixo, links, opções remotas e integer[].');
}finally{await browser.close();}
