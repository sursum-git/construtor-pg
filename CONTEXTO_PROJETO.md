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
- Existem telas administrativas runtime para parametros, sessoes, transacoes, logs de transacoes e jobs.
- As demos ainda podem usar dados mockados em memoria por `DemoMockHttpClient`.
- A pasta `kendo/` nao deve ser alterada.

Paginas principais:

- `home.html`: demo de pagina inicial por JSON, com appbar, seletor de sistema/modulo, Kendo TreeView lateral e abertura de programas.
- `login.html`: demo visual de login com appbar, logo, lembrar acesso, selecao simulada de assinante, escolha de area para administrador e recuperacao de senha.
- `index.html`: demo principal de clientes.
- `exemplos.html`: indice central de exemplos.
- `theme-builder.html`: pagina de teste/geracao visual de temas.
- `program-builder.html`: interface visual para cadastrar modulos estruturais com abreviacao e faixa numerica inicial/final, modelar entidades, criar tabela fisica, versionar a estrutura da entidade, configurar cadastro mestre versionado, usar assistente para referencias historicas, definir campo de codificacao customizada, cadastrar regras de negocio declarativas ou por classe/metodo, configurar chaves unicas compostas, marcar campos nao editaveis, dependencias/FKs com acoes e validar nomenclatura de tabela e campo conforme padrao Genesis-ERP, gerando programas CRUD a partir de `builder_entity`, com preview, historico de versoes, rollback e publicacao.
- `examples/pages/*.html`: paginas isoladas por variacao de configuracao.
- `production/app.html`: entrada de producao para CRUD por `screenId`.
- `production/home.html`: entrada de producao para Home por `screenId`.
- `production/login.html`: entrada de producao para login, manter logado, selecao de assinante, escolha de area para administrador e recuperacao de senha.
- `production/program-builder.html`: entrada da interface visual do construtor ligada ao backend real.

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
