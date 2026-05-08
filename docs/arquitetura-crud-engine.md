# Arquitetura do CRUD Engine

O motor segue a ideia:

```text
Backend decide.
Frontend renderiza.
```

Esse mesmo principio tambem existe para a pagina inicial via `HomeEngine`.
O `HomeEngine` e separado do `CrudEngine`: ele monta o shell global e pode chamar um CRUD como um dos tipos fechados de programa.
Para paginas de processamento por parametros existe tambem o `ProcessEngine`, separado do CRUD para evitar misturar consultas com execucoes de jobs.

Nesta etapa ainda nao existe backend real. O JSON vem de arquivo/local embed e as chamadas passam pelo mock HTTP.

Para uma primeira versao de producao, o motor tambem aceita carregar a tela por `screenId`.
Nesse modo o frontend nao recebe uma URL livre de JSON: ele pede ao backend uma tela conhecida, e o backend devolve somente a definicao autorizada para o usuario.

## Estrutura principal

```text
src/crud-engine/
  CrudEngine.js
  CrudConfigLoader.js
  CrudDefinitionLoader.js
  CrudDefinitionValidator.js
  CrudToolbarRenderer.js
  CrudFilterRenderer.js
  CrudKendoGridRenderer.js
  CrudKendoFormRenderer.js
  CrudLayoutManager.js
  CrudHttpClient.js
  CrudUtils.js

src/page-engine/
  PageDefinitionNormalizer.js

src/home-engine/
  HomeEngine.js
  HomeDefinitionLoader.js
  HomeDefinitionValidator.js

src/process-engine/
  ProcessEngine.js
  ProcessDefinitionLoader.js
  ProcessDefinitionValidator.js

src/demo/
  demo.js
  home-demo.js
  home-embedded-data.js
  demo-embedded-data.js
  DemoMockHttpClient.js

src/bootstrap/
  kendo-ptbr.js
  production-crud.js
  production-home.js

production/
  app.html
  home.html

src/examples/
  examples-catalog.js
  examples-index.js
  examples-page.js
```

## Responsabilidades

`CrudEngine.js`

- Orquestra carregamento, normalizacao, validacao e renderizacao.
- Renderiza cabecalho, toolbar, filtros aplicados, grid e formulario.
- Coordena filtros, layout, ordenacao, agrupamento, exportacao, ajuda, logs e tema.

`PageDefinitionNormalizer.js`

- Normaliza o contrato JSON para a estrutura interna usada pelos renderizadores.
- Mantem compatibilidade entre niveis antigos e novos do JSON.

`CrudDefinitionValidator.js`

- Valida obrigatorios, campos existentes, permissoes, URLs, endpoints, filtros, grid, formulario e opcoes seguras.
- Deve bloquear `template`, `eval` e JavaScript livre vindo do JSON.

`CrudToolbarRenderer.js`

- Renderiza a appbar principal do grid.
- Renderiza botoes condicionais, acoes em massa e impressao/exportacao.

`CrudFilterRenderer.js`

- Renderiza filtros em janela Kendo.
- Suporta appbar inferior fixa, filtros salvos, filtros em abas e operadores por tipo.

`CrudKendoGridRenderer.js`

- Monta o Kendo Grid.
- Controla colunas, acoes de linha, mobile, exportacao, agrupamento, selecao e congelamento.

`CrudKendoFormRenderer.js`

- Monta formulario em popup Kendo.
- Suporta abas, etapas, appbars, navegacao, logs, impressao, outras acoes, eventos seguros e situacao.

`CrudLayoutManager.js`

- Captura e aplica layout de grid.
- Salva/restaura leiautes, filtros, ordenacoes e agrupamentos no mock.

`CrudHttpClient.js`

- Interface unica de chamadas HTTP.
- A demo usa `DemoMockHttpClient`.

`CrudUtils.js`

- Helpers pequenos: clone, path, URL, mensagens Kendo, confirmacao Kendo, escape HTML etc.

`ProcessEngine.js`

- Renderiza parametros declarativos.
- Chama endpoint de processamento.
- Acompanha job por SSE quando disponivel, com polling como fallback.
- Renderiza retorno fechado em mensagem, grid Kendo, link de relatorio ou aviso de job iniciado.
- Nao aceita template livre, `eval` ou JavaScript vindo do JSON.

