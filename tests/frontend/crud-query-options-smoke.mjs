import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';

// Removing the default-group fallback must break this test; a saved empty group
// must continue to suppress the configured default after the user clears it.
const browser = await chromium.launch({headless: true, channel: 'msedge'});
try {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.setContent('<div id="root"></div>');
  for (const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudKendoGridRenderer.js']) {
    await page.addScriptTag({path: path.resolve(file)});
  }
  const result = await page.evaluate(async () => {
    const fields = Object.fromEntries(Array.from({length:20}, (_,i) => ['field_'+(i+1), {type:'integer',label:'Campo '+(i+1),readonly:true}]));
    fields.json_data = {type:'json',label:'JSON'};
    const definition = {schemaVersion:'1.0', pageType:'crud', id:'query-options-test', module:'tests',entity:'query',title:'Consulta',
      api:{read:{url:'/read'}},permissions:{read:true,create:false,edit:false,delete:false},dataModel:{primaryKey:'field_1',fields},
      query:{defaultPageSize:30, defaultSort:[{field:'field_1',dir:'asc'}],defaultGroup:[{field:'field_2',dir:'asc'},{field:'field_3',dir:'asc'}]},
      userLayout:{enabled:true,version:2,source:'default',activeLayoutId:null,activeGroupId:null,savedGroups:[],grid:{columns:{order:[],hidden:[],widths:[],frozen:[],added:[]},sort:[],group:[],filter:null,groupAggregates:[]}},
      grid:{pageable:true,sortable:true,groupable:true,columns:Array.from({length:20},(_,i)=>({field:'field_'+(i+1),title:'Campo '+(i+1),visible:i<12}))},form:{id:"query-options-form",tabs:[]}};
    try { new CrudDefinitionValidator().validate(definition); } catch(e) { throw new Error(JSON.stringify(e.payload || e)); }
    const renderer = new CrudKendoGridRenderer({definition,httpClient:{request:async ()=>({data:[],total:0})}});
    renderer.render($('#root'));
    await renderer.waitForInitialRead();
    const groups = renderer.grid.dataSource.group().map(g=>({field:g.field,dir:g.dir}));
    const pageSize=renderer.grid.dataSource.pageSize();
    const pageSizes=renderer.grid.options.pageable.pageSizes;
    const visible=renderer.grid.columns.filter(c=>c.field&&!c.hidden).length;
    const hidden=renderer.grid.columns.filter(c=>c.field&&c.hidden).length;
    renderer.destroy();
    const make = userLayout => new CrudKendoGridRenderer({definition:Object.assign({},definition,{userLayout}),httpClient:{}}).getInitialGroup().map(g=>({field:g.field,dir:g.dir}));
    const cleared=make({grid:{group:[]}});
    const clearedGlobal=make({source:'user_global',activeLayoutId:'global-layout',grid:{group:[]}});
    const activeEmptyPreset=make({source:'default',activeGroupId:'preset-empty',savedGroups:[{id:'preset-empty',group:[],aggregates:[]}],grid:{group:[]}});
    const own=make({grid:{group:[{field:'field_4',dir:'desc'}]}});
    const missing=make({grid:{}});
    const invalids=[true,[{field:'constructor',dir:'asc'}],[{field:'missing',dir:'asc'}],[{field:'json_data',dir:'asc'}],[{field:'field_1',dir:'evil'}],
      [{field:'field_1',dir:'asc',compare:'free'}],[{field:'field_1',dir:'asc'},{field:'field_1',dir:'asc'}],
      [1,2,3,4].map(i=>({field:'field_'+i,dir:'asc'}))];
    const rejected=invalids.map(defaultGroup=>{
      const bad=CrudUtils.clone(definition);bad.query.defaultGroup=defaultGroup;
      try {new CrudDefinitionValidator().validate(bad);return false;} catch{return true;}
    });
    return {groups,pageSize,pageSizes,visible,hidden,cleared,clearedGlobal,activeEmptyPreset,own,missing,rejected};
  });
  assert.deepEqual(result.groups,[{field:'field_2',dir:'asc'},{field:'field_3',dir:'asc'}]);
  assert.equal(result.pageSize,30);assert.ok(result.pageSizes.includes(30));assert.equal(result.visible,12);assert.equal(result.hidden,8);
  assert.deepEqual(result.cleared,[]);assert.deepEqual(result.clearedGlobal,[]);assert.deepEqual(result.activeEmptyPreset,[]);
  assert.deepEqual(result.own,[{field:'field_4',dir:'desc'}]);
  assert.deepEqual(result.missing,result.groups);
  assert.ok(result.rejected.every(Boolean));assert.deepEqual(errors,[]);
  console.log('PASS: query pageSize30, 12 visible/8 hidden; default groups, saved override and explicitly empty layout; closed group validation.');
} finally {await browser.close();}
