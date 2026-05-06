# Continuidade para novas sessoes Codex

Use este arquivo para retomar o trabalho em outra sessao.

## Antes de mexer

1. Leia `CONTEXTO_PROJETO.md`.
2. Leia `docs/arquitetura-crud-engine.md`.
3. Leia `docs/padroes-ui-kendo.md`.
4. Confira `git status --short`.
5. Para alteracoes na pagina inicial, leia `docs/arquitetura-home-engine.md`.
6. Nao reverta alteracoes existentes sem pedido explicito do usuario.

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
require('./src/crud-engine/CrudUtils.js');
require('./src/page-engine/PageDefinitionNormalizer.js');
require('./src/crud-engine/CrudDefinitionValidator.js');
require('./src/demo/demo-embedded-data.js');
require('./src/examples/examples-catalog.js');
const catalog = global.CrudExamplesCatalog;
const validator = new global.CrudDefinitionValidator();
const errors = [];
catalog.list().forEach((item) => {
  try {
    validator.validate(catalog.buildDefinition(item.id));
  } catch (error) {
    errors.push({ id: item.id, error: error && error.details || error && error.message || String(error) });
  }
});
if (errors.length) {
  console.log(JSON.stringify(errors, null, 2));
  process.exit(1);
}
console.log('validated examples: ' + catalog.list().length);
console.log('property options: ' + catalog.getPropertyOptions().length);
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

## Quando implementar funcionalidade nova

Atualizar tambem:

- `src/examples/examples-catalog.js`;
- `src/examples/examples-index.js`, se a pagina principal precisar mostrar algo novo;
- `src/examples/examples-page.js`, se as paginas individuais precisarem suportar algo novo;
- `src/styles/examples-guide.css`, se houver mudanca visual nos exemplos;
- `docs/padroes-ui-kendo.md`, se virar padrao;
- `docs/backlog-v1-estavel.md`, se for algo para depois.

## Regras de seguranca

- Nao aceitar `template`, `eval` ou JavaScript livre vindo do JSON.
- JSON pode escolher opcoes fechadas, campos e URLs, mas nao deve injetar comportamento arbitrario.
- Permissao visual no frontend nao substitui validacao no backend.
- Em producao, backend deve validar tenant, usuario, registro, permissao e transicao.

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
- Chat opcional no appbar da pagina inicial, usando Kendo Chat com ComboBox de usuarios e endpoints configurados no JSON.
- Atendimento opcional no appbar da pagina inicial, com selecao de setor, chat quando houver atendente online no setor e solicitacao com setor travado quando nao houver disponibilidade.
- Chat de IA opcional no appbar da pagina inicial, usando Kendo Chat sem selecao de usuario e endpoints configurados no JSON.
- Alertas e solicitacoes opcionais no appbar da pagina inicial, com botoes compactos e janelas Kendo alimentadas por endpoints configurados no JSON.
- Pagina de exemplos com PanelBar e aba de configuracao por exemplo.
- Theme builder com preview.
- Guia PDF para orientar outra IA a padronizar projeto Kendo/PHP/Symfony.

## Pontos conhecidos para atencao

- Persistencias de layout/filtro/ordenacao/agrupamento ainda sao mock em memoria.
- Nao existe backend real, autenticacao, tenant ou banco de dados.
- Antes de uma versao estavel, revisar `docs/backlog-v1-estavel.md`.
- Push ainda depende de configurar remote Git.
