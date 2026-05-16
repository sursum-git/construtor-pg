# Contexto do projeto

Este projeto e um motor frontend dinamico para telas CRUD usando JSON, Kendo UI for jQuery e jQuery.

Objetivo principal:

- Renderizar telas CRUD a partir de uma definicao JSON.
- Renderizar telas de processamento por parametros a partir de uma definicao JSON.
- Manter um padrao visual e comportamental reutilizavel.
- Servir como base futura para migrar sistemas existentes para telas configuradas por metadados.

Decisoes fechadas:

- Aplicacao separada na raiz do projeto.
- Frontend em HTML simples, sem build.
- Kendo local em `kendo/`.
- jQuery local em `vendor/jquery/jquery-4.0.0.min.js`.
- Tema atual principal: `kendo/styles/default-urban.css`.
- Existe backend inicial em `backend/` com Symfony, API Platform, PostgreSQL, runtime por metadados, auditoria, fila, sessao, autenticacao, permissoes reais por tela/acao, selecao de assinante, manter logado e escolha de area para administrador.
- O runtime agora tambem registra rastreabilidade estrutural nas transacoes, incluindo `programVersion`, `builderProgramVersionId`, `builderEntityVersionId`, `screenDefinitionVersion`, `schemaFingerprint`, `databaseIdentity`, `databaseEnvironment`, `customizationKind`, `grantId`, `requestCode`, `approvalId` e `testExecutionBundleId` quando aplicavel.
- Existem telas administrativas runtime para parametros, literais/traducoes, notificacoes, destinatarios de notificacoes, integracoes, sessoes, transacoes, logs de transacoes e jobs.
- As demos ainda podem usar dados mockados em memoria por `DemoMockHttpClient`.
- A pasta `kendo/` nao deve ser alterada.

Paginas principais:

