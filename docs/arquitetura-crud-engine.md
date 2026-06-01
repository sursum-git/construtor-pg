# Arquitetura do CRUD Engine

O motor segue a ideia:

```text
Backend decide.
Frontend renderiza.
```

Esse mesmo principio tambem existe para a pagina inicial via `HomeEngine`.
O `HomeEngine` e separado do `CrudEngine`: ele monta o shell global e pode chamar um CRUD como um dos tipos fechados de programa.
Para paginas de processamento por parametros existe tambem o `ProcessEngine`, separado do CRUD para evitar misturar consultas com execucoes de jobs.
Para geracao visual de novos programas existe uma interface administrativa separada, que gera definicoes CRUD ou registra programas custom e publica o resultado no runtime.
Esse editor agora tambem possui painel contextual de propriedades, visao de relacionamentos, comparativo entre revisoes/versoes, reordenacao por drag-and-drop para campos/regras/chaves, lock de edicao com heartbeat no backend, importacao de tabelas PostgreSQL existentes e importacao de SQL/DDL com `CREATE TABLE`.
Existe tambem um MVP opcional e separado em `desktop-wpf/`, criado apenas para validar uma experiencia desktop de autoria sem impactar o frontend web atual.

Na demo, o JSON pode vir de arquivo/local embed e as chamadas podem passar pelo mock HTTP.
Na producao inicial, o backend Symfony ja entrega telas por `screenId`, endpoints por `endpointId` e documentos autorizados pelo runtime.

Para uma primeira versao de producao, o motor tambem aceita carregar a tela por `screenId`.
Nesse modo o frontend nao recebe uma URL livre de JSON: ele pede ao backend uma tela conhecida, e o backend devolve somente a definicao autorizada para o usuario.

## Instalacao inicial

A instalacao inicial tambem segue o principio "backend decide, frontend renderiza".

O executavel Go prepara e autoriza o ambiente antes da pagina web:

- perfil compilado: `system_builder` ou `subscriber`;
- precheck do host conforme modo;
- ativacao central por codigo do assinante e codigo enviado ao e-mail cadastrado;
- emissao de sessao curta e manifesto assinado;
- gravacao de sessao local em arquivo de estado.

A pagina `production/install.html` nao recebe token livre do usuario. Ela consulta o backend local, mostra a ativacao autorizada e coleta apenas dados operacionais: senha do instalador, administrador inicial, dados do assinante e opcoes fechadas de bootstrap.

A API local so executa `/api/install/run` quando `InstallationActivationService` valida a sessao local criada pelo executavel. Depois do sucesso, o backend grava `APP_SYSTEM_INSTALLED=1` e o hash da senha do instalador em `.env.local`.

No Docker Linux, a stack simples tem `app` e `database`. Para producao operacional, `compose.production.yaml` separa `nginx`, `php`, `worker` e `database`. O boot do container nao instala o sistema automaticamente. A execucao das migrations, seed, catalogo padrao e integridade continua no fluxo controlado da pagina.

A central de ativacao tambem valida fingerprints, limite de hosts, fingerprints revogados, tokens internos cadastraveis para SaaS e assinatura HMAC dos artefatos antes do download pelo executavel.

## Notificacoes runtime

Existe agora um modulo proprio de notificacoes runtime, separado das listas agregadas antigas de `alerts`, `requests` e `jobs`.

No backend:

- `runtime_notification` guarda o cabecalho da notificacao;
- `runtime_notification_recipient` guarda a entrega por destinatario;
- as telas administrativas sao:
  - `admin.notificacoes`
  - `admin.notificacao-destinatarios`
- a Home pode consumir:
  - `home.notifications.list`
  - `home.notifications.ack`

Modelo atual:

- envio por usuarios especificos em `target_user_ids`;
- envio por grupos em `target_groups`;
- rastreio por destinatario com estados:
  - `pending`
  - `delivered`
  - `read`
- cada destinatario pode registrar:
  - `delivered_at`
  - `read_at`

Na Home:

- o appbar continua aceitando agregacao de `alerts`, `requests` e `jobs` quando nao houver endpoint proprio;
- quando existir endpoint dedicado de notificacoes, a central passa a listar os registros reais do backend;
- cada item pode ser marcado como lido por `home.notifications.ack`;
- quando a notificacao tiver `link_program_id` ou `link_screen_id`, o shell pode abrir o programa relacionado;
- a notificacao tambem pode carregar `navigation.query`, permitindo abrir a tela alvo com filtros e foco contextual via querystring segura.

Limites desta fase:

- nao existe ainda broadcast generico por todos os usuarios sem materializar destinatarios;
- nao existe painel analitico em tempo real por websocket;
- o fluxo principal e administrativo/manual, nao automacao de campanha.

## Entidade API

O construtor agora aceita `entityType=api` para consultas externas em JSON.

Escopo atual:

- endpoint de lista obrigatorio;
- endpoint de detalhe opcional;
- autenticacao por headers fixos cadastrados na entidade;
- mapeamento por `jsonPath`;
- renderizacao em grid + formulario;
- sem tabela fisica e sem lock de escrita;
- cadastro reutilizavel de metadados da API em `builder_api_source`;
- importacao basica de contrato OpenAPI/Swagger para montar as operacoes;
- dois modos:
  - `readonly`;
  - `crud` basico para APIs JSON previsiveis.
- tambem existe um provedor especifico `odoo` dentro do cadastro reutilizavel de APIs, em modo `readonly`, com suporte a `XML-RPC` e `JSON-RPC`, teste de conexao, leitura de metadados do modelo e geracao de CRUD em modo consulta.
- `builder_api_source` agora tambem entra na protecao de integridade estrutural e e validado pelo backend antes do uso no construtor.

No backend:

