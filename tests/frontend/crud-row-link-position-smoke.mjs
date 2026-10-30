
import assert from 'node:assert/strict';import path from 'node:path';import {chromium} from 'playwright';
const browser=await chromium.launch({headless:true,channel:'msedge'});
try{
 const page=await browser.newPage();await page.setContent('<div id="root" style="width:900px"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudDefinitionValidator.js','src/crud-engine/CrudKendoGridRenderer.js'])await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async()=>{
  const fields=Object.fromEntries(Array.from({length:20},(_,i)=>['field_'+(i+1),{type:'integer',label:'Campo '+(i+1)}]));
  const link=(id,label,position)=>({id,label,screenId:'ciw.panel',parameters:[{field:'id',sourceField:'field_1'}],...(position?{position}:{})});
  const definition={schemaVersion:'1.0',pageType:'crud',id:'row-links',module:'tests',entity:'rows',title:'Consulta',permissions:{read:true,create:false,edit:false,delete:false},api:{read:{url:'/read'}},dataModel:{primaryKey:'field_1',fields},query:{defaultPageSize:20},userLayout:{grid:{columns:{order:Object.keys(fields).reverse()}}},grid:{columns:Object.keys(fields).map(field=>({field,title:fields[field].label,width:160})),rowActions:[{action:'view',label:'Consultar',permission:'read'}],bulkActions:{enabled:true,actions:[{id:'export',label:'Exportar',action:'export',permission:'read'}]},rowLinks:[link('end','Fim','end'),link('panel','Painel','start'),link('other','Outro inicio','start'),link('legacy','Legado')]},form:{id:'links-form',mode:'popup',layout:'tabs',tabs:[]}};
  const validator=new CrudDefinitionValidator();validator.validate(definition);
  const renderer=new CrudKendoGridRenderer({definition,httpClient:{request:async()=>({data:[{field_1:17}],total:1})}});renderer.render($('#root'));await renderer.waitForInitialRead();
  const columns=renderer.grid.columns.map(c=>c.selectable?'bulk':c.field||c.title);
  const links=renderer.grid.tbody.find('a.crud-row-link').map((_,a)=>$(a).text()).get();renderer.destroy();
  const invalid=['middle',null,[],false].map(position=>{const bad=CrudUtils.clone(definition);bad.grid.rowLinks[1].position=position;try{validator.validate(bad);return false;}catch{return true;}});
  return {columns,links,invalid};
 });
 assert.deepEqual(result.columns.slice(0,5),['Acoes','bulk','Painel','Outro inicio','field_20']);assert.deepEqual(result.columns.slice(-2),['Fim','Legado']);assert.deepEqual(result.links,['Painel','Outro inicio','Fim','Legado']);assert.ok(result.invalid.every(Boolean));
 console.log('PASS: start links follow actions/bulk and precede twenty data fields, end/default links retained, positions closed.');
}finally{await browser.close();}
