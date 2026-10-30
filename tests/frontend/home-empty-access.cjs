const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const canonical='D:/opencode/construtor-pg',source=process.argv[2]||canonical;
const win={location:{origin:'http://127.0.0.1:8765',href:'http://127.0.0.1:8765/production/home.html'}};
const context=vm.createContext({window:win,console,URL,Promise,jQuery:()=>{throw Error('DOM nao esperado no teste puro');}});
for(const [root,file]of [[canonical,'src/crud-engine/CrudUtils.js'],[source,'src/home-engine/HomeDefinitionValidator.js'],[source,'src/home-engine/HomeEngine.js']])vm.runInContext(fs.readFileSync(path.join(root,file),'utf8'),context,{filename:file});
const base=()=>({schemaVersion:'1.0',pageType:'home',app:{title:'Home'},layout:{initialProgramId:'painel'},navigation:{modules:[{id:'ciw',title:'CIW'}],groups:[]},programs:[],permissions:{}});
const validator=new win.HomeDefinitionValidator();const cases=[];
const test=(name,fn)=>cases.push([name,fn]);
test('Home sem programas apos filtro de permissoes e valida',()=>assert.equal(validator.validate(base()),true));
test('flag booleana de troca de senha na identidade nao e segredo',()=>{const d=base();d.currentUser={forcePasswordChange:false};d.user={forcePasswordChange:true};assert.equal(validator.validate(d),true);});
test('flag textual ou campo de senha continuam rejeitados',()=>{for(const identity of [{forcePasswordChange:'segredo'},{password:'segredo'},{nested:{forcePasswordChange:false}}]){const d=base();d.currentUser=identity;assert.throws(()=>validator.validate(d));}});
test('flag fora da identidade continua rejeitada',()=>{const d=base();d.app.forcePasswordChange=false;assert.throws(()=>validator.validate(d));});
test('lista de programas continua obrigatoria',()=>{const d=base();delete d.programs;assert.throws(()=>validator.validate(d));});
test('programas malformados continuam rejeitados',()=>{const d=base();d.programs={};assert.throws(()=>validator.validate(d));});
test('grupo apontando programa nao autorizado continua rejeitado',()=>{const d=base();d.navigation.groups=[{id:'x',title:'X',moduleId:'ciw',items:[{programId:'negado'}]}];assert.throws(()=>validator.validate(d));});
test('programa disponivel continua exigindo grupo',()=>{const d=base();d.programs=[{id:'p',title:'Empresas',type:'crud',screenId:'ciw.empresas'}];assert.throws(()=>validator.validate(d));});
test('lista de grupos continua obrigatoria mesmo sem programas',()=>{const d=base();delete d.navigation.groups;assert.throws(()=>validator.validate(d));});
test('Home com programa e grupo validos permanece valida',()=>{const d=base();d.programs=[{id:'p',title:'Empresas',type:'crud',screenId:'ciw.empresas'}];d.navigation.groups=[{id:'g',title:'Cadastros',moduleId:'ciw',items:[{programId:'p'}]}];assert.equal(validator.validate(d),true);});
test('Home vazia nao tenta abrir programa inicial obsoleto',async()=>{
 let opened=0;const messages=[];
 const home={definition:base(),getRequestedInitialProgramId:()=>'',getSavedInitialProgramId:()=>'',openProgram:()=>{opened++;return Promise.resolve();},renderContentMessage:(message,type)=>messages.push({message,type})};
 await win.HomeEngine.prototype.openInitialProgram.call(home);
 assert.equal(opened,0);assert.equal(messages.length,1);assert.match(messages[0].message,/Nenhuma aplicação/);assert.notEqual(messages[0].type,'error');
});
test('Home com programa mantem abertura inicial normal',async()=>{
 const d=base();d.programs=[{id:'p',title:'Empresas'}];d.layout.initialProgramId='p';let opened;
 const home={definition:d,getRequestedInitialProgramId:()=>'',getSavedInitialProgramId:()=>'',openProgram:(id,options)=>{opened={id,options};return Promise.resolve();},renderContentMessage:()=>{throw Error('Mensagem vazia indevida');}};
 await win.HomeEngine.prototype.openInitialProgram.call(home);assert.equal(opened.id,'p');assert.equal(opened.options.syncModule,false);
});
test('programa inicial filtrado por acesso recai na primeira aplicacao disponivel',async()=>{
 const d=base();d.programs=[{id:'ciw-empresas',title:'Empresas'}];let opened;
 const home={definition:d,getRequestedInitialProgramId:()=>'',getSavedInitialProgramId:()=>'',openProgram:(id)=>{opened=id;return Promise.resolve();}};
 await win.HomeEngine.prototype.openInitialProgram.call(home);assert.equal(opened,'ciw-empresas');
});
(async()=>{let failures=0;for(const[name,fn]of cases){try{await fn();console.log('PASS '+name);}catch(e){failures++;console.log('FAIL '+name+': '+e.message);}}console.log(`${cases.length-failures}/${cases.length} PASS`);process.exitCode=failures?1:0;})();