## Interface publica atual

```js
new CrudEngine({
  root,
  screenId,
  definitionUrl,
  definition,
  config,
  configUrl,
  hideThemeSwitch,
  httpClient
}).init()
```

Interface publica do processamento:

```js
new ProcessEngine({
  root,
  screenId,
  definitionUrl,
  definition,
  config,
  httpClient
}).init()
```

`hideThemeSwitch=true` desativa o seletor claro/escuro do CRUD quando a tela e aberta dentro de um shell que ja possui controle global de tema.

Em modo `security.mode="production"`, a inicializacao recomendada e:

```js
new CrudEngine({
  root,
  screenId: "cadastros.clientes",
  config,
  httpClient
}).init()
```

Nesse modo, `definition` direto e `definitionUrl` livre podem ser bloqueados por configuracao.
As APIs da tela devem usar `endpointId` ou `actionId`; o motor converte esses identificadores para o gateway runtime configurado em `config.security.endpoints.runtimeEndpoint`.

## Entrada de producao

As demos continuam em `index.html`, `home.html` e `examples/pages/*.html`.
Para producao, usar entradas separadas:

- `production/app.html?screenId=cadastros.clientes`: abre um CRUD por `screenId`.
- `production/home.html?screenId=home`: abre a Home por `screenId`; se ausente, usa `home`.
- Programas `type="process"` podem ser abertos pela Home usando `screenId` em producao.

Essas entradas:

- usam `public/config/crud-engine.production.config.json`;
- nao carregam `DemoMockHttpClient`;
- nao carregam JSON de `examples/`;
- nao possuem script inline proprio;
- usam `CrudHttpClient({ allowLocalFallback: false })`;
- exibem erro generico para o usuario final.

Contrato HTTP esperado:

```js
httpClient.request({ url, method, data })
```

Listagem:

```json
{ "data": [], "total": 0 }
```

Erro:

```json
{
  "error": {
    "code": "CODE",
    "message": "Mensagem",
    "details": {}
  }
}
```

Consistencia de regra de negocio:

```json
{
  "error": {
    "code": "BUSINESS_VALIDATION_FAILED",
    "message": "Existem inconsistencias no formulario.",
    "severity": "error"
  },
  "validation": {
    "status": "blocked",
    "title": "Inconsistencias encontradas",
    "messages": [
      {
        "field": "observacao",
        "type": "error",
        "message": "Observacao e obrigatoria para cliente inativo."
      }
    ]
  },
  "effects": [
    {
      "action": "required",
      "target": "observacao",
      "value": true
    }
  ]
}
```

## JSON de tela

Niveis importantes:

- `program`: titulo, subtitulo, versao, ajuda e logs da tela.
- `permissions`: permissoes visuais.
- `dataSource`: endpoints e API.
- `dataModel`: campos, tipos e chave primaria.
- `crud.query`: paginacao e ordenacao inicial.
- `crud.filter`: filtros.
- `crud.grid`: grid, colunas, mobile, impressao, acoes em massa e IA.
- `crud.form`: formulario, campos, abas, etapas, eventos, logs, impressao, aviso de concorrencia, paginas backend com valores e outras acoes.
- `crud.userLayout`: leiautes, filtros, ordenacoes, agrupamentos e templates mobile salvos.
- `runtime`: configuracao declarativa do frontend para semaforo, heartbeat, mensagens e entidade/programa corrente.

## JSON de processamento

Niveis importantes:

- `program`: titulo, subtitulo, versao, ajuda e logs da tela.
- `permissions`: permissoes visuais.
- `dataSource.api.process`: endpoint que inicia o processamento.
- `dataSource.api.status`: endpoint que consulta o status do job quando houver polling.
- `process.parameters.fields`: parametros renderizados com labels acima dos campos.
- `process.actions.process`: texto, icone e permissao do botao Processar.
- `process.wait`: modo `auto`, `sse`, `polling` ou `none`.
- `process.result`: tipo esperado quando o backend nao informar explicitamente: `message`, `grid`, `report` ou `job`.

