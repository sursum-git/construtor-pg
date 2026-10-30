
import assert from 'node:assert/strict';import path from 'node:path';import {chromium} from 'playwright';
const browser=await chromium.launch({headless:true,channel:'msedge'});
try{
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('http://crud-return.test/**',route=>route.fulfill({status:200,contentType:'text/html',body:'<div id="root"></div>'}));await page.goto('http://crud-return.test/production/app.html?screenId=ciw.query');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/page-engine/PageDefinitionNormalizer.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudConfigLoader.js','src/crud-engine/CrudDefinitionLoader.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudLayoutManager.js','src/crud-engine/CrudToolbarRenderer.js','src/crud-engine/CrudFilterRenderer.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudKendoFormRenderer.js','src/crud-engine/CrudEngine.js'])await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async()=>{
  const calls=[];const definition={schemaVersion:'1.0',pageType:'crud',screenId:'ciw.query',id:'query',module:'tests',entity:'records',title:'Consulta',permissions:{read:true,create:false,edit:false,delete:false},api:{read:{url:'/read'}},dataModel:{primaryKey:'id',fields:{id:{type:'integer',label:'ID'},name:{type:'string',label:'Nome'}}},query:{defaultPageSize:20},filter:{type:'window',fields:[{id:'name',field:'name',label:'Nome',type:'string'}]},grid:{pageable:true,sortable:true,columns:[{field:'id',title:'ID'},{field:'name',title:'Nome'}],rowLinks:[{id:'panel',label:'Painel',screenId:'ciw.panel',parameters:[{field:'id',sourceField:'id'}]}]},form:{id:'query-form',mode:'popup',layout:'sections',sections:[],fields:[]}};
  const client={request:async request=>{calls.push(JSON.parse(JSON.stringify(request.data)));return {data:[{id:17,name:'Selecionado'}],total:150};}};
  const make=options=>new CrudEngine({root:'#root',definition,config:{},runtimeMessages:false,httpClient:client,...options});
  const source=await make({}).init();source.applyFilters([{id:'name',field:'name',operator:'contains',value:'Fornecedor'}]);
  source.gridRenderer.grid.dataSource.query({page:4,pageSize:10,sort:[{field:'name',dir:'desc'}],filter:{logic:'and',filters:[{field:'id',operator:'gt',value:10}]},group:[]});
  await new Promise(r=>setTimeout(r,20));
  const link=source.gridRenderer.grid.tbody.find('a.crud-row-link').first();const click=$.Event('click');click.preventDefault();link.trigger(click);
  source.gridRenderer.destroy();source.filterRenderer.destroy();$('#root').empty();
  const before=calls.length;const restored=await make({restoreFrom:'ciw.panel'}).init();
  const first=calls[before];const state={first,count:calls.length-before,page:restored.gridRenderer.grid.dataSource.page(),filter:restored.currentFilters,sort:restored.gridRenderer.grid.dataSource.sort().map(s=>({field:s.field,dir:s.dir}))};
  restored.gridRenderer.destroy();restored.filterRenderer.destroy();$('#root').empty();
  const consumed=await make({restoreFrom:'ciw.panel'}).init();state.consumedPage=consumed.gridRenderer.grid.dataSource.page();consumed.gridRenderer.destroy();consumed.filterRenderer.destroy();
  state.invalidRejected=typeof CrudUtils.saveGridReturnState==='function'&&CrudUtils.saveGridReturnState(definition,'ciw.panel',{page:1,pageSize:20,sort:[{field:'unknown',dir:'asc'}],group:[],filter:null,filters:[]})===false;
  state.wrongOriginRejected=typeof CrudUtils.readGridReturnState==='function'&&CrudUtils.readGridReturnState(definition,'https://evil.example')===null;
  const validState={page:2,pageSize:10,sort:[],group:[],filter:null,filters:[]};
  CrudUtils.saveGridReturnState(definition,'ciw.panel',validState);
  state.wrongTargetRejected=CrudUtils.readGridReturnState(definition,'ciw.other')===null;
  state.legitimateTargetPreserved=CrudUtils.readGridReturnState(definition,'ciw.panel').page===2;
  CrudUtils.saveGridReturnState(definition,'ciw.panel',validState);
  const key='crud.return-grid.ciw.query',stored=JSON.parse(sessionStorage.getItem(key));stored.savedAt=Date.now()-1800001;sessionStorage.setItem(key,JSON.stringify(stored));
  state.expiredRejected=CrudUtils.readGridReturnState(definition,'ciw.panel')===null;
  return state;
 });
 assert.equal(result.count,1);assert.equal(result.first.page,4);assert.equal(result.first.pageSize,10);assert.equal(result.page,4);assert.equal(result.filter[0].value,'Fornecedor');assert.deepEqual(result.sort,[{field:'name',dir:'desc'}]);assert.equal(result.first.filter.filters[0].field,'id');assert.equal(result.consumedPage,1);assert.equal(result.invalidRejected,true);assert.equal(result.wrongOriginRejected,true);assert.equal(result.wrongTargetRejected,true);assert.equal(result.legitimateTargetPreserved,true);assert.equal(result.expiredRejected,true);assert.deepEqual(errors,[]);
 console.log('PASS: rowLink round trip restores page/pageSize/filter/sort in one initial query; state consumed and unknown fields/origin rejected.');
}finally{await browser.close();}
