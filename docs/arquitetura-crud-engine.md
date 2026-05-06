# Arquitetura do CRUD Engine

O motor segue a ideia:

```text
Backend decide.
Frontend renderiza.
```

Esse mesmo principio tambem existe para a pagina inicial via `HomeEngine`.
O `HomeEngine` e separado do `CrudEngine`: ele monta o shell global e pode chamar um CRUD como um dos tipos fechados de programa.

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

## Seguranca de producao

- `config.security.mode="production"` ativa padroes conservadores.
- `definitionSource.requireScreenId=true` exige que a tela seja carregada por identificador.
- `definitionSource.allowDirectDefinition=false` bloqueia JSON direto vindo da camada chamadora.
- `definitionSource.allowDefinitionUrl=false` bloqueia URL livre para definicao de tela.
- `endpoints.allowInlineUrls=false` bloqueia URLs livres em APIs e acoes.
- `endpoints.requireEndpointIds=true` exige `endpointId` ou `actionId`.
- `documents.allowInlineUrls=false` bloqueia links diretos para logs/documentos quando configurado.
- O frontend continua validando o JSON, mas permissao real, tenant, dados e acoes precisam ser validados no backend.