- o builder salva a configuracao em `builder_entity.metadata.apiSource`;
- a entidade pode apontar para um cadastro reutilizavel em `builder_api_source`, usando `apiSourceCode`, `apiListOperationCode`, `apiDetailOperationCode`, `apiCreateOperationCode`, `apiUpdateOperationCode` e `apiDeleteOperationCode`;
- o builder expõe `GET /api/admin/program-builder/api-sources/{apiSourceCode}`, `POST /api/admin/program-builder/api-sources` e `POST /api/admin/program-builder/api-sources/import-openapi`;
- o importador OpenAPI/Swagger cria um rascunho revisavel de operacoes da API, sem publicar nem gerar tela automaticamente;
- para Odoo, o builder tambem expoe `POST /api/admin/program-builder/api-sources/odoo/test-connection` e `POST /api/admin/program-builder/api-sources/odoo/model-metadata`;
- o runtime publica endpoints `read`, `get` e, quando configurado, `create`, `update` e `delete`;
- o handler usado passa a ser `entity.api.crud`, separado do `entity.crud`;
- quando a fonte vinculada usa `providerType=odoo`, o runtime muda para `entity.api.odoo.readonly`, com `search_read`, `search_count` e `read` montados internamente, sem expor `create`, `update` ou `delete`.
- campos de entidade API agora tambem podem usar `options.api.lookupResolver` para enriquecer a linha com outra operacao cadastrada da mesma `apiSource`, com memoizacao por request e batch por ids distintos quando `mode=batch`.

### Lookup resolver em entidade API

Contrato fechado:

```json
{
  "api": {
    "jsonPath": "cliente_nome",
    "lookupResolver": {
      "operationCode": "clientes_batch",
      "sourceField": "cliente_id",
      "requestParam": "ids",
      "mode": "batch",
      "responseItemsPath": "items",
      "matchField": "id",
      "valuePath": "nome"
    }
  }
}
```

Regras:

- `operationCode` precisa existir no cadastro reutilizavel da API;
- `sourceField` aponta para um campo ja mapeado da linha principal;
- `mode=batch` espera array em `responseItemsPath` e exige `matchField`;
- `mode=per_value` usa `responseItemPath` e resolve um registro por id distinto;
- o cache atual fica restrito ao request do backend, sem persistencia global nesta fase.

## Isolamento por assinante na entidade persistente

O construtor agora tambem pode marcar a entidade persistente com escopo de registros por tabela:

- `subscriberIsolation.mode=none`
- `subscriberIsolation.mode=subscriber_column`

Quando o modo for `subscriber_column`:

- a coluna fisica do assinante precisa existir na modelagem da entidade;
- o runtime CRUD aplica automaticamente o filtro do assinante atual em `read` e `get`;
- `create` injeta o valor do assinante atual nessa coluna;
- `update` e `delete` ficam limitados ao registro que ja passou pelo mesmo filtro.

Isso cobre o cenario em que varios assinantes apontam para o mesmo programa e o mesmo banco, mas a separacao dos registros acontece pela coluna do assinante.

Quando o modo for `none` em entidade `persistence`:

- a entidade precisa confirmar explicitamente que a tabela e global compartilhada;
- o builder bloqueia persistencia sem essa confirmacao;
- o catalogo administrativo do provisionamento passa a listar a tabela como global, filtrada por assinante ou em risco de configuracao.

## Soft delete

O runtime generico suporta exclusao logica por entidade persistente. Por padrao, o comportamento continua sendo hard delete. Para habilitar soft delete, a entidade deve possuir `builder_entity.metadata.softDelete`:

```json
{
  "enabled": true,
  "deletedAtField": "deletedAt",
  "deletedByField": "deletedBy",
  "reasonField": "deleteReason"
}
```

Regras:

- `deletedAtField` e obrigatorio quando `enabled=true` e precisa apontar para uma coluna fisica da entidade.
- `deletedByField` e `reasonField` sao opcionais.
- `read` e `get` ocultam registros com `deletedAtField` preenchido.
- `delete` deixa de remover a linha e passa a preencher data/hora da exclusao, usuario atual e motivo quando os campos existirem.
- Para consulta administrativa de registros excluidos, o payload pode enviar `includeDeleted=true` ou `_runtime.includeDeleted=true`.
- O evento `runtime.entity.deleted` continua sendo publicado e o log em `runtime_transaction_log` registra `deleteMode=soft`.

## Solicitacoes LGPD

O backend possui uma primeira camada operacional para pedidos do titular:

- pagina publica `production/privacy-request.html`;
- endpoints publicos:
  - `POST /api/public/privacy/requests/start`;
  - `POST /api/public/privacy/requests/confirm`;
  - `GET /api/public/privacy/requests/{protocol}?email=...`;
- tabelas:
  - `privacy_subject_request`;
  - `privacy_subject_request_verification`;
  - `privacy_subject_request_evidence`;
  - `privacy_retention_policy`;
- telas administrativas:
  - `admin.lgpd-solicitacoes`;
  - `admin.lgpd-evidencias`;
  - `admin.lgpd-retencao`.

O pedido publico nao expõe dados do titular. Ele cria um protocolo, envia codigo para o e-mail informado e so encaminha a solicitacao depois da validacao. A validacao confirmada gera alerta prioritario para grupos administrativos (`admin`, `privacy`, `dpo`), publica o evento `privacy.subject_request.created` e registra logs em `runtime_transaction_log`.

Pedidos recebidos por e-mail, telefone, WhatsApp, formulario externo ou atendimento presencial entram pela tela `admin.lgpd-solicitacoes`, usando `source_channel` correspondente. Quando o pedido e criado manualmente, o runtime gera protocolo, prioridade alta por padrao e o mesmo alerta/evento operacional.

Politicas em `privacy_retention_policy` indicam quando um dado/documento bloqueia anonimizacao, por exemplo notas fiscais ou registros fiscais com retencao obrigatoria. Nesse caso, a resposta ao titular deve ser parcial ou recusada com justificativa formal, sem anonimizar campos essenciais enquanto houver obrigacao de guarda.

Na definicao gerada:

- o `pageType` continua `crud`;
- quando nao houver operacoes de escrita, `permissions.create/edit/delete` ficam `false`, a toolbar nao mostra `Incluir` e as acoes de linha ficam apenas com `Visualizar`;
- quando houver operacoes de escrita, a tela habilita `Incluir`, `Alterar` e `Excluir` conforme os endpoints publicados;
- `runtime.lock.enabled=false`;
- `runtime.messages.enabled=false`.

No construtor:

- o cadastro da API centraliza `baseUrl`, `authMode`, `authHeaders`, `timeoutSeconds`, `openapiUrl` e as operacoes disponiveis;
- a entidade `api` deixa de depender de configuracao solta e passa a vincular uma API cadastrada + operacoes de lista/detalhe/escrita;
- o bloco inline continua existindo apenas como contrato expandido que o runtime usa internamente depois da validacao do cadastro;
- quando a fonte usa Odoo, o editor troca o cadastro generico por um formulario proprio com:
  - `transport`
  - `baseUrl`
  - `database`
  - `login`
  - `secretMode`
  - `secretValue`
  - `model`
  - `defaultContext`
  - `defaultDomain`
  - `defaultOrder`
  - `defaultLimit`
  - `timeoutSeconds`