- `home.html`: demo de pagina inicial por JSON, com appbar, seletor de sistema/modulo, Kendo TreeView lateral e abertura de programas.
- a Home agora tambem pode exibir central de notificacoes no appbar, com endpoint proprio ou agregacao de `alerts`, `requests` e `jobs`. Quando houver endpoint dedicado, o backend ja suporta notificacoes por usuario/grupo, marcacao de leitura por destinatario e navegacao contextual direta para a tela de destino com filtros/querystring segura.
- a Home tambem ja possui backend real para chat entre usuarios e atendimento: contatos por sessoes ativas, historico de conversa em `runtime_user_message`, atendentes online por grupos `support`/`support.<setor>`, solicitacoes persistidas em `runtime_support_request` e canal SSE proprio para novas mensagens, presenca e atualizacao de protocolo. O envio continua por `POST`; consultas de lista/status usam `GET` na mesma rota runtime (`/api/runtime/screens/{screenId}/endpoints/{endpointId}`) e o stream usa `?stream=1`.
- `login.html`: demo visual de login com appbar, logo, lembrar acesso, selecao simulada de assinante, escolha de area para administrador e recuperacao de senha. O login agora tambem sabe carregar o bundle runtime de literais por locale, com fallback pt-BR embutido.
- `index.html`: demo principal de clientes.
- `exemplos.html`: indice central de exemplos.
- `theme-builder.html`: pagina de teste/geracao visual de temas.
- `program-builder.html`: interface visual para cadastrar modulos estruturais com abreviacao e faixa numerica inicial/final, modelar entidades `persistence`, `query`, `io` e agora `api`, criar tabela fisica quando fizer sentido, versionar a estrutura da entidade, configurar cadastro mestre versionado, usar assistente para referencias historicas, definir campo de codificacao customizada, cadastrar regras de negocio declarativas ou por classe/metodo, configurar chaves unicas compostas, marcar campos nao editaveis, dependencias/FKs com acoes e validar nomenclatura de tabela e campo conforme padrao Genesis-ERP, gerando programas CRUD ou custom a partir do catalogo runtime, com preview, historico de versoes, rollback e publicacao. Para `entityType=api`, o projeto agora cobre consulta externa em JSON/array, grid + formulario de visualizacao, cadastro reutilizavel de metadados da API com importacao OpenAPI/Swagger e um primeiro modo CRUD para APIs JSON previsiveis, com `create/update/delete` declarativos, sem tabela fisica, sem lock de escrita e sem JavaScript livre. Tambem existe suporte inicial a Odoo como provedor especifico dentro do cadastro de APIs, em modo somente leitura, com configuracao de `XML-RPC` e `JSON-RPC`, teste de conexao, leitura de metadados do modelo por `fields_get`, carga automatica dos campos na entidade API e publicacao de tela CRUD em modo consulta. A tela agora usa layout mais proximo de editor com arvore lateral, filtros rapidos por tipo/estado, badges por no, abas centrais e painel lateral de preview, propriedades, relacionamentos, comparativo e diagnostico, alem de reordenacao visual por arrastar e soltar, validacao incremental por item, lock de edicao por entidade/modulo/programa, importacao de tabelas PostgreSQL existentes para entidade + rascunho CRUD, importacao de JSON externo validado pelo backend antes de carregar a modelagem, assistente de integracao para gerar esqueleto de mapping a partir da entidade atual e assistente interno de IA com `kendoChat`, configuracao segura por parametros administrativos, entrada por texto/audio, carga do rascunho validado para revisao manual e consumo do bundle runtime de literais na propria interface.
- o construtor agora tambem registra ownership e politica de customizacao do programa (`standard`, `customer_overlay`, `customer_custom`), aplica gate de governanca para programa padrao com grant + lock + bundle de testes + aprovacao final, possui dialogs dedicados para governanca e rebase assistido de overlay, mostra checklist guiado antes do publish, exibe dashboard operacional com requests/grants/aprovacoes/testes, permite operar requests, grants e bundles no proprio dialogo, ajustar a retencao da governanca sem depender apenas do CRUD administrativo generico, aplicar politica de ambiente para publicacao e preparar a resolucao runtime entre base padrao, overlay e variante especifica por assinante. Existe tambem a tela dedicada `admin.programa-governanca` para operar requests, grants, bundles, aprovacoes, retencao e rebase fora do editor, alem das entradas focadas `admin.programa-grants-operacao`, `admin.programa-aprovacoes-operacao` e `admin.programa-retencao-operacao` para operacao direta por notificacao/contexto.
- existe agora a tela administrativa `admin.integridade`, baseada em `system_record_integrity`, para monitorar o ultimo status de verificacao das assinaturas estruturais, disparar reassinatura controlada pela UI administrativa por `endpointId` seguro e operar em conjunto com os comandos `app:integrity:check`, `app:integrity:monitor` e `app:integrity:resign`.
- existe tambem a politica inicial de retencao de historico de governanca, com comando `app:governance:cleanup-history` para avaliar/aplicar limpeza de requests, grants, aprovacoes, bundles de teste e notificacoes administrativas antigas. A retencao agora pode ser parametrizada por `admin.parametros` e tambem ajustada no dialogo de governanca do `program-builder`.
- os registros estruturais principais do builder/runtime agora podem ser protegidos por assinatura de integridade em `system_record_integrity`, com checagem no backend para detectar alteracao fora do fluxo oficial. A cobertura inclui programa, versao, entidade, revisao da entidade, situacoes e transicoes da entidade, tela, endpoint, overlay, versao de overlay, `builder_api_source`, `builder_module`, `runtime_lock_policy`, `system_parameter`, `system_parameter_value`, `system_option_list`, `system_option`, `import_export_mapping`, `import_export_mapping_version` e `import_export_schedule`. A reassinatura controlada registra `auditTrail` com motivo, usuario, horario, hash anterior e status antes/depois.
- o frontend CRUD agora tambem possui catalogo interno de literais pt-BR para mensagens operacionais e de validacao, com suporte a `titleKey/titleParams` e `messageKey/messageParams` retornados pelo backend, preservando fallback para textos legados.
- `import_export_mapping`: catalogo inicial de integracoes entre entidades e arquivos, com preview, execucao manual, historico persistido, versionamento do proprio mapping e agendamento basico. A primeira entrega suporta origem em entidade `persistence`, `api` generica, `api` Odoo readonly e arquivo XML declarativo; destino em entidade local, API generica JSON previsivel, `csv`, `xml` e `txt_layout`. No TXT agora existem tres formas de estruturar os registros: posicional fixo (`lineMode="fixed"`), por separador (`lineMode="delimited"`) e arvore hierarquica com `nodeType=record|group|totalizer`, adequada para leiautes com pai, filho e totalizadores. Em XML, a engine agora tambem aceita raiz com namespaces/atributos, nos hierarquicos em `xmlLayouts[]`, atributos por no, filhos repetitivos por `sourceAlias`, vinculo pai/filho por `linkBy` e importacao por `recordPath + fields[].xpath`.
- `desktop-wpf/`: MVP separado em WPF para validar uma experiencia desktop de arvore de objetos, propriedades contextuais e preview JSON, sem alterar o fluxo web atual.
- `examples/pages/*.html`: paginas isoladas por variacao de configuracao.
- `examples/pages/manual-programas.html`: manual operacional e funcional navegavel por programa, com indice em `TreeView`.
- `examples/pages/admin-integridade.html`: pagina local de smoke da tela administrativa de integridade estrutural.
- `examples/pages/admin-program-governance.html`: pagina local de smoke da tela dedicada de governanca de programas.
- `examples/pages/admin-program-grants.html`: pagina local de smoke da operacao focada em grants.
- `examples/pages/admin-program-approvals.html`: pagina local de smoke da operacao focada em aprovacoes.
- `examples/pages/admin-program-retention.html`: pagina local de smoke da operacao focada em retencao.
- `examples/pages/program-builder-governance.html`: pagina local de smoke do fluxo governado do construtor, com solicitacao, grant, bundle de testes, aprovacao, publish e rebase de overlay.
- `production/app.html`: entrada de producao para CRUD por `screenId`.
- `production/app.html`: entrada de producao por `screenId`, cobrindo `crud`, `process` e `custom`.
- `production/home.html`: entrada de producao para Home por `screenId`.
- `production/login.html`: entrada de producao para login, manter logado, selecao de assinante, escolha de area para administrador e recuperacao de senha.
- `production/program-builder.html`: entrada da interface visual do construtor ligada ao backend real.
- `production/app.html?screenId=admin.literais`: tela administrativa para manter literais e traducoes por locale, carregadas pelo frontend via bundle runtime com fallback para o dicionario pt-BR embutido.
- `production/app.html?screenId=admin.notificacoes`: tela administrativa para cadastrar notificacoes runtime por usuario ou grupo.
- `production/app.html?screenId=admin.notificacao-destinatarios`: tela administrativa para acompanhar entrega e leitura por destinatario.
- `production/app.html?screenId=admin.integracoes`: tela administrativa para cadastro, preview e execucao manual de importacao/exportacao. A tela agora possui editor visual com `TreeView` para TXT/XML, inspetor de no, preview estrutural lado a lado, historico persistido de execucoes, historico de versoes do mapping e aba de agendamentos.
- `production/app.html?screenId=admin.programa-solicitacoes`: tela administrativa para solicitacoes formais de alteracao em programas padrao.
- `production/app.html?screenId=admin.programa-grants`: tela administrativa para grants temporarios de edicao/publicacao.
- `production/app.html?screenId=admin.programa-testes`: tela administrativa para bundles e execucoes de roteiros obrigatorios.
- `production/app.html?screenId=admin.programa-aprovacoes`: tela administrativa para aprovacao final de publicacao.
- `production/app.html?screenId=admin.programa-governanca`: tela dedicada para operar requests, grants, bundles, aprovacoes, retencao e rebase por programa.
- `production/app.html?screenId=admin.programa-grants-operacao`: entrada focada para grants, usada por notificacoes administrativas e operacao direta.
- `production/app.html?screenId=admin.programa-aprovacoes-operacao`: entrada focada para aprovacoes, usada por notificacoes administrativas e operacao direta.
- `production/app.html?screenId=admin.programa-retencao-operacao`: entrada focada para retencao, usada por notificacoes/contexto e ajuste rapido da politica.
- `production/app.html?screenId=admin.programa-overlays`: tela administrativa para overlays e variantes por assinante.
- `production/app.html?screenId=admin.programa-overlay-versoes`: tela administrativa para o historico versionado dessas customizacoes.

