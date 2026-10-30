(function (global) {
  "use strict";
  if (global.document.body.dataset.exampleId !== "processamento-opcoes-dependentes" || global.document.body.dataset.production === "true" || /\/production\//.test(global.location.pathname)) { throw new Error("Cliente disponível somente no exemplo local identificado."); }
  const definition = {"schemaVersion":"1.0","pageType":"process","screenId":"demo.process-dependent-options","program":{"id":"demo-process-dependent-options","module":"operacional","entity":"demo_dependent_options","title":"Prioridades fictícias","version":"1.0.0","subtitle":"Exemplo local; nenhuma regra comercial é executada.","icon":"check","permission":"read"},"permissions":{"read":true,"process":true},"contextFields":[{"field":"container_id","required":true,"readonly":true}],"dataModel":{"primaryKey":"container_id","fields":{"container_id":{"label":"Container MED","type":"integer","nullable":false,"readonly":true,"editable":false,"validation":{"required":true}},"ordem_campo":{"label":"Ordenar despesas por","type":"text","nullable":false,"editor":"dropdown","defaultValue":"ordem","options":[{"value":"ordem","text":"Ordem"},{"value":"despesa_id","text":"Despesa"},{"value":"log_calculada","text":"Calculada"},{"value":"perc_calculo","text":"Percentual"}],"validation":{"required":true}},"ordem_direcao":{"label":"Direcao","type":"text","nullable":false,"editor":"dropdown","defaultValue":"asc","options":[{"value":"asc","text":"Crescente"},{"value":"desc","text":"Decrescente"}],"validation":{"required":true}},"despesa_ids":{"label":"Despesas prioritarias","type":"array","nullable":false,"itemType":"integer","editor":"multiselect","defaultValue":[],"optionsSource":{"endpointId":"options.despesa_ids","parameterFields":["ordem_campo","ordem_direcao"]},"validation":{"required":true}}}},"runtime":{"entityCode":"container_despesas_selecao_progress","programId":"op1047","mode":"api-process"},"dataSource":{"api":{"process":{"endpointId":"process","method":"POST"},"options.despesa_ids":{"endpointId":"options.despesa_ids","method":"POST"}}},"process":{"parameters":{"title":"Parametros","fields":[{"id":"ordem_campo","field":"ordem_campo","label":"Ordenar despesas por","type":"dropdown","required":true,"readonly":false,"editor":"dropdown","defaultValue":"ordem","options":[{"value":"ordem","text":"Ordem"},{"value":"despesa_id","text":"Despesa"},{"value":"log_calculada","text":"Calculada"},{"value":"perc_calculo","text":"Percentual"}]},{"id":"ordem_direcao","field":"ordem_direcao","label":"Direcao","type":"dropdown","required":true,"readonly":false,"editor":"dropdown","defaultValue":"asc","options":[{"value":"asc","text":"Crescente"},{"value":"desc","text":"Decrescente"}]},{"id":"despesa_ids","field":"despesa_ids","label":"Despesas prioritarias","type":"array","required":true,"readonly":false,"itemType":"integer","editor":"multiselect","defaultValue":[],"optionsSource":{"endpointId":"options.despesa_ids","parameterFields":["ordem_campo","ordem_direcao"]}},{"id":"container_id","field":"container_id","label":"Container MED","type":"integer","required":true,"readonly":true}]},"actions":{"process":{"label":"Confirmar exemplo","icon":"play","permission":"process","confirmation":{"message":"Confirmar a seleção somente nesta demonstração local?","confirmText":"Confirmar exemplo"}}},"wait":{"mode":"none","message":"Processando..."},"result":{"type":"message"}}};
  const context = Object.freeze({container_id:900001});
  const calls = {options:[],process:[]};
  const fields = ["ordem","despesa_id","log_calculada","perc_calculo"], directions = ["asc","desc"];
  const rows = [
    {value:101,text:"Prioridade Alfa",ordem:2,despesa_id:101,log_calculada:0,perc_calculo:2},
    {value:102,text:"Prioridade Beta",ordem:1,despesa_id:102,log_calculada:1,perc_calculo:1},
    {value:103,text:"Prioridade Gama",ordem:3,despesa_id:103,log_calculada:1,perc_calculo:3}
  ];
  const clone = value => JSON.parse(JSON.stringify(value));
  const order = value => value && Object.keys(value).length === 2 && fields.includes(value.ordem_campo) && directions.includes(value.ordem_direcao);
  const validateContext = value => value && Object.keys(value).length === 1 && value.container_id === context.container_id;
  // Dispatch keys only. This example client never calls fetch or XMLHttpRequest.
  definition.dataSource.api.process.url="local.dependent.apply";
  definition.dataSource.api["options.despesa_ids"].url="local.dependent.options";
  const client = {request:async request => {
    const payload=request.data;
    if (!validateContext(payload && payload.context)) { throw new Error("Contexto protegido inválido."); }
    if (request.url === "local.dependent.options") {
      if (Object.keys(payload).some(name=>!["context","parameters"].includes(name)) || !order(payload.parameters)) { throw new Error("Use somente os dois enums fechados."); }
      const parameters=clone(payload.parameters);calls.options.push(clone(payload));
      // Different local delays make a fast change exercise the engine's stale-response guard.
      await new Promise(resolve=>global.setTimeout(resolve,parameters.ordem_direcao === "asc" ? 140 : 20));
      const sorted=rows.slice().sort((a,b)=>(a[parameters.ordem_campo]-b[parameters.ordem_campo])*(parameters.ordem_direcao === "asc" ? 1 : -1) || a.value-b.value);
      return {data:sorted.map(row=>({value:row.value,text:row.text}))};
    }
    if (request.url !== "local.dependent.apply") { throw new Error("Operação fora do exemplo."); }
    const parameters=payload.parameters;
    if (!parameters || Object.keys(parameters).some(name=>!["ordem_campo","ordem_direcao","despesa_ids","container_id"].includes(name)) || parameters.container_id!==context.container_id || !order({ordem_campo:parameters.ordem_campo,ordem_direcao:parameters.ordem_direcao}) || !Array.isArray(parameters.despesa_ids) || !parameters.despesa_ids.length || parameters.despesa_ids.length>100 || new Set(parameters.despesa_ids).size!==parameters.despesa_ids.length || parameters.despesa_ids.some(id=>!Number.isInteger(id) || !rows.some(row=>row.value===id))) { throw new Error("Seleção fictícia inválida."); }
    calls.process.push(clone(payload));
    global.document.getElementById("dependent-options-payload").textContent=JSON.stringify(payload,null,2);
    global.document.getElementById("dependent-options-status").textContent="Seleção confirmada somente na memória desta página.";
    return {ok:true,status:"succeeded",result:{type:"message",message:"Seleção fictícia recebida. Nenhum cálculo ou gravação de negócio foi executado."}};
  }};
  global.kendo.culture("pt-BR");
  const engine=new global.ProcessEngine({root:"#dependent-options-process",definition,config:{},contextValues:context,httpClient:client});
  const ready=engine.init().then(()=>engine.optionsPromise);
  global.processDependentOptionsExample={engine,ready,calls};
  ready.catch(error=>{global.document.getElementById("dependent-options-status").textContent=error.message;});
}(window));