- nesse modo o construtor disponibiliza:
  - `Testar conexao`
  - `Ler metadados do modelo`
  - `Carregar campos do modelo`
- as operacoes de lista e detalhe passam a usar os codigos sinteticos `odoo_list` e `odoo_detail`.

### Odoo

No cadastro reutilizavel, `providerType=odoo` salva o bloco `metadata.odoo` com:

- `transport`
- `baseUrl`
- `database`
- `login`
- `secretMode`
- `secretValue`
- `model`
- `defaultContext`
- `defaultDomain`
- `defaultOrder`
- `defaultLimit`
- `timeoutSeconds`
- `json2Ready`

O segredo volta mascarado para a UI administrativa e segue o mesmo fluxo de restauracao de valor mascarado usado no restante do builder.

Mapeamento inicial dos tipos do Odoo:

- `char`, `selection`, `many2one` -> `string`
- `text`, `html` -> `text`
- `integer` -> `integer`
- `float`, `monetary` -> `decimal`
- `boolean` -> `boolean`
- `date` -> `date`
- `datetime` -> `datetime`
- `one2many`, `many2many` -> `json`

Limites desta fase:

- somente leitura;
- metodos ORM expostos:
  - `search_read`
  - `search_count`
  - `read`
- sem `create`, `write` e `unlink`;
- `many2one` entra como valor legivel;
- `one2many` e `many2many` entram como `json`;
- `JSON-2` fica apenas como preparo de cadastro, sem execucao nesta entrega.

Limites desta fase de escrita:

- apenas APIs JSON previsiveis;
- `GET`, `POST`, `PUT`, `PATCH` e `DELETE`;
- headers e parametros estaticos;
- sem transformacao por JavaScript;
- sem upload, GraphQL, SOAP/XML, refresh dinamico de token ou multiplas chamadas por operacao.

## Importacao e exportacao entre entidades

Existe agora um catalogo inicial de mapeamentos em `import_export_mapping`, exposto pelos endpoints administrativos:

- `GET /api/admin/import-export-mappings`
- `GET /api/admin/import-export-mappings/{code}`
- `POST /api/admin/import-export-mappings`
- `POST /api/admin/import-export-mappings/preview`
- `POST /api/admin/import-export-mappings/execute`

Escopo atual:

- origem:
  - entidade `persistence`;
  - entidade `api` generica;
  - entidade `api` com provedor Odoo readonly;
- destino:
  - entidade `persistence`;
  - entidade `api` generica com CRUD previsivel;
  - arquivo `csv`;
  - arquivo `txt_layout`.

No `txt_layout`, a engine aceita tanto estrutura plana quanto hierarquica.

No `xml`, a engine aceita dois modos:

- estrutura simples por `columns[]`;
- estrutura rica por `xmlLayouts[]`, com namespaces, atributos, filhos repetitivos e vinculo por `linkBy`.

Contratos suportados na primeira entrega:

- `api -> tabela`;
- `tabela -> api`;
- `entidade -> csv`;
- `entidade -> txt_layout`.

O corpo do mapeamento usa:

- `source` ou `sources[]`;
- `destination`;
- `fieldMappings[]`;
- `options`.

Os artefatos estruturais dessa frente tambem entram na camada de integridade do sistema:

- `import_export_mapping`;
- `import_export_mapping_version`;
- `import_export_schedule`.

Outras superfícies estruturais sensiveis agora cobertas pela mesma assinatura:

- `builder_module`;
- `runtime_lock_policy`;
- `system_parameter`;
- `system_parameter_value`.

Para arquivo TXT, cada item de `recordLayouts[]` aceita:

- `sourceAlias`;
- `lineMode: "fixed" | "delimited"`;
- `fields[]`.
- opcionalmente `nodeType: "record" | "group" | "totalizer"`;
- opcionalmente `children[]` para arvore hierarquica.

### TXT posicional

Quando `lineMode="fixed"`, cada campo aceita:

- `sourcePath` ou `constant`;
- `start` opcional;
- `length` obrigatorio;
- `align: "left" | "right"`;
- `padChar`;
- `transforms[]`.

Se `start` nao for informado, o motor continua da posicao anterior.

### TXT por separador

Quando `lineMode="delimited"`, cada campo aceita:

- `sourcePath` ou `constant`;
- `transforms[]`.

Nesse modo:

- nao existe coluna fixa por posicao;
- a ordem do array `fields[]` define a ordem da linha;
- `separator` define o delimitador da linha.

Exemplo:

```json
{
  "sourceAlias": "cliente",
  "lineMode": "delimited",
  "separator": "|",
  "fields": [
    { "constant": "02" },
    { "sourcePath": "id" },
    { "sourcePath": "nome" },
    { "sourcePath": "cidade" }
  ]
}
```

### TXT hierarquico

Quando o leiaute precisa de registros pai, filhos e totalizadores, `recordLayouts[]` pode formar uma arvore.

Nos suportados:

- `group`: so organiza a arvore; nao precisa gerar linha;
- `record`: renderiza a linha e depois pode abrir filhos em `children[]`;
- `totalizer`: calcula agregados sobre uma fonte filtrada e renderiza a linha de fechamento.

Vinculo entre pai e filho:

```json
{
  "linkBy": [
    { "parentPath": "id", "childField": "nota_id", "operator": "eq" }
  ]
}
```

No totalizador, a linha pode usar:

- `_parent.*` para ler valores do registro pai;
- `_summary.count`;
- `_summary.<nome_da_agregacao>`.

Agregacoes iniciais suportadas:

- `count`
- `sum`

Transformacoes fechadas suportadas nesta etapa:

- `trim`
- `upper`
- `lower`
- `constant`
- `concat`
- `date_format`
- `number_format`
- `pad_left`
- `pad_right`

No XML rico, cada no pode usar:

- `name`;
- `sourceAlias`;
- `attributes[]`;
- `fields[]`;
- `children[]`;
- `textSourcePath` ou `textConstant`;
- `linkBy[]` para relacionar filhos ao registro pai.

Limites desta fase:

- execucao manual por endpoint administrativo ou por agendamento simples (`interval`, `hourly`, `daily`);
- a tela administrativa ja cobre cadastro, `TreeView`, preview estrutural, execucao, historico persistido, versoes do mapping e agendamentos;
- o XML ainda nao cobre cenarios avancados como namespaces default, escolha de atributo vs conteudo por expressao livre ou transformacao por script.

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

