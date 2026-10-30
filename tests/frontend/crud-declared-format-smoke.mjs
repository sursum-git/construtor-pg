import assert from 'node:assert/strict';
import path from 'node:path';
import {chromium} from 'playwright';

// A hardcoded n2 or UTC parsing of date-only payloads must break these checks.
const browser=await chromium.launch({headless:true,channel:'msedge'});
try {
  const context=await browser.newContext({timezoneId:'America/Sao_Paulo'});
  const page=await context.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setContent('<div id="root"></div>');
  for(const file of ['vendor/jquery/jquery-4.0.0.min.js','kendo/js/kendo.all.min.js','kendo/js/cultures/kendo.culture.pt-BR.min.js','src/crud-engine/CrudUtils.js','src/crud-engine/CrudKendoGridRenderer.js','src/crud-engine/CrudKendoFormRenderer.js']) {
    await page.addScriptTag({path:path.resolve(file)});
  }
  const result=await page.evaluate(async ()=>{
    kendo.culture('pt-BR');
    const definition={id:'declared-format',title:'Consulta',permissions:{read:true,create:false,edit:false,delete:false},
      api:{read:{url:'/read'}},dataModel:{primaryKey:'id',fields:{id:{type:'integer',label:'ID',format:'n5',decimals:5},
        cotacao:{type:'decimal',label:'Cotação',format:'n5',decimals:5,readonly:true},
        chegada:{type:'date',label:'Chegada',readonly:true},ativo:{type:'boolean',label:'Ativo',readonly:true,nullable:true},
        status_code:{type:'integer',label:'Sit.Acompanhamento',readonly:true}}},
      query:{defaultPageSize:30,defaultGroup:[{field:'status_code',dir:'asc'}]},
      grid:{groupable:true,columns:[{field:'id',title:'ID'},{field:'cotacao',title:'Cotação'},{field:'chegada',title:'Chegada'},{field:'ativo',title:'Ativo'}]},
      form:{id:'format-form',layout:'tabs',title:{view:'Detalhe'},tabs:[{id:'geral',title:'Geral',fields:[{field:'cotacao',readonly:true},{field:'chegada',readonly:true},{field:'ativo',readonly:true}]}]}};
    const records=[{id:1,cotacao:5.12345,chegada:'2026-12-01',ativo:false,status_code:7},{id:2,cotacao:0,chegada:'2027-01-15',ativo:true,status_code:7},{id:3,cotacao:0,chegada:null,ativo:null,status_code:7}];
    const renderer=new CrudKendoGridRenderer({definition,httpClient:{request:async()=>({data:records,total:3})}});
    renderer.render($('#root'));await renderer.waitForInitialRead();
    const rows=renderer.grid.tbody.find('tr[data-uid]').map((_,row)=>$(row).find('td').filter((__,td)=>!td.classList.contains('k-group-cell')&&td.style.display!=='none').map((__,td)=>$(td).text()).get()).get();
    const booleanRows=renderer.grid.tbody.find('tr[data-uid]').map((_,row)=>$(row).find('td').filter((__,td)=>!td.classList.contains('k-group-cell')&&td.style.display!=='none').eq(3).text()).get();
    const rawBooleans=Array.from(renderer.grid.dataSource.data()).map(row=>row.ativo);
    const groupText=renderer.grid.tbody.find('tr.k-grouping-row').text();
    const chipText=renderer.grid.wrapper.find('.k-grouping-header').text();
    const technicalGroupColumn=renderer.grid.columns.find(c=>c.field==='status_code');
    const groupColumn=technicalGroupColumn?{hidden:technicalGroupColumn.hidden,menu:technicalGroupColumn.menu}:null;
    const form=new CrudKendoFormRenderer({definition,httpClient:{}});
    const view=record=>{form.open('view',record);const values={};for(const key of ['cotacao','chegada','ativo'])values[key]=form.inputs[key].readonlyElement.text();form.close(true);return values;};
    const one=view(records[0]),two=view(records[1]);
    const batchDefinition=CrudUtils.clone(definition);batchDefinition.grid.editing={mode:'batch'};batchDefinition.dataModel.fields.ativo.editable=true;
    const batch=new CrudKendoGridRenderer({definition:batchDefinition,httpClient:{}}),cell=$('<td>');
    batch.buildColumns().find(c=>c.field==='ativo').editor(cell,{field:'ativo'});
    const checkboxPreserved=cell.find('input[type=checkbox]').length===1&&Boolean(cell.find('input').data('kendoCheckBox'));kendo.destroy(cell);
    const result={checkboxPreserved,booleanRows,rawBooleans,mobileBooleanFalse:renderer.formatFieldValue('ativo',records[0]),mobileBooleanTrue:renderer.formatFieldValue('ativo',records[1]),mobileBooleanNull:renderer.formatFieldValue('ativo',records[2]),integerZero:renderer.formatFieldValue('status_code',{status_code:0}),offset:new Date('2026-12-01T12:00:00Z').getTimezoneOffset(),rows,one,two,groupText,chipText,groupColumn,
      mobile:renderer.formatFieldValue('cotacao',records[0]),mobileZero:renderer.formatFieldValue('cotacao',records[1]),mobileDate:renderer.formatFieldValue('chegada',records[0]),
      formats:[CrudUtils.toKendoFormat('n5','decimal'),CrudUtils.toKendoFormat(undefined,'number',5),CrudUtils.toKendoFormat(undefined,'number',0),CrudUtils.toKendoFormat('n10','decimal'),CrudUtils.toKendoFormat('n11','decimal'),CrudUtils.toKendoFormat('evil','decimal'),CrudUtils.toKendoFormat(undefined,'number'),CrudUtils.toKendoFormat('currency','decimal')],
      integerReadonly:form.formatReadonly({type:'integer',format:'n5',decimals:5},37),integerPlainFormat:CrudUtils.toKendoFormat(undefined,'integer')||null,integerFormat:CrudUtils.toKendoFormat('n5','integer',5),legacyReadonly:form.formatReadonly({type:'decimal'},5.12345),legacyString:form.formatReadonly({type:'string'},'texto'),nullReadonly:form.formatReadonly({type:'decimal',format:'n5'},null),
      timestamp:kendo.toString(CrudUtils.normalizeDateValue('2026-12-01T00:00:00Z'),'dd/MM/yyyy HH:mm'),dateOnly:kendo.toString(CrudUtils.normalizeDateValue('2026-12-01'),'dd/MM/yyyy')};
    renderer.destroy();return result;
  });
  assert.equal(result.checkboxPreserved,true);assert.deepEqual(result.booleanRows,['Não','Sim','']);assert.deepEqual(result.rawBooleans,[false,true,null]);assert.equal(result.mobileBooleanFalse,'Não');assert.equal(result.mobileBooleanTrue,'Sim');assert.equal(result.mobileBooleanNull,'');assert.equal(result.integerZero,'0');
  assert.equal(result.offset,180);
  assert.deepEqual(result.formats,['{0:n5}','{0:n5}','{0:n0}','{0:n10}','{0:n2}','{0:n2}','{0:n2}','{0:c2}']);
  assert.ok(result.rows.includes('5,12345'));assert.ok(result.rows.includes('0,00000'));
  assert.deepEqual(result.one,{cotacao:'5,12345',chegada:'01/12/2026',ativo:'Não'});
  assert.deepEqual(result.two,{cotacao:'0,00000',chegada:'15/01/2027',ativo:'Sim'});
  assert.equal(result.mobile,'5,12345');assert.equal(result.mobileZero,'0,00000');assert.equal(result.mobileDate,'01/12/2026');
  assert.equal(result.integerFormat,'{0:n0}');assert.equal(result.integerPlainFormat,null);assert.equal(result.integerReadonly,'37');assert.equal(result.legacyReadonly,'5.12345');assert.equal(result.legacyString,'texto');assert.equal(result.nullReadonly,'');
  assert.equal(result.dateOnly,'01/12/2026');assert.equal(result.timestamp,'30/11/2026 21:00');
  assert.match(result.groupText,/Sit\.Acompanhamento/);assert.match(result.chipText,/Sit\.Acompanhamento/);
  assert.deepEqual(result.groupColumn,{hidden:true,menu:false});
  assert.deepEqual(errors,[]);
  console.log('PASS: pt-BR precision 0..10, n5 grid/form/mobile incl zero, boolean labels, DATE calendar in UTC-3, datetime instant, group labels outside visible columns.');
} finally {await browser.close();}
