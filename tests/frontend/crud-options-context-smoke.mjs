import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
 const page=await browser.newPage();await page.setContent('<div id="root"></div>');
 for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudKendoFormRenderer.js']) await page.addScriptTag({path:path.resolve(file)});
 const result=await page.evaluate(async()=>{
  const calls=[],notices=[];CrudUtils.showMessage=m=>notices.push(m);
  const definition={id:'items',title:'Itens',permissions:{create:true,edit:true,read:true},api:{'options.item_proc_compra_id':{url:'/options',method:'POST'}},
   contextFields:[{field:'container_id',required:true,readonly:true}],dataModel:{primaryKey:'item_container_id',fields:{
    container_id:{type:'integer',label:'Container',readonly:true},item_proc_compra_id:{type:'integer',label:'Item',editor:'dropdown',readonlyOnEdit:true,
     optionsSource:{endpointId:'options.item_proc_compra_id',copyFields:['saldo_disponivel_texto']}},saldo_disponivel_texto:{type:'string',label:'Saldo',readonly:true}}},
   form:{mode:'embedded',layout:'sections',sections:[{id:'all',fields:[{field:'container_id'},{field:'item_proc_compra_id'},{field:'saldo_disponivel_texto'}]}]}};
  const client={request:async req=>{calls.push(req);return {data:[{value:17,text:'ERP17 / saldo 1,25',fields:{saldo_disponivel_texto:'1,25'}}]};}};
  const make=(ctx,data,mode='create')=>{const form=new CrudKendoFormRenderer({definition,host:$('#root'),httpClient:client,contextValues:ctx});form.open(mode,data);return form;};
  const create=make({container_id:123,undeclared:999},{container_id:123});await create.optionsReady;
  const sent=calls[0].data;create.inputs.item_proc_compra_id.input.data('kendoDropDownList').value(17);create.inputs.item_proc_compra_id.input.data('kendoDropDownList').trigger('change');
  const saldo=create.data.saldo_disponivel_texto;
  const edit=make({container_id:123},{item_container_id:20,container_id:123,item_proc_compra_id:17},'edit');await edit.optionsReady;
  const readonly=edit.inputs.item_proc_compra_id.input.prop('disabled')===true;
  const before=calls.length;const invalid=make({container_id:'1e2'},{container_id:123});await invalid.optionsReady;
  const missing=make({},{container_id:123});await missing.optionsReady;
  const conflict=make({container_id:123},{container_id:999});await conflict.optionsReady;
  return {sent,saldo,readonly,invalid:invalid.optionsFailed,missing:missing.optionsFailed,conflict:conflict.optionsFailed,noHttp:calls.length===before};
 });
 assert.deepEqual(result.sent,{context:{container_id:123}});assert.equal(result.saldo,'1,25');assert.equal(result.readonly,true);
 assert.equal(result.invalid,true);assert.equal(result.missing,true);assert.equal(result.conflict,true);assert.equal(result.noHttp,true);
 console.log('PASS: opções contextuais validadas, apresentação e compra imutável na edição.');
}finally{await browser.close();}