src/custom-page/
  CustomPageEngine.js

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
  program-builder.html

src/program-builder/
  program-builder.js

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
- Renderiza tambem `editor="customCode"` com assistente declarativo para propriedades da codificacao.

## Importacao de tabelas existentes no construtor

O `program-builder` agora consegue ler uma tabela fisica ja existente no PostgreSQL e gerar:

- um rascunho de `builder_entity` com campos, PK, FKs, `unique`, `default`, tipos e readonly sugerido;
- classificacao automatica da tabela (`master`, `support`, `transactional`, `junction`);
- um rascunho basico de programa CRUD, ainda sujeito a revisao humana antes de publicar.

Fluxo:

1. `GET /api/admin/program-builder/database/tables`
   - lista tabelas importaveis do banco;
   - exclui tabelas internas do runtime/construtor.
2. `POST /api/admin/program-builder/database/inspect`
   - inspeciona uma tabela;
   - devolve `classification`, `diagnostics`, `entityDraft` e `programDraft`.
3. `POST /api/admin/program-builder/database/import`
   - grava a entidade importada;
   - gera revisao da entidade;
   - opcionalmente cria um rascunho de programa CRUD.

Decisoes do fluxo:

- a importacao nao publica automaticamente;
- a tabela fisica importada nao e renomeada nem recriada;
- validacao de nomenclatura estrutural e relaxada quando o nome fisico importado e mantido como origem;
- o codigo do programa continua manual e precisa respeitar o modulo escolhido.

## Importacao de JSON externo no construtor

O `program-builder` tambem aceita um segundo fluxo para trabalhar com IA externa ou JSON montado fora do sistema.

Fluxo:

1. `GET /api/public/program-builder/external-context`
   - endpoint publico protegido pelo cabecalho `X-Builder-Public-Key`;
   - expone apenas contrato tecnico, tipos suportados, regras de nomenclatura, exemplos minimos, modulos e resumo de entidades existentes.
2. `POST /api/admin/program-builder/external/validate`
   - recebe um objeto com `entityDraft` e `programDraft`;
   - valida o payload com o mesmo nucleo do builder;
   - recusa `pageType` diferente de `crud`;
   - devolve `entityDraft`, `programDraft`, `generatedDefinition`, `diagnostics` e `readyToApply`.

Decisoes do fluxo:

- o endpoint publico nao cria nada, apenas orienta uma IA externa;
- a chave do endpoint publico usa os parametros `ai.builder.public_context_enabled` e `ai.builder.public_context_key`;
- a importacao administrativa nao salva nem publica automaticamente;
- o frontend apenas carrega o rascunho normalizado para revisao humana no proprio construtor.

## Importacao de SQL/DDL no construtor

O `program-builder` tambem aceita colar um script de definicao de tabela PostgreSQL para gerar a modelagem inicial sem executar o SQL no schema real.

Fluxo:

1. `POST /api/admin/program-builder/database/inspect-ddl`
   - recebe um `CREATE TABLE`;
   - aceita opcionalmente `COMMENT ON TABLE` e `COMMENT ON COLUMN`;
   - recusa comandos fora desse escopo;
   - devolve `classification`, `diagnostics`, `entityDraft` e `programDraft`.
2. `POST /api/admin/program-builder/database/import-ddl`
   - valida o mesmo script;
   - grava a entidade importada;
   - gera revisao da entidade;
   - opcionalmente cria um rascunho de programa CRUD.

Decisoes do fluxo:

- o SQL nao e executado no banco real;
- a importacao aceita um `CREATE TABLE` por vez;
- a publicacao do programa continua manual;
- comandos como `DROP`, `ALTER`, `INSERT`, `UPDATE`, funcoes, triggers e scripts livres sao bloqueados;
- o parser cobre colunas, tipos PostgreSQL comuns, PK, UNIQUE, FK, defaults simples, nulidade e comentarios usados como labels;
- casos complexos devem ser revisados pelo analista antes de salvar/publicar.

## Assistente de IA interno no construtor

O `program-builder` agora tambem tem um fluxo interno por chat para montar rascunhos CRUD dentro do proprio sistema.

Endpoints:

1. `GET /api/admin/program-builder/ai/settings`
   - devolve a configuracao atual do assistente, com token mascarado.
2. `POST /api/admin/program-builder/ai/settings`
   - salva configuracao do assistente.
3. `POST /api/admin/program-builder/ai/session`
   - cria ou retoma uma sessao persistente do assistente, vinculada ao usuario, tenant e assinante atual.
4. `POST /api/admin/program-builder/ai/message`
   - exige `sessionId`, carrega o historico resumido persistido e devolve mensagens + rascunho validado quando houver informacao suficiente.
5. `POST /api/admin/program-builder/ai/transcribe`
   - processa `transcriptText` ou `audioBase64`.
6. `POST /api/admin/program-builder/ai/finalize-draft`
   - revalida o rascunho persistido na sessao antes de carregar a modelagem.

Persistencia:

- `runtime_ai_session` guarda `sessionId`, usuario, assinante, proposito, hash/versao do catalogo, rascunho atual, diagnosticos, status e validade.
- `runtime_ai_message` guarda mensagens da conversa, payload normalizado, diagnosticos e horario.
- o token autentica a chamada, mas o `sessionId` identifica a conversa; `sessionId` sozinho nunca autoriza acesso.

Parametros administrativos usados:

- `ai.builder.enabled`
- `ai.builder.provider`
- `ai.builder.agent_name`
- `ai.builder.base_url`
- `ai.builder.model`
- `ai.builder.api_token`
- `ai.builder.transcription_enabled`
- `ai.builder.transcription_model`

Regras do fluxo:

- provider suportado nesta etapa: `mock` e `openai_compatible`;
- o chat nunca salva nem publica por conta propria;
- o rascunho passa pela mesma validacao do fluxo externo (`entityDraft` + `programDraft`);
- regras geradas pela IA ficam limitadas ao contrato declarativo `requiredWhen`; referencias a classe/metodo viram diagnostico de pendencia tecnica;
- o contexto da IA inclui um catalogo versionado de capacidades com schemas para entidade, programa, campo e regra declarativa;
- `ai.builder.api_token` e `ai.builder.public_context_key` sao tratados como segredos e nao retornam em claro nem no CRUD administrativo de parametros.

No frontend:

