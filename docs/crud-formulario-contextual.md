# Formulario CRUD contextual por metadados

O Builder aceita `programDraft.formOptions` opcional em programas CRUD. A omissao conserva a grade e o formulario popup com aba Geral.

## Contrato fechado

- `mode`: `popup` (padrao) ou `embedded`.
- `openOnLoadField`: campo da PK integer, declarado em `contextFields` com `required:true`. Exige endpoint de detalhe `get`. O modo embedded exige esse campo.
- `returnScreenId`: screenId seguro opcional para o botao Voltar do formulario embedded em view. O retorno abre a tela declarada, sem URL livre. Links de linha capturam filtros, pagina/pageSize, ordenacao e agrupamento em sessionStorage, por par de screenIds e por sessao de aba; Voltar envia apenas restoreFrom com o screenId do painel. A origem valida campos e descritores, consome o estado uma vez (validade 30 minutos) e o aplica antes da primeira query, sem depender de bfcache. Estado ausente, expirado ou incompativel usa os defaults da tela. Nao ha botoes anterior/proximo no formulario embedded.
- `tabs`: lista de 1 a 20 abas. Cada aba tem `id` identificador unico e `title` de ate 160 caracteres.
- Aba de dados: `sections` com `id`, `title` opcional, `columns` opcional de 1 a 4 e `fields` como lista de codigos de campos da entidade. O Builder reutiliza os editores e as propriedades desses campos.
- Aba ligada: `type:"linkedPage"` e `linkedPage` com `screenId`, `engine:"crud"` opcional, `hideHeader` e `requireRecord` booleanos opcionais, `filters` opcional. Cada filtro tem `field` de destino, `sourceField` existente no mestre e `operator:"eq"` opcional.

Nao aceita URLs, templates livres, codigo, engines de processo/report ou definicoes de pagina embutidas neste contrato Builder. As paginas filhas continuam publicadas e autorizadas independentemente pelo screenId.

## Entrada e seguranca

O link de linha existente transporta o contexto pela URL `context__<campo>`. O motor valida os campos declarados, inclusive obrigatoriedade e integer positivo int32. A entrada contextual consulta o detalhe pela PK e abre em modo view. Nao instancia a grade inicial nem consulta list; embedded reutiliza o mesmo renderer dentro do root e popup conserva KendoWindow.

Contexto ausente/invalido, consulta negada ou PK divergente no retorno impedem a abertura. Atualizar repete get. Apos salvar, a visualizacao contextual e relida por get sem depender de grade; uma falha de releitura informa que o registro ja foi salvo. Apos excluir, a pagina exibe o estado de exclusao com Voltar e nao tenta consultar novamente o registro removido. Os filtros fixos das filhas continuam sendo reaplicados pelo contexto declarado. A API deve validar o vinculo/empresa e a permissao no servidor, sem confiar no navegador.

Readonly depende dos campos e das permissoes usuais: `allowCreate:false`, `allowUpdate:false`, `allowDelete:false`. Abrir uma pagina nao executa processos de negocio.

## Contexto nas operacoes externas

Uma operacao cadastrada de fonte API pode declarar `requestContextFields:["container_id"]`. A operacao exige POST. A lista aceita ate oito identificadores unicos em letras minusculas e cada campo deve existir na entidade como integer e e preservada nos snapshots/vinculos de endpoints. O detalhe frontend envia `context` contendo somente os valores validados de `contextFields` da pagina. A resolucao server-side deve encaminhar apenas os campos declarados pela operacao e recusar ausencia/conflitos antes de chamar a API externa.

## Verificacao

- PHPUnit: `ProgramBuilderFormOptionsTest` e `ProgramBuilderApiRequestContextTest`.
- Frontend Edge: `node tests/frontend/crud-contextual-form-smoke.mjs`, `crud-contextual-form-write-smoke.mjs` e `crud-row-link-return-state-smoke.mjs`.
- Regressao: contexto/colecoes, abas linkedPage e gate visual.

Esta capacidade generica nao publica por si uma tela de negocio nem cria fontes API. ProcessEngine e reports de entidades API permanecem fora deste contrato.

## Exemplo de rascunho Builder

```json
{
  "programDraft": {
    "pageType": "crud",
    "allowCreate": false,
    "allowUpdate": false,
    "allowDelete": false,
    "contextFields": [
      {
        "field": "container_id",
        "filter": true,
        "readonly": true,
        "required": true
      }
    ],
    "formOptions": {
      "mode": "embedded",
      "openOnLoadField": "container_id",
      "returnScreenId": "ciw.containers",
      "tabs": [
        {
          "id": "dados",
          "title": "Dados",
          "sections": [
            {
              "id": "principal",
              "title": "Container",
              "columns": 2,
              "fields": [
                "container_id",
                "nome_ab_forn"
              ]
            }
          ]
        },
        {
          "id": "itens",
          "title": "Itens",
          "type": "linkedPage",
          "linkedPage": {
            "screenId": "ciw.container-itens",
            "engine": "crud",
            "hideHeader": true,
            "requireRecord": true,
            "filters": [
              {
                "field": "container_id",
                "sourceField": "container_id",
                "operator": "eq"
              }
            ]
          }
        }
      ]
    }
  }
}
```

Os codigos ilustram o contrato; fonte, entidade, programa e screenIds devem estar cadastrados/publicados no destino. O exemplo nao altera esses cadastros.

## Posicao dos links de linha e booleanos

`rowLinks[].position` aceita somente `start` ou `end`; quando omitido continua no final. `start` coloca os links depois das colunas de acoes e selecao em lote, antes dos campos de dados, independentemente da ordem salva dos campos. Multiplos links preservam a ordem declarada. O contrato e preservado pelo Builder e na exportacao/importacao.

Exemplo: `{"id":"painel_container","label":"Painel do Container","screenId":"ciw.container-painel","position":"start","parameters":[{"field":"container_id","sourceField":"container_id"}]}`.

A grade apresenta campos boolean como Sim/Nao (com acento em Não), com vazio para null, inclusive no mobile; valores JSON permanecem boolean e o editor batch continua checkbox. Campos integer 0/1 continuam numericos salvo lookup explicito. Testes: `crud-row-link-position-smoke.mjs` e `crud-declared-format-smoke.mjs`.