Arquivos de configuracao e dados:

- `examples/home.home.json`: JSON completo da demo da pagina inicial.
- `examples/clientes.crud.json`: JSON completo da demo.
- `examples/processamento-relatorio.process.json`: JSON completo da demo de processamento por parametros.
- `public/metadata/schemas/home-definition-v1.schema.json`: schema inicial da home.
- `public/metadata/schemas/crud-definition-v1.schema.json`: schema inicial.
- `public/metadata/schemas/process-definition-v1.schema.json`: schema inicial de processamento.
- `public/config/crud-engine.production.config.json`: configuracao segura para primeira versao de producao.
- `src/demo/demo-embedded-data.js`: dados e configuracao embutidos para uso via `file://`.
- `src/demo/DemoMockHttpClient.js`: mock HTTP em memoria.

Documentos importantes:

- `especificacao-crud-engine-v1.md`: especificacao inicial maior.
- `docs/arquitetura-home-engine.md`: arquitetura da pagina inicial por JSON.
- `docs/backlog-v1-estavel.md`: memoria de pontos para estabilizacao futura.
- `docs/roteiro-teste-web.md`: roteiro funcional para validar as telas web em demo e producao local.
- `docs/desktop-builder-mvp-wpf.md`: escopo e limites do MVP desktop em WPF.
- `docs/paridade-demo-producao.md`: controle do que mudou na demo e precisa, ou nao, ser levado para producao.
- `docs/guia-ia-padrao-kendo-grids-formularios.pdf`: guia para IA padronizar outro projeto Kendo/PHP/Symfony.
- `docs/guia-ia-padrao-kendo-grids-formularios.html`: fonte do PDF.

Estado do Git:

- Branch usada nesta linha de trabalho: `master`.
- O repositorio ja possui `origin` configurado e os pushes recentes foram executados por `origin/master`.

Como iniciar uma nova sessao:

1. Leia este arquivo.
2. Leia `docs/arquitetura-crud-engine.md`.
3. Leia `docs/padroes-ui-kendo.md`.
4. Se for alterar codigo, leia `docs/continuidade-codex.md`.
5. Se for alterar a pagina inicial, leia `docs/arquitetura-home-engine.md`.
6. Se for alterar demo, exemplos ou mocks, use a skill `construtor-pg-demo-production-parity` e leia `docs/paridade-demo-producao.md`.
7. Depois leia apenas os arquivos especificos da funcionalidade.
