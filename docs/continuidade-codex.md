# Continuidade para novas sessoes Codex

Use este arquivo para retomar o trabalho em outra sessao.

## Antes de mexer

1. Leia `CONTEXTO_PROJETO.md`.
2. Leia `docs/arquitetura-crud-engine.md`.
3. Leia `docs/padroes-ui-kendo.md`.
4. Confira `git status --short`.
5. Para alteracoes na pagina inicial, leia `docs/arquitetura-home-engine.md`.
6. Para alteracoes em demo, exemplos ou mocks, execute a skill `construtor-pg-demo-production-parity` e atualize `docs/paridade-demo-producao.md`.
7. Nao reverta alteracoes existentes sem pedido explicito do usuario.

## Comandos uteis

Validar sintaxe JS:

```powershell
node --check src\examples\examples-catalog.js
node --check src\examples\examples-index.js
node --check src\examples\examples-page.js
node --check src\crud-engine\CrudEngine.js
node --check src\crud-engine\CrudKendoGridRenderer.js
node --check src\crud-engine\CrudKendoFormRenderer.js
node --check src\home-engine\HomeEngine.js
node --check src\home-engine\HomeDefinitionLoader.js
node --check src\home-engine\HomeDefinitionValidator.js
```

Validar exemplos pelo catalogo:

```powershell
@'
global.window = global;
global.localStorage = {
  store: {},
  getItem(key) { return this.store[key] || null; },
  setItem(key, value) { this.store[key] = String(value); },
  removeItem(key) { delete this.store[key]; }
};
require('./src/crud-engine/CrudUtils.js');
require('./src/page-engine/PageDefinitionNormalizer.js');
require('./src/crud-engine/CrudDefinitionLoader.js');
require('./src/crud-engine/CrudDefinitionValidator.js');
require('./src/home-engine/HomeDefinitionValidator.js');
require('./src/demo/demo-embedded-data.js');
require('./src/demo/home-embedded-data.js');
require('./src/demo/DemoMockHttpClient.js');
require('./src/examples/examples-catalog.js');

(async function() {
  const catalog = global.CrudExamplesCatalog;
  const crudValidator = new global.CrudDefinitionValidator();
  const homeValidator = new global.HomeDefinitionValidator();
  const normalizer = new global.PageDefinitionNormalizer();
  const errors = [];

  for (const item of catalog.list()) {
    try {
      const config = catalog.buildConfig(item.id);
      const policy = global.CrudUtils.normalizeSecurityPolicy(config, {});
      if (item.id === 'home-engine') {
        homeValidator.validate(catalog.buildHomeDefinition(item.id), { securityPolicy: policy });
        continue;
      }
      if (item.loadByScreenId) {
        const http = new global.DemoMockHttpClient({ storageSuffix: 'validation-' + item.id });
        const raw = await new global.CrudDefinitionLoader({ httpClient: http }).load({
          screenId: item.screenId || item.id,
          securityPolicy: policy
        });
        crudValidator.validate(normalizer.normalize(raw), { securityPolicy: policy });
        continue;
      }
      crudValidator.validate(catalog.buildDefinition(item.id), { securityPolicy: policy });
    } catch (error) {
      errors.push({ id: item.id, error: error && error.details || error && error.payload || error && error.message || String(error) });
    }
  }

  if (errors.length) {
    console.log(JSON.stringify(errors, null, 2));
    process.exit(1);
  }
  console.log('validated examples: ' + catalog.list().length);
  console.log('property options: ' + catalog.getPropertyOptions().length);
})();
'@ | node -
```

Validar no browser:

- Usar Playwright/Chromium headless quando possivel.
- URLs principais:
  - `file:///C:/construtor-pg/index.html`
  - `file:///C:/construtor-pg/home.html`
  - `file:///C:/construtor-pg/exemplos.html`
  - `file:///C:/construtor-pg/theme-builder.html`
  - `file:///C:/construtor-pg/examples/pages/consulta-basica.html`
  - `file:///C:/construtor-pg/production/app.html?screenId=cadastros.clientes`
  - `file:///C:/construtor-pg/production/home.html?screenId=home`

