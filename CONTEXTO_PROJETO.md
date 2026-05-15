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
- Existem telas administrativas runtime para parametros, literais/traducoes, notificacoes, destinatarios de notificacoes, sessoes, transacoes, logs de transacoes e jobs.
- As demos ainda podem usar dados mockados em memoria por `DemoMockHttpClient`.
- A pasta `kendo/` nao deve ser alterada.

Paginas principais:

- `home.html`: demo de pagina inicial por JSON, com appbar, seletor de sistema/modulo, Kendo TreeView lateral e abertura de programas.
- a Home agora tambem pode exibir central de notificacoes no appbar, com endpoint proprio ou agregacao de `alerts`, `requests` e `jobs`. Quando houver endpoint dedicado, o backend ja suporta notificacoes por usuario/grupo e marcacao de leitura por destinatario.
- a Home tambem ja possui backend real para chat entre usuarios e atendimento: contatos por sessoes ativas, historico de conversa em `runtime_user_message`, atendentes online por grupos `support`/`support.<setor>`, solicitacoes persistidas em `runtime_support_request` e canal SSE proprio para novas mensagens, presenca e atualizacao de protocolo.
- `login.html`: demo visual de login com appbar, logo, lembrar acesso, selecao simulada de assinante, escolha de area para administrador e recuperacao de senha.
- `index.html`: demo principal de clientes.
- `exemplos.html`: indice central de exemplos.
- `theme-builder.html`: pagina de teste/geracao visual de temas.
- `program-builder.html`: interface visual para cadastrar modulos estruturais com abreviacao e faixa numerica inicial/final, modelar entidades `persistence`, `query`, `io` e agora `api`, criar tabela fisica quando fizer sentido, versionar a estrutura da entidade, configurar cadastro mestre versionado, usar assistente para referencias historicas, definir campo de codificacao customizada, cadastrar regras de negocio declarativas ou por classe/metodo, configurar chaves unicas compostas, marcar campos nao editaveis, dependencias/FKs com acoes e validar nomenclatura de tabela e campo conforme padrao Genesis-ERP, gerando programas CRUD ou custom a partir do catalogo runtime, com preview, historico de versoes, rollback e publicacao. Para `entityType=api`, o projeto agora cobre consulta externa em JSON/array, grid + formulario de visualizacao, cadastro reutilizavel de metadados da API com importacao OpenAPI/Swagger e um primeiro modo CRUD para APIs JSON previsiveis, com `create/update/delete` declarativos, sem tabela fisica, sem lock de escrita e sem JavaScript livre. Tambem existe suporte inicial a Odoo como provedor especifico dentro do cadastro de APIs, em modo somente leitura, com configuracao de `XML-RPC` e `JSON-RPC`, teste de conexao, leitura de metadados do modelo por `fields_get`, carga automatica dos campos na entidade API e publicacao de tela CRUD em modo consulta. A tela agora usa layout mais proximo de editor com arvore lateral, filtros rapidos por tipo/estado, badges por no, abas centrais e painel lateral de preview, propriedades, relacionamentos, comparativo e diagnostico, alem de reordenacao visual por arrastar e soltar, validacao incremental por item, lock de edicao por entidade/modulo/programa, importacao de tabelas PostgreSQL existentes para entidade + rascunho CRUD, importacao de JSON externo validado pelo backend antes de carregar a modelagem e assistente interno de IA com `kendoChat`, configuracao segura por parametros administrativos, entrada por texto/audio e carga do rascunho validado para revisao manual.
- o frontend CRUD agora tambem possui catalogo interno de literais pt-BR para mensagens operacionais e de validacao, com suporte a `titleKey/titleParams` e `messageKey/messageParams` retornados pelo backend, preservando fallback para textos legados.
- `import_export_mapping`: catalogo inicial de integracoes entre entidades e arquivos, com preview e execucao manual por endpoint administrativo. A primeira entrega suporta origem em entidade `persistence`, `api` generica e `api` Odoo readonly; destino em entidade local, API generica JSON previsivel, `csv` e `txt_layout`. No TXT agora existem tres formas de estruturar os registros: posicional fixo (`lineMode="fixed"`), por separador (`lineMode="delimited"`) e arvore hierarquica com `nodeType=record|group|totalizer`, adequada para leiautes com pai, filho e totalizadores.
- `desktop-wpf/`: MVP separado em WPF para validar uma experiencia desktop de arvore de objetos, propriedades contextuais e preview JSON, sem alterar o fluxo web atual.
- `examples/pages/*.html`: paginas isoladas por variacao de configuracao.
- `examples/pages/manual-programas.html`: manual operacional e funcional navegavel por programa, com indice em `TreeView`.
- `production/app.html`: entrada de producao para CRUD por `screenId`.
- `production/app.html`: entrada de producao por `screenId`, cobrindo `crud`, `process` e `custom`.
- `production/home.html`: entrada de producao para Home por `screenId`.
- `production/login.html`: entrada de producao para login, manter logado, selecao de assinante, escolha de area para administrador e recuperacao de senha.
- `production/program-builder.html`: entrada da interface visual do construtor ligada ao backend real.
- `production/app.html?screenId=admin.literais`: tela administrativa para manter literais e traducoes por locale, carregadas pelo frontend via bundle runtime com fallback para o dicionario pt-BR embutido.
- `production/app.html?screenId=admin.notificacoes`: tela administrativa para cadastrar notificacoes runtime por usuario ou grupo.
- `production/app.html?screenId=admin.notificacao-destinatarios`: tela administrativa para acompanhar entrega e leitura por destinatario.

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

- Branch usada ate agora: `master`.
- O ultimo commit local conhecido antes destes arquivos foi `7f38d707 Implement CRUD engine demo`.
- O repositorio nao tinha remote configurado quando foi tentado `git push`.

Como iniciar uma nova sessao:

1. Leia este arquivo.
2. Leia `docs/arquitetura-crud-engine.md`.
3. Leia `docs/padroes-ui-kendo.md`.
4. Se for alterar codigo, leia `docs/continuidade-codex.md`.
5. Se for alterar a pagina inicial, leia `docs/arquitetura-home-engine.md`.
6. Se for alterar demo, exemplos ou mocks, use a skill `construtor-pg-demo-production-parity` e leia `docs/paridade-demo-producao.md`.
7. Depois leia apenas os arquivos especificos da funcionalidade.
