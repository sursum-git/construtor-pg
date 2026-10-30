import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const scope={window:{},console,URL};vm.createContext(scope);
for(const file of ['src/crud-engine/CrudUtils.js','src/process-engine/ProcessDefinitionValidator.js'])vm.runInContext(fs.readFileSync(file,'utf8'),scope);
const definition={schemaVersion:'1.0',pageType:'process',program:{title:'Processo'},currentUser:{id:1,forcePasswordChange:false},dataSource:{api:{process:{endpointId:'run',method:'POST'}}},process:{parameters:{fields:[{field:'container_id',type:'integer',required:true,readonly:true}]},wait:{mode:'none'},result:{type:'message'}},contextFields:[{field:'container_id',required:true,readonly:true}]};
for(const value of [true,false]){const d=structuredClone(definition);d.currentUser.forcePasswordChange=value;assert.equal(new scope.window.ProcessDefinitionValidator().validate(d),true);}
for(const mutate of [x=>x.currentUser.forcePasswordChange='secret',x=>x.currentUser.password='secret',x=>x.process.parameters.forcePasswordChange=true,x=>x.apiToken='secret']){const d=structuredClone(definition);mutate(d);assert.throws(()=>new scope.window.ProcessDefinitionValidator().validate(d));}
console.log('PASS: runtime session boolean accepted; credentials, wrong types and other paths remain rejected.');