## Quando implementar funcionalidade nova

Atualizar tambem:

- `src/examples/examples-catalog.js`;
- `src/examples/examples-index.js`, se a pagina principal precisar mostrar algo novo;
- `src/examples/examples-page.js`, se as paginas individuais precisarem suportar algo novo;
- `src/styles/examples-guide.css`, se houver mudanca visual nos exemplos;
- `docs/padroes-ui-kendo.md`, se virar padrao;
- `docs/backlog-v1-estavel.md`, se for algo para depois.

Se a funcionalidade entrar primeiro na demo, registrar em `docs/paridade-demo-producao.md` se ela ja e compativel com producao, se ficou pendente, se nao se aplica ou se depende de backend.

## Regras de seguranca

- Nao aceitar `template`, `eval` ou JavaScript livre vindo do JSON.
- JSON pode escolher opcoes fechadas, campos e URLs, mas nao deve injetar comportamento arbitrario.
- Permissao visual no frontend nao substitui validacao no backend.
- Em producao, backend deve validar tenant, usuario, registro, permissao e transicao.
- Para primeira versao de producao, usar `config.security.mode="production"`.
- Em producao, preferir `screenId` e bloquear `definition` direto/`definitionUrl` livre.
- Em producao, preferir `endpointId`/`actionId` e bloquear URLs livres de API no JSON.
- Entradas de producao ficam em `production/` e nao devem carregar mock ou JSON local de `examples/`.
- `CrudHttpClient({ allowLocalFallback: false })` deve ser usado nas entradas de producao.

## Estado atual de funcionalidades

Implementado ate agora, em nivel demo/frontend:

- CRUD de clientes com mock HTTP em memoria.
- Kendo Grid com paginacao, ordenacao, filtros, acoes de linha e exportacao.
- Filtro em janela, filtros salvos, filtros aplicados e edicao de filtro aplicado.
- Leiautes, ordenacoes e agrupamentos salvos no mock.
- Agrupamento com contagem/soma.
- Congelamento de colunas opcional para desktop.
- Mobile com modo colunas e modo template/card seguro.
- Formulario popup com abas, etapas, situacao, eventos seguros, logs, impressao e outras acoes.
- Tema claro/escuro por configuracao global.
- Pagina inicial por JSON com Kendo TreeView lateral, appbar e chamada de programas por `iframe`, `crud` e `html` sanitizado.
- Assinante/tenant corrente opcional no cabecalho global da pagina inicial via `currentSubscriber`.
- Chat opcional no appbar da pagina inicial, usando Kendo Chat com ComboBox de usuarios e endpoints configurados no JSON.
- Atendimento opcional no appbar da pagina inicial, com selecao de setor, chat quando houver atendente online no setor e solicitacao com setor travado quando nao houver disponibilidade.
- Chat de IA opcional no appbar da pagina inicial, usando Kendo Chat sem selecao de usuario e endpoints configurados no JSON.
- Alertas e solicitacoes opcionais no appbar da pagina inicial, com botoes compactos e janelas Kendo alimentadas por endpoints configurados no JSON.
- Pagina de exemplos com PanelBar e aba de configuracao por exemplo.
- Theme builder com preview.
- Guia PDF para orientar outra IA a padronizar projeto Kendo/PHP/Symfony.
- Camada inicial de seguranca de producao: carregamento por `screenId`, bloqueio opcional de JSON/URL livre, gateway runtime por `endpointId/actionId` e exemplo `seguranca-producao`.
- Entradas separadas de producao em `production/app.html` e `production/home.html`, com CSP em meta tag, sem inicializacao inline e sem fallback local no HTTP client.

## Pontos conhecidos para atencao

- Persistencias de layout/filtro/ordenacao/agrupamento ainda sao mock em memoria.
- Nao existe backend real, autenticacao, tenant ou banco de dados.
- Antes de uma versao estavel, revisar `docs/backlog-v1-estavel.md`.
- Push ainda depende de configurar remote Git.
