# Consultas CRUD por metadados

`programDraft.crudOptions` permite configurar consultas extensas pelo fluxo normal de preview, importação, rascunho e publicação. O builder gera a definição a partir dos campos da entidade e persiste a configuração em `builderConfig`. A exportação a devolve em `programDraft.crudOptions`, permitindo regenerar a mesma consulta.

```json
{
  "crudOptions": {
    "pageSize": 30,
    "gridFields": [
      { "field": "codigo", "width": 140 },
      { "field": "descricao", "width": 250 },
      { "field": "situacao", "hidden": true }
    ],
    "filterFields": ["codigo", "situacao"],
    "defaultSort": [{ "field": "codigo", "dir": "asc" }],
    "defaultGroup": [{ "field": "situacao", "dir": "asc" }]
  }
}
```

Os nomes do exemplo devem existir na entidade. A configuração aceita somente programas `pageType=crud` e as cinco propriedades acima; não aceita títulos, templates, fórmulas, JavaScript, SQL ou URLs. Títulos, tipos, permissões e propriedades técnicas continuam vindo dos metadados da entidade. As opções não habilitam escrita.

| Propriedade | Contrato |
|---|---|
| `pageSize` | Inteiro de 1 a 500. Gera `crud.query.defaultPageSize` e mantém `pageSize` compatível. |
| `gridFields` | Lista não vazia de até 100 objetos. Cada objeto aceita somente `field`, `hidden` booleano opcional e `width` inteiro opcional de 40 a 1200. Ordem preservada. |
| `filterFields` | Lista de até 64 nomes de campos. Ordem preservada. Tipos/operadores vêm dos metadados; números, datas e lógicos usam igualdade inicialmente. |
| `defaultSort` | Até oito objetos com somente `field` e `dir` (`asc` ou `desc`). |
| `defaultGroup` | Até três objetos com somente `field` e `dir` (`asc` ou `desc`). Habilita o agrupamento nativo da grade. |

Referências inexistentes, campos JSON, repetições, listas associativas, tipos incorretos e propriedades desconhecidas são recusados com 422 antes de gravar o rascunho. O tamanho máximo é validado, sem truncar silenciosamente. Colunas escolhidas explicitamente podem incluir campos fora da seleção inicial da entidade, todos já declarados e autorizados na mesma definição.

Quando `crudOptions` ou uma propriedade são omitidos, os defaults anteriores daquela propriedade permanecem: até seis colunas, cinco filtros, 20 linhas e ordenação pela chave. Listas vazias explícitas de filtros, ordenação ou agrupamento desativam o respectivo default; `gridFields` precisa conter pelo menos um campo.

No runtime, o agrupamento padrão está em `crud.query.defaultGroup`. O renderer o usa somente quando não existe escolha de agrupamento no leiaute do usuário. Um preset salvo continua tendo precedência; `userLayout.grid.group=[]` preserva a decisão de remover o agrupamento. O envelope vazio do runtime (`source=default`, sem `activeLayoutId` nem `activeGroupId`, `grid.group=[]`) é reconhecido como ausência de preferência. Um leiaute `user`/`user_global` ou preset ativo com lista vazia continua sendo uma escolha explícita. Nenhum leiaute de usuário é criado para simular um default global. O seletor de tamanhos inclui o `defaultPageSize` configurado (por exemplo, 30), sem duplicar as opções existentes. A paginação e o agrupamento continuam usando o motor Kendo existente, sem alterar bibliotecas de terceiro.

O exemplo real de conversão fica em `integrations/ciw-containers/builder-draft.json` quando integrado: consulta somente leitura, 20 colunas disponíveis (oito inicialmente ocultas), 11 filtros, 30 linhas e agrupamento por duas situações. Seu estado de cadastro/publicação deve ser consultado na documentação da integração; a existência do arquivo não confirma publicação.

Validação focal: `php vendor/bin/phpunit tests/Builder/ProgramBuilderCrudOptionsTest.php` em `backend/` e `node tests/frontend/crud-query-options-smoke.mjs` na raiz. O teste Edge usa uma página isolada e verifica a precedência de agrupamento, inclusive leiaute vazio explícito.

Validação em 16/10/2026: suíte PHP completa com 339 testes/2035 asserções; oito smokes Edge, incluindo consulta configurada, preservação no editor, lote, contextos, logs, resumo e página vinculada. Nenhum dado de negócio foi gravado por esses testes.

## Formatação de consultas

A grade, os valores móveis, agregados/grupos e o formulário readonly respeitam formatos numéricos declarados `n0` a `n10`, `c0` a `c10` e `p0` a `p10` (também o wrapper estrito `{0:n5}`), ou `decimals` inteiro entre 0 e 10. Formatos livres não são executados; os defaults numéricos anteriores permanecem quando a declaração está ausente ou inválida. INTEGER usa sempre zero casas quando formatado, mesmo diante de metadado decimal conflitante; IDs sem formato continuam simples. Booleanos readonly mostram Sim/Não, preservando false e zero.

DATE no contrato JSON (`YYYY-MM-DD`) representa um dia de calendário e é interpretado localmente. Isso evita deslocar 01/12/2026 para 30/11 no fuso UTC-3. Timestamps com horário/fuso mantêm a interpretação de instante. Um agrupamento inicial por campo do dataModel ausente das colunas usa uma coluna Kendo interna, oculta e fora do menu, somente para aplicar seu label/template nativo no cabeçalho e chip; a definição publicada de colunas não é alterada.

O teste `node tests/frontend/crud-declared-format-smoke.mjs` usa Edge, cultura pt-BR e timezone America/Sao_Paulo, com 5.12345, zero, false, duas datas DATE e um timestamp UTC. Nove smokes Edge passaram após esta correção; não houve mudança de PHP, ABL ou metadados publicados.
