# Arquitetura do CRUD Engine

O motor segue a ideia:

```text
Backend decide.
Frontend renderiza.
```

Esse mesmo principio tambem existe para a pagina inicial via `HomeEngine`.
O `HomeEngine` e separado do `CrudEngine`: ele monta o shell global e pode chamar um CRUD como um dos tipos fechados de programa.

Nesta etapa ainda nao existe backend real. O JSON vem de arquivo/local embed e as chamadas passam pelo mock HTTP.

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

src/demo/
  demo.js
  home-demo.js
  home-embedded-data.js
  demo-embedded-data.js
  DemoMockHttpClient.js

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

## Interface publica atual

```js
new CrudEngine({
  root,
  definitionUrl,
  definition,
  config,
  configUrl,
  hideThemeSwitch,
  httpClient
}).init()
```

`hideThemeSwitch=true` desativa o seletor claro/escuro do CRUD quando a tela e aberta dentro de um shell que ja possui controle global de tema.

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

## JSON de tela

Niveis importantes:

- `program`: titulo, subtitulo, versao, ajuda e logs da tela.
- `permissions`: permissoes visuais.
- `dataSource`: endpoints e API.
- `dataModel`: campos, tipos e chave primaria.
- `crud.query`: paginacao e ordenacao inicial.
- `crud.filter`: filtros.
- `crud.grid`: grid, colunas, mobile, impressao, acoes em massa e IA.
- `crud.form`: formulario, campos, abas, etapas, eventos, logs, impressao e outras acoes.
- `crud.userLayout`: leiautes, filtros, ordenacoes e agrupamentos salvos.

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