Em producao, endpoints de processamento devem usar `endpointId` ou `actionId`, resolvidos pelo gateway runtime.

## Runtime generico de entidade

O backend pode executar CRUD generico com `handler="entity.crud"` em `runtime_endpoint`.
Nesse modo, `runtime_endpoint.config.entityCode` aponta para `builder_entity.code`, e `builder_field` define campos permitidos, chave primaria, tipos e coluna fisica.
O CRUD generico usa DBAL com identificadores validados, filtra `values`, aplica regras de negocio registradas e grava transacao/logs pelo runtime.

Regras simples ficam nos metadados. Regras complexas devem ser handlers PHP registrados no backend, nunca codigo livre vindo do banco.
O Doctrine Subscriber fica apenas como fallback de auditoria para alteracoes via Doctrine fora do fluxo runtime.

### Preferencias de usuario

Em producao, as preferencias do grid nao devem depender de `localStorage`.
O backend persiste por tenant, usuario e `screenId` nas tabelas:

- `user_grid_layout_preference`: ordem, largura, visibilidade e congelamento de colunas, alem do estado atual de sort/filter/group e template mobile vinculado ao layout;
- `user_sort_preference`: presets de ordenacao;
- `user_filter_preference`: presets de filtro;
- `user_group_preference`: presets de agrupamento e agregacoes;
- `user_mobile_grid_template_preference`: campos, posicoes, badges e abas do card mobile do grid.

O runtime entrega tudo em `crud.userLayout`.
O frontend continua usando `saveLayout`, `saveSort`, `saveFilter`, `saveGroup` e passa a aceitar `saveMobileTemplate`/`deleteMobileTemplate`.
A demo pode simular em `localStorage`, mas producao salva no PostgreSQL.

Quando o conceito de assinante/tenant estiver habilitado, a preferencia pode ser salva em dois escopos:

- `scope="tenant"`: vale somente para o assinante atual;
- `scope="global"` ou `applyToAllTenants=true`: vale para todos os assinantes do mesmo usuario.

A precedencia aplicada pelo runtime e:

1. preferencia do usuario no assinante atual;
2. preferencia global do usuario;
3. padrao do programa/tela;
4. padrao tecnico do sistema.

O escopo global usa `tenant_id="__global__"` nas mesmas tabelas de preferencia.
Ao retornar o JSON, cada preset informa `scope`, `tenantId` e `inherited`.

### Situacao de entidade

Uma entidade pode ter ou nao fluxo de situacao.
Quando tiver, o construtor usa:

- `builder_entity.situationEnabled`;
- `builder_entity.situationFieldCode`;
- `builder_entity_situation` para os codigos permitidos;
- `builder_entity_situation_transition` para transicoes, pontos de regra e efeitos seguros.

O frontend continua renderizando `crud.form.situation`.
O backend e quem valida a regra real: valor de situacao cadastrado, situacao inicial no `create`, transicao permitida no `update` e regras declarativas fechadas por transicao.
Uma mudanca de situacao grava log proprio `*.situation.transition` dentro da transacao runtime.

### Minimo para uma entidade funcionar no runtime

So existir uma classe Doctrine, DTO ou tabela fisica nao e suficiente para o frontend dinamico.
O runtime nao infere automaticamente uma tela a partir da classe, porque ele precisa saber quais campos sao legiveis, gravaveis, auditaveis, quais endpoints estao autorizados e qual JSON sera entregue ao frontend.

O minimo para uma entidade CRUD funcionar e:

- tabela fisica no PostgreSQL;
- registro em `builder_entity` com `code`, `entityType="persistence"` e `tableName`;
- registros em `builder_field`, incluindo a chave primaria e os campos usados no grid/formulario;
- `builder_field.dataType` preenchido para cada campo;
- `builder_field.options.columnName` quando o nome do campo no frontend for diferente da coluna fisica;
- se houver situacao: `builder_entity.situationEnabled=true`, `situationFieldCode` existente em `builder_field`, situacoes e transicoes cadastradas;
- registro em `screen_definition` com `screenId`, `pageType="crud"`, `status` publicado ou rascunho e JSON declarativo da tela;
- registros em `runtime_endpoint` para as operacoes usadas pela tela, normalmente com `handler="entity.crud"` e `config.entityCode/config.operation`.