- o appbar do `program-builder` ganhou os botoes `Assistente IA` e `Configurar IA`;
- a janela usa `kendoChat`;
- o painel lateral mostra resumo do rascunho e diagnosticos;
- o painel lateral tambem mostra estado e validade da sessao;
- o botao `Nova conversa` inicia uma nova sessao persistente;
- `Carregar na modelagem` so aplica o rascunho depois de nova validacao no backend;
- o audio tenta `SpeechRecognition` primeiro e usa `MediaRecorder` como fallback.

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

- `production/app.html?screenId=cadastros.clientes`: abre uma tela por `screenId`, detectando `crud`, `process` ou `custom`.
- `production/home.html?screenId=home`: abre a Home por `screenId`; se ausente, usa `home`.
- Programas `type="process"` podem ser abertos pela Home usando `screenId` em producao.
- Programas `type="custom"` podem ser abertos pela Home ou diretamente em `production/app.html`, usando definicao runtime com `custom.mode` e `custom.entryUrl`.
- O backend ja publica `screenId=processamento.relatorio-clientes`, com endpoints `process` e `status`.

## Programas custom

O tipo `pageType="custom"` foi criado para telas especificas demais para cair cedo em `crud` ou `process`.

Contrato:

- `pageType: "custom"`
- `program`: metadados catalogados no runtime
- `custom.mode`: `iframe` ou `htmlUrl`
- `custom.entryUrl`: caminho relativo do proprio sistema
- `custom.frameTitle`: titulo acessivel para o iframe

Regras:

- nao aceita `http` ou `https` livre no entrypoint publicado pelo builder;
- `iframe` abre uma pagina manual isolada;
- `htmlUrl` carrega um fragmento HTML sanitizado, sem scripts, eventos inline ou URLs inseguras;
- endpoints runtime do programa ficam desabilitados quando o tipo nao e CRUD.

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
    "titleKey": "validation.title.inconsistencies",
    "messages": [
      {
        "field": "observacao",
        "type": "error",
        "message": "Observacao e obrigatoria para cliente inativo.",
        "messageKey": "validation.message.field_required",
        "messageParams": {
          "field": "observacao",
          "fieldCode": "observacao",
          "fieldLabel": "Observacao"
        }
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

O frontend agora resolve os textos do contrato de validacao assim:

- se vier `messageKey`/`messageParams`, usa o catalogo interno de literais;
- se vier `titleKey`/`titleParams`, usa o catalogo interno de literais;
- se a chave nao existir, cai para `message` e `title`;
- se nada vier, usa os defaults pt-BR do motor.

Existe agora tambem um bundle runtime de literais por locale:

- rota: `GET /api/runtime/literals/{locale}`;
- protegido pela mesma sessao runtime das telas de producao;
- carregado pelo frontend quando `config.literals.enabled=true`;
- mergeado sobre o catalogo interno pt-BR;
- se o endpoint falhar ou a chave nao existir, o motor usa o dicionario embutido e, por fim, o texto legado do payload.

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

## Politica de relatorios e documentos

O produto agora separa explicitamente tres trilhas:

- `reports`: consultas formatadas, impressao e exportacao;
- `special_document`: documentos internos com visual mais fechado;
- `regulated_document`: documentos de alto rigor com pipeline proprio de preparo, emissao, hash, artefato e conferencia;
- bridge PHP interna de impressao: camada tecnica comum para geracao de artefato, entrega ao cliente e transporte fisico futuro;
- engine externa ou modulo especializado: documentos oficiais, homologados ou de layout rigido.

Na camada `reports`, tambem existe opcionalmente `report.authenticity.enabled`, que grava um hash `sha256` da emissao no banco separado de auditoria e permite conferencia publica posterior por `production/report-authenticity.html`. O contrato ainda aceita `report.authenticity.storage.storeCanonicalPayload` e `storeExportArtifact` para guardar, opcionalmente, o payload canonico da emissao e o artefato exportado no mesmo banco separado.

A bridge de impressao agora tambem aceita `printing.deliveryMode=qz_tray` para PDF em `reports`, `special_document` e `regulated_document`, sempre por metadado fechado e com entrega local no cliente via `window.qz`. Nao existe nesta fase spooler gerenciado pelo backend nem suporte a linguagens brutas de impressora.

Referencia funcional: `docs/politica-reports-documentos.md`.
Referencia tecnica: `docs/arquitetura-printing-bridge.md`.

## Runtime generico de entidade

O backend pode executar CRUD generico com `handler="entity.crud"` em `runtime_endpoint`.
Nesse modo, `runtime_endpoint.config.entityCode` aponta para `builder_entity.code`, e `builder_field` define campos permitidos, chave primaria, tipos e coluna fisica.
O CRUD generico usa DBAL com identificadores validados, filtra `values`, aplica regras de negocio registradas e grava transacao/logs pelo runtime.

Regras simples ficam nos metadados. Regras complexas devem ser handlers PHP registrados no backend, nunca codigo livre vindo do banco.
O Doctrine Subscriber fica apenas como fallback de auditoria para alteracoes via Doctrine fora do fluxo runtime.

As regras configuradas por entidade ficam em `builder_entity.metadata.rules`.
Cada regra pode informar:

- `type`: `requiredWhen` ou `class_method`;
- `phase`: `beforeValidate`, `beforePersist`, `afterPersist` ou `afterCommit`;
- `order`: ordem crescente de execucao;
- `continueOnError`: se `true`, agrega o erro e segue para a proxima regra configurada;
- `className` e `methodName` quando usar classe, restritos a `App\Runtime\BusinessRule\*`;
- `params`: objeto JSON com configuracao adicional para o metodo.

Toda regra configurada gera log em `runtime_transaction_log`.
Regras por classe/metodo tambem podem registrar logs proprios usando `RuntimeBusinessRuleContext::log(...)`.
Nesta etapa, classes configuradas precisam ter construtor sem argumentos obrigatorios.

## Construtor visual de programas

O backend possui um fluxo inicial para modelar entidades persistentes e gerar programas CRUD novos a partir delas.

Esse fluxo usa:

- `builder_entity` e `builder_field`: metadados da entidade e dos campos;
- `builder_entity_version`: historico imutavel das revisoes da modelagem, com snapshot completo e rollback;
- `builder_program_version`: historico imutavel das versoes salvas;
- `runtime_entity_record_version`: snapshots imutaveis de registros de cadastros mestres versionados;
- `builder_program`: programa publicado corrente;
- `screen_definition`: tela publicada corrente;
- `runtime_endpoint`: endpoints fechados criados automaticamente no publish;
- `ProgramBuilderService` e `ProgramBuilderController`: servico e API do construtor.

Endpoints atuais:

- `GET /api/admin/program-builder/bootstrap`: lista modulos estruturais, entidades e programas existentes;
- `POST /api/admin/program-builder/modules`: cadastra ou atualiza modulo estrutural com abreviacao e faixa numerica inicial/final;
- `GET /api/admin/program-builder/entities/{entityCode}`: carrega a modelagem atual da entidade;
- `POST /api/admin/program-builder/entity-versions/{id}/restore`: restaura a modelagem da entidade e tenta voltar o schema fisico para a revisao escolhida;
- `POST /api/admin/program-builder/entities`: salva metadados da entidade e, quando pedido, cria ou complementa a tabela fisica;
- `GET /api/admin/program-builder/programs/{programCode}`: carrega o historico de versoes do programa;
- `POST /api/admin/program-builder/preview`: gera preview real da definicao sem persistir;
- `POST /api/admin/program-builder/drafts`: salva ou atualiza um rascunho;
- `POST /api/admin/program-builder/versions/{id}/publish`: publica a versao selecionada;
- `POST /api/admin/program-builder/versions/{id}/duplicate`: cria novo rascunho com incremento de versao;
- `POST /api/admin/program-builder/database/inspect-ddl`: analisa `CREATE TABLE` e devolve rascunhos sem executar o SQL;
- `POST /api/admin/program-builder/database/import-ddl`: importa o rascunho gerado de SQL/DDL para entidade e opcionalmente programa CRUD;
- `POST /api/admin/program-builder/external/validate`: valida JSON externo e devolve rascunho normalizado para revisao.

Novos conceitos de ownership e customizacao:

- `programOrigin = standard | customer_overlay | customer_custom`;
- `ownerScope = system | subscriber`;
- `customizationPolicy = locked | overlay_only | full_override_allowed`;
- `subscriberId`, `baseProgramCode`, `baseProgramVersionId`, `upgradeFrozen` e `frozenReason` no catalogo/versionamento do programa.

Fluxo atual:

- programa padrao continua versionado em `builder_program_version`;
- overlay por assinante usa `builder_program_overlay` + `builder_program_overlay_version`;
- variante especifica do cliente usa `customer_custom`;
- o runtime tenta resolver `customer_custom`, depois `customer_overlay`, depois `standard`.

Para programas padrao, a publicacao passou a poder exigir gate de governanca:

- solicitacao formal em `program_change_request`;
- grant temporario em `program_change_grant`;
- lock de autoria reaproveitando `builder_editor_lock` com `grantId`;
- bundle de testes em `program_test_execution`;
- aprovacao final em `program_publication_approval`.

Na esteira atual, publicar programa `standard/system` exige:

- grant ativo do usuario;
- lock ativo do programa e da entidade base, ambos vinculados ao grant;
- bundle de testes executado;
- aprovacao final ativa.

Primeiro escopo suportado:

- somente `pageType="crud"`;
- modelagem visual de entidade persistente;
- selecao visual de `entityType` (`persistence`, `query`, `io`);
- cadastro visual de modulo estrutural com abreviacao e faixa numerica inicial/final sem sobreposicao;
- criacao inicial da tabela fisica e inclusao de colunas novas;
- rename de tabela e de coluna quando a sincronizacao fisica estiver habilitada;
- alteracao de tipo, tamanho, precision/scale, obrigatoriedade e default de colunas existentes;
- exclusao controlada de colunas removidas quando o usuario habilitar essa opcao;
- historico proprio da modelagem da entidade, com revisoes numeradas e restauracao explicita;
- rollback estrutural de tabela, colunas e constraints gerenciadas pelo construtor;
- cadastro mestre versionado com snapshot JSON imutavel por registro;
- referencia automatica para a versao corrente do cadastro mestre em entidades transacionais;
- campo virtual para leitura de dado historico via snapshot, sem criar coluna fisica extra;
- cadastro visual de regras de negocio da entidade, com ordem, fase, continuidade apos erro e classe/metodo;
- chaves unicas compostas no nivel da entidade, alem de `unique` por campo;
- campo marcado como nao editavel, refletindo em `readonly/writable`;
- dependencias/FKs com classificacao, `onDelete` e `onUpdate`;
- classificacao estrutural de tabela (`main`, `composition`, `specific_relation`, `aggregation`, `recursive`, `multi_level`, `view`);
- sugestao visual do nome fisico da tabela a partir do modulo, numero base e relacionamentos estruturais;
- validacao do codigo do programa informado manualmente por modulo, no formato `abreviacao + 4 digitos`, como `cd0101`;
- validacao de nomenclatura para tabela e coluna fisica conforme padrao Genesis-ERP;
- preview backend do JSON real;
- historico com `draft`, `published` e `archived`;
- re-publicacao de versoes anteriores pela mesma acao de publish;
- duplicacao de qualquer versao para continuar a evolucao sem perder historico.

Limites atuais:

- `query` e `io` ja podem ser cadastrados na modelagem, mas o fluxo completo de tabela fisica + geracao CRUD continua fechado em `persistence`;
- os snapshots historicos ficam em tabela generica `runtime_entity_record_version`, nao em uma tabela `_version` separada por entidade;
- situacao/transicoes avancadas ainda nao possuem editor visual dedicado nessa tela.

A interface visual fica em `program-builder.html` e `production/program-builder.html`.
Ela agora usa composicao mais proxima de editor em Kendo, com `Splitter`, `TreeView` e `TabStrip`: arvore lateral para navegar por modulo/entidade/programa, filtros rapidos por tipo/estado, badges visuais por no, abas centrais para os formularios e painel lateral para preview, historicos e diagnostico de pendencias.
Ela usa `CrudHttpClient`, portanto respeita token, sessao e tenant ja usados nas outras entradas do projeto.
Antes de usar a interface, o backend precisa ter as migrations `Version20260509093000`, `Version20260510113000`, `Version20260510143000` e `Version20260510170000` aplicadas; sem isso a API responde `PROGRAM_BUILDER_STORAGE_NOT_READY`.

O construtor agora tambem possui um assistente visual de historico para entidades transacionais:

- escolhe um cadastro mestre versionado;
- escolhe o campo origem, como `produto_id` ou `cliente_id`;
- gera automaticamente o campo fisico `*_version_id`;
- gera automaticamente campos virtuais `*_historico` lendo o snapshot salvo;
- usa `runtime_entity_record_version` como tabela fisica de referencia da versao.

O construtor tambem passou a aceitar o tipo de campo `custom_code`.

Para esse tipo, a modelagem guarda um contrato fechado:

- `customCode.mode`: `pattern` ou `static_method`;
- `customCode.prefix`: prefixo literal opcional;
- `customCode.pattern`: padrao declarativo com tokens como `{YYYY}`, `{MM}`, `{DD}`, `{SEQ:4}`, `{ENTITY:campo}` e `{PROMPT:propriedade}`;
- `customCode.sequenceEnabled`, `customCode.sequenceScope` e `customCode.sequencePadding`;
- `customCode.staticClass` e `customCode.staticMethod`, restritos a classes `App\Runtime\CustomCode\*`;
- `customCode.assistantScreenId`: tela auxiliar segura carregada por `screenId`, usando `pageType="process"` para coletar propriedades e devolver `result.type="properties"`;
- `customCode.promptTitle` e `customCode.promptFields[]` para abrir um assistente declarativo antes do salvar.

O frontend apenas coleta propriedades extras do assistente e envia em `_customCode`. Esse assistente pode ser um popup declarativo simples ou uma tela auxiliar segura aberta por `screenId`. A tela auxiliar tambem pode devolver `previewCode`, para o usuario confirmar a previsao do codigo antes de aplicar as propriedades. O valor final continua sendo gerado no backend. Para controlar a sequencia, o backend usa a tabela `runtime_custom_code_sequence`.

O primeiro assistente seguro fechado no backend e `screenId=assistente.codificacao.produto-pdm`, com endpoint `process` e handler `process.customCode.pdm`. Ele devolve `result.type="properties"`, `previewTitle`, `previewCode` e `values` saneados para o formulario.

Para regras por classe/metodo, existe uma classe de exemplo em [ConfiguredEntityRuleMethods.php](C:/construtor-pg/backend/src/Runtime/BusinessRule/ConfiguredEntityRuleMethods.php).

O padrao recomendado para essas classes agora e:

- manter as chaves de literal como constantes da propria classe;
- devolver `messageKey/messageParams` quando retornar array;
- usar `RuntimeBusinessRuleContext::messageItem(...)` para montar mensagens por campo;
- usar `RuntimeBusinessRuleContext::throwValidation(...)` quando a regra precisar interromper o fluxo com `RuntimeValidationException`;
- deixar `message` literal apenas como fallback de compatibilidade.

Exemplo resumido:

```php
$context->throwValidation(
    'CLIENTE_OBSERVACAO_REQUIRED',
    [
        $context->messageItem('observacao', 'validation.message.field_required', [
            'fieldLabel' => 'Observacao',
        ]),
    ],
);
```

No executor, `messageKey` ja e tratado como contrato de primeira classe. Se a regra devolver apenas chave e parametros, o frontend resolve o texto pelo catalogo de literais sem depender de string fixa dentro da classe.

Padrao de nomenclatura agora validado no construtor:

- tabelas persistentes novas/renomeadas: `t1`, `t1c1`, `t1e1`, `t1r`, `t1m`, `t1e2at2e3`;
- views novas/renomeadas: `v1`;
- colunas novas/renomeadas:
  - `dt_` para data;
  - `dt_hr_` para data/hora;
  - `si_`, `i_` ou `bi_` para inteiro sem FK;
  - sufixo `_id` para FK;
  - `log_` para logico;
  - `c_` para texto curto;
  - `t_` para texto longo;
  - `d_` para decimal;
  - `u_` ou `id_` para campos participantes de chave unica.

### Modelo para historico sem replicacao

Para entidades mestre como pessoa, endereco e produto, o construtor agora pode marcar a entidade como `cadastro versionado`.

Quando isso estiver ativo:

- cada `create` ou `update` feito pelo runtime gera snapshot imutavel em `runtime_entity_record_version`;
- se o snapshot relevante ficar identico ao ultimo e `deduplicate=true`, a versao existente e reaproveitada;
- entidades transacionais podem gravar apenas a FK do cadastro atual e a FK da versao historica capturada no momento da operacao.

Padrao recomendado:

- mestre: `produto`, `pessoa`, `endereco`;
- transacional: `nota_fiscal`, `nota_fiscal_item`, `pedido_item`;
- campo de referencia atual: `produto_id`;
- campo de referencia historica: `produto_version_id`;
- campo virtual de leitura historica: por exemplo `produto_nome_historico`, usando `versionSnapshot.versionField=produto_version_id` e `versionSnapshot.path=nome`.

Assim a tabela transacional nao replica o cadastro inteiro, mas continua exibindo os dados da epoca corretamente.

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

### Frequencia de busca em filtros lookup

O `CrudFilterRenderer` agora suporta prioridade e persistencia de registros mais usados em filtros `lookup` com `editor="searchWindow"`.

Contrato atual do filtro:

- `frequentLimit`: quantidade maxima de itens frequentes mostrados na janela; `0` ou `false` desliga o recurso;
- `frequentEndpoint`: endpoint fechado opcional para buscar a lista frequente no backend;
- `frequentRecordEndpoint`: endpoint fechado opcional para gravar o uso no backend; quando nao vier preenchido, o frontend tenta usar `dataSource.api.recordLookupUsage` se existir.

Comportamento:

- a janela mostra botoes `Todos` e `Mais usados`;
- sem backend, continua funcionando por `localStorage`, separado por `screenId`, `runtimeUserId` e `filterId`;
- com backend, o runtime pode persistir e consultar a frequencia por usuario/tela/filtro, sem URL livre.

Persistencia backend atual:

- migration `Version20260531113000`;
- tabela `user_lookup_usage`;
- handlers runtime:
  - `layout.recordLookupUsage`
  - `layout.lookupFrequent`

Payloads fechados:

- gravacao:

```json
{
  "filterId": "clienteId",
  "field": "clienteId",
  "items": [
    { "value": "123", "text": "Cliente Acme" }
  ]
}
```

- consulta:

```json
{
  "filterId": "clienteId",
  "field": "clienteId",
  "limit": 5
}
```

Retorno esperado da consulta:

```json
{
  "items": [
    { "value": "123", "text": "Cliente Acme" }
  ]
}
```

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
- `admin.programa-solicitacoes`: solicitacoes formais para alteracao de programa padrao;
- `admin.programa-grants`: grants temporarios de edicao/publicacao;
- `admin.programa-grants-operacao`: entrada focada para grants com contexto direto por notificacao;
- `admin.programa-testes`: bundles e execucoes de roteiros obrigatorios;
- `admin.programa-aprovacoes`: aprovacao final para publish governado;
- `admin.programa-aprovacoes-operacao`: entrada focada para aprovacoes com contexto direto por notificacao;
- `admin.programa-retencao-operacao`: entrada focada para ajuste rapido da retencao;
- `admin.programa-overlays-operacao`: entrada focada para listar overlays, revisar congelamento e abrir preview de rebase;
- `admin.programa-overlay-versoes-operacao`: entrada focada para historico, comparacao e publicacao das versoes do overlay;
- `admin.programa-overlays`: overlays e variantes por assinante;
- `admin.programa-overlay-versoes`: historico versionado dessas customizacoes.

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

## Rastreabilidade de transacao

`runtime_transaction.requestContext` e `runtime_transaction_log.metadata` agora podem receber um bloco tecnico de rastreabilidade com:

- `programCode`;
- `programVersion`;
- `builderProgramVersionId`;
- `entityCode`;
- `builderEntityVersionId`;
- `screenId`;
- `screenDefinitionVersion`;
- `schemaFingerprint`;
- `databaseIdentity`;
- `databaseEnvironment`;
- `customizationKind`;
- `subscriberId`;
- `grantId`;
- `requestCode`;
- `approvalId`;
- `testExecutionBundleId`.

Objetivo:

- auditar qual versao de programa e de estrutura gerou a operacao;
- distinguir base padrao, overlay e variante especifica;
- identificar o ambiente/banco que executou a gravacao;
- ligar a transacao aos controles de governanca quando a operacao vier de publicacao ou alteracao sensivel.

O bloqueio real, expiracao por entidade/programa/acao, auditoria e validacao de versao ficam no backend.

## EventBus Runtime

O backend possui uma primeira camada incremental de EventBus declarativo. Ela nao substitui regras, jobs, notificacoes ou logs atuais: apenas orquestra acontecimentos e registra tudo nas mesmas tabelas operacionais.

Tabelas novas:

- `runtime_event`: outbox dos eventos publicados, com `eventCode`, origem, tenant, usuario, tela, programa, entidade, registro, payload, status e transacao original quando existir.
- `runtime_event_subscription`: assinaturas declarativas por evento, com filtro simples, handler fechado, prioridade, tentativa maxima e template de idempotencia.
- `runtime_event_delivery`: execucao de cada assinatura, com status, tentativas, ultimo erro, resultado, transacao operacional e chave de idempotencia.

Eventos publicados inicialmente:

- CRUD generico: `runtime.entity.created`, `runtime.entity.updated`, `runtime.entity.deleted`, `runtime.entity.status_changed`.
- Program Builder: `builder.program.published`, `builder.entity.versioned`, `builder.overlay.rebased`.
- Jobs: `runtime.job.completed`, `runtime.job.failed`.

Handlers fechados da primeira versao:

- `notification`: cria notificacao runtime administrativa.
- `job`: agenda job existente em `runtime_async_job`.
- `log`: grava log operacional na transacao da entrega.
- `integration` e `webhook`: aceitam apenas contrato cadastrado (`integrationCode`/`webhookCode`), sem URL livre no JSON.

Logs padronizados em `runtime_transaction_log`:

- `runtime.event.published`;
- `runtime.event.subscription.started`;
- `runtime.event.subscription.completed`;
- `runtime.event.subscription.failed`;
- `runtime.event.subscription.skipped_idempotent`.

Quando o evento nasce dentro de uma transacao runtime, o log de publicacao fica vinculado a ela. Quando a assinatura roda no worker, o EventBus abre uma transacao operacional propria. A mensagem `App\Runtime\RuntimeEventMessage` usa o transporte async do Symfony Messenger.

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

## Impersonacao administrativa

O backend possui impersonacao administrativa auditavel para suporte e simulacao de problemas.

Fluxo:

- `POST /api/auth/impersonate/start` cria uma nova sessao `runtime_user_session` para o usuario alvo.
- A acao tambem pode ser chamada pela tela `admin.usuarios` via endpoint runtime seguro `runtime.admin.impersonateStart`.
- Exige usuario autenticado com `admin.impersonate`.
- Exige `targetTenantId`, `targetUsername` e `reason`.
- Usuario alvo inativo e bloqueado.
- Entrar como outro administrador exige `admin.impersonate.admin`.
- Sessao impersonada nao gera `rememberToken` e tem validade curta, padrao 60 minutos.
- `POST /api/auth/impersonate/stop` revoga somente a sessao impersonada atual.

A sessao impersonada guarda `sessionProperties.impersonation` e `permissionSnapshot.impersonation` com:

- administrador original;
- sessao original;
- usuario alvo;
- justificativa;
- IP, user-agent, inicio e validade.

Durante a simulacao, o usuario alvo e o usuario efetivo para permissoes e execucao. A auditoria preserva o administrador original: `runtime_transaction.requestContext` recebe o bloco `impersonation` e `runtime_transaction_log.metadata` recebe automaticamente `impersonation`, `effectiveUserId`, `originalUserId` e `impersonationReason`.

## Auditoria de consultas analytics

A camada `analytics` agora tambem pode gravar trilha de consulta em banco separado, sem misturar com o banco principal do runtime.

Configuracao:

- `ANALYTICS_AUDIT_ENABLED=1`
- `ANALYTICS_AUDIT_DATABASE_URL=...`
- `ANALYTICS_AUDIT_MAX_ROWS=200`
- `ANALYTICS_AUDIT_STRICT=0`

Com isso, cada `analytics.query.run` e `analytics.materialize` pode registrar:

- `tenantId`, `userId`, `sessionId`
- `screenId`, `datasetId`, `viewId`
- filtros, parametros e ordenacao
- `executionMode`, origem do resultado (`live`, `cache_hit`, `materialize`, `error`)
- colunas devolvidas
- recorte das linhas consultadas, limitado por `ANALYTICS_AUDIT_MAX_ROWS`
- total retornado e erro quando existir

Operacao:

- comando `php bin/console app:analytics:audit:init` cria a tabela `runtime_analytics_audit_entry` no banco configurado;
- por padrao, falha na escrita da auditoria nao derruba a consulta;
- se precisar endurecer isso, usar `ANALYTICS_AUDIT_STRICT=1`.
