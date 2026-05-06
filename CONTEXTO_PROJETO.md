# Contexto do projeto

Este projeto e um motor frontend dinamico para telas CRUD usando JSON, Kendo UI for jQuery e jQuery.

Objetivo principal:

- Renderizar telas CRUD a partir de uma definicao JSON.
- Manter um padrao visual e comportamental reutilizavel.
- Servir como base futura para migrar sistemas existentes para telas configuradas por metadados.

Decisoes fechadas:

- Aplicacao separada na raiz do projeto.
- Frontend em HTML simples, sem build.
- Kendo local em `kendo/`.
- jQuery local em `vendor/jquery/jquery-4.0.0.min.js`.
- Tema atual principal: `kendo/styles/default-urban.css`.
- Nao existe backend real nesta etapa.
- Dados e persistencias sao mockados em memoria por `DemoMockHttpClient`.
- A pasta `kendo/` nao deve ser alterada.

Paginas principais:

- `home.html`: demo de pagina inicial por JSON, com appbar, seletor de sistema/modulo, Kendo TreeView lateral e abertura de programas.
- `index.html`: demo principal de clientes.
- `exemplos.html`: indice central de exemplos.
- `theme-builder.html`: pagina de teste/geracao visual de temas.
- `examples/pages/*.html`: paginas isoladas por variacao de configuracao.
- `production/app.html`: entrada de producao para CRUD por `screenId`.
- `production/home.html`: entrada de producao para Home por `screenId`.

Arquivos de configuracao e dados:

- `examples/home.home.json`: JSON completo da demo da pagina inicial.
- `examples/clientes.crud.json`: JSON completo da demo.
- `public/metadata/schemas/home-definition-v1.schema.json`: schema inicial da home.
- `public/metadata/schemas/crud-definition-v1.schema.json`: schema inicial.
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