Se apenas a tabela/classe existir e os metadados nao existirem, o backend retorna erro fechado `ENTITY_METADATA_NOT_CONFIGURED`.
Se a entidade estiver cadastrada, mas sem tabela, campos, chave primaria ou colunas validas, o backend retorna erros fechados como `ENTITY_TABLE_NOT_CONFIGURED`, `ENTITY_FIELDS_NOT_CONFIGURED`, `ENTITY_FIELDS_NOT_USABLE` ou `ENTITY_PRIMARY_KEY_NOT_FOUND`.
Esses erros seguem o contrato padrao `{ error: { code, message, details } }` e incluem `details.minimumRequired` para orientar o que falta cadastrar.

## Fila de trabalho

O backend usa Symfony Messenger para tarefas assicronas fechadas, com transporte Doctrine/PostgreSQL.
Jobs sao rastreados em `runtime_async_job` e processados por handlers PHP registrados; metadados do banco podem escolher tipos conhecidos, mas nunca executar PHP/JS livre.

A decisao de usar worker fica no backend:

- `builder_entity.metadata.jobs` define jobs padrao da entidade;
- `runtime_endpoint.config.jobs` pode sobrescrever ou complementar por programa/endpoint;
- `mode="async"` agenda no worker;
- sem job configurado, a acao continua sendo processada na chamada normal;
- `type` sempre precisa existir em um handler PHP registrado.
- acoes manuais de formulario podem usar endpoint fechado com `handler="runtime.job.enqueue"`.

Exemplo de job configurado:

```json
{
  "id": "cliente-email-confirmation",
  "type": "cliente.email_confirmation",
  "trigger": "after_success",
  "mode": "async",
  "enabled": true,
  "operations": ["create"],
  "when": {
    "source": "after",
    "field": "email",
    "operator": "isEmail"
  },
  "payload": {
    "clienteId": "after.id",
    "nome": "after.nome",
    "email": "after.email"
  },
  "queuedMessage": "E-mail de confirmacao agendado."
}
```

Contrato opcional em resposta de gravacao:

```json
{
  "_runtime": {
    "asyncJobs": [
      {
        "type": "cliente.email_confirmation",
        "status": "queued"
      }
    ]
  },
  "effects": [
    {
      "action": "showMessage",
      "type": "info",
      "message": "E-mail de confirmacao agendado."
    }
  ]
}
```

O primeiro job fechado e `cliente.email_confirmation`, disparado por `builder_entity.metadata.jobs` apos inclusao valida de cliente com e-mail.
O primeiro job manual fechado e `cliente.whatsapp_welcome`, disparado pelo endpoint `sendWhatsapp` com `handler="runtime.job.enqueue"`.
No ambiente atual o Mailer usa `null://null`; o worker registra a execucao e o envio fica preparado para SMTP real via `MAILER_DSN`.
A tela de consulta dos jobs fica em `production/app.html?screenId=admin.jobs`.

## Telas administrativas do runtime

As areas administrativas usam o mesmo CRUD Engine, carregadas por `screenId` e `endpointId`, sem URL livre:

- `admin.parametros`: cadastro de parametros (`system_parameter`);
- `admin.parametro-valores`: valores vigentes dos parametros (`system_parameter_value`);
- `admin.listas-opcoes`: listas de opcoes (`system_option_list`);
- `admin.opcoes`: opcoes das listas (`system_option`);
- `admin.sessoes`: consulta de sessoes (`runtime_user_session`) com acao fechada para derrubar sessao;
- `admin.transacoes`: consulta de transacoes (`runtime_transaction`);
- `admin.logs-transacoes`: consulta dos logs das transacoes (`runtime_transaction_log`);
- `admin.jobs`: consulta de jobs assincronos (`runtime_async_job`).

Campos sensiveis como tokens de semaforo nao sao expostos no JSON das telas administrativas.

## Parametros do sistema

O backend possui um modulo simples de parametros com API Platform:

- `system_option_list` guarda listas de opcoes.
- `system_option` guarda opcoes por lista, com `code` e `description`.
- `system_parameter` define o parametro, tipo de dado e lista quando o tipo for `option` ou `multi_option`.
- `system_parameter_value` guarda valores vigentes por periodo e `establishmentCode`.

O resolver de parametros aplica valor por estabelecimento antes do global e valida tipos fechados. Valor global usa `establishmentCode=null`; valores antigos com string vazia tambem sao tratados como globais. O primeiro parametro criado pelo seed e `subscriber.enabled=false`.

## Runtime de concorrencia

O CRUD pode consumir endpointIds fechados para:

- adquirir, renovar e liberar semaforo de registro;
- enviar `lockToken`, `transactionId` e `version` em gravacoes;
- receber mensagens runtime por SSE, com polling como fallback, e tratar `force_logout`;
- avisar antes de sair de formulario sujo.

O bloqueio real, expiracao por entidade/programa/acao, auditoria e validacao de versao ficam no backend.

## Exemplos

`exemplos.html` e o indice central.

Cada pagina em `examples/pages/*.html` usa:

- aba `Renderizacao`;
- aba `Codigo`;
- aba `Configuracao`, que permite mudar o trecho JSON do exemplo e atualizar a renderizacao.

O catalogo dos exemplos fica em `src/examples/examples-catalog.js`.

Sempre que uma funcionalidade nova for implementada, atualizar:

- `src/examples/examples-catalog.js`;
- paginas/fluxo de exemplos, se necessario;
- a referencia de propriedades em `getPropertyOptions()`.

## Seguranca de producao

- `config.security.mode="production"` ativa padroes conservadores.
- `definitionSource.requireScreenId=true` exige que a tela seja carregada por identificador.
- `definitionSource.allowDirectDefinition=false` bloqueia JSON direto vindo da camada chamadora.
- `definitionSource.allowDefinitionUrl=false` bloqueia URL livre para definicao de tela.
- `endpoints.allowInlineUrls=false` bloqueia URLs livres em APIs e acoes.
- `endpoints.requireEndpointIds=true` exige `endpointId` ou `actionId`.
- `documents.allowInlineUrls=false` bloqueia links diretos para logs/documentos quando configurado.
- Botoes de formulario que abrem paginas do backend podem enviar valores por querystring ou POST, mas em producao a URL/acao deve ser autorizada e o backend precisa validar todos os parametros recebidos.
- Chamadas runtime feitas a partir de formulario enviam os valores atuais em `values`; o backend usa apenas campos permitidos da entidade, ignora campos livres e continua validando permissao, lock e versao antes de gravar.
- A autenticacao de producao usa `/api/auth/login`, token Bearer vinculado a `runtime_user_session` e provedores fechados em `auth_provider_config`: `local`, `ldap`, `sso`, `oauth`/`oidc`.
- No login por usuario/senha, o tipo de acesso vem de `auth_user.authSource`; a tela so mostra selecao quando houver acesso externo OAuth/OIDC habilitado.
- O conceito de assinante e controlado pelo parametro `subscriber.enabled`. Quando habilitado, o backend resolve os assinantes apos validar as credenciais; administrador ou usuario com mais de um assinante recebe `requiresSubscriberSelection=true` e conclui em `/api/auth/select-subscriber`.
- Assinantes e vinculos de usuario ficam em `auth_subscriber` e `auth_user_subscriber`; o desafio temporario de selecao fica em `auth_login_challenge`.
- Recuperacao de senha usa `/api/auth/password/request-reset` e `/api/auth/password/reset`, gravando tokens com hash em `auth_password_reset_token`. Em dev o token pode voltar na resposta; em producao deve ir por e-mail.
- O recurso "manter logado" usa `auth_remember_token` com hash do token, validade de 30 dias e restauracao por `/api/auth/remember`; logout e derrubada administrativa revogam o token.
- `AUTH_REQUIRED=1` faz os endpoints runtime recusarem chamadas sem token valido; `AUTH_REQUIRED=0` mantem compatibilidade tecnica local.
- O frontend continua validando o JSON, mas permissao real, tenant, dados e acoes precisam ser validados no backend.
