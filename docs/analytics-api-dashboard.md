# Dashboard Analytics com fonte API registrada

`analytics.presentation.mode=dashboard` usa o AnalyticsEngine existente. O modo padrão de analytics continua disponível. A definição é produzida pelo Builder, via `analyticsConfig.apiDashboard`; editor/importação/exportação preservam esse bloco. A configuração tem datasets, dashboard, navigationHosts e navigation. Não aceita código livre.

Exemplo completo publicável: `integrations/iol-inicial-gerencial/metadata/builder-draft.json`, com as entidades e fonte no mesmo pacote. Segredo da fonte é fornecido somente no cadastro protegido; `__RUNTIME_SECRET__` é marcador, não credencial operacional.

```json
{
  "id": "resumo",
  "source": {"type": "api", "entityCode": "iol_gerencial_resumo_progress"},
  "fields": [{"field": "faturamento_liquido", "format": "c2"}],
  "limit": 1
}
```

Cada entidade deve ser API, ter operação list cadastrada e a permissão `<entityCode>.read`. A operação é selecionada no servidor. O adaptador ignora o envelope de rastreamento do dispatcher, encaminha apenas paginação/ordenação permitidas e força `read` da entidade declarada. Cabeçalhos de identidade vêm da sessão ativa pelo transporte Sursum. Não aceitar login, URL, SQL, cabeçalhos ou parâmetros comerciais do navegador.

Dashboards de APIs enviam `datasetIds` em uma única chamada ao endpoint de consulta. O servidor valida todos os IDs e permissões antes das leituras e responde com `datasets.<id>`. Endpoints externos idênticos (URL, método, identidade/cabeçalhos, corpo, timeout e contrato) compartilham a resposta **somente dentro dessa requisição**. O escopo é descartado em `finally`, inclusive após erro; não existe reutilização entre usuários ou atualizações. O orçamento retém até10respostas, mas leituras extras de lookup seguem normalmente sem retenção. O IOL registra o mesmo corpo `read/painel` nas duas operações list e projeta `data.resumo`/`data.vendedores`: o PASOE coleta/calcula uma única vez.

Esses datasets sempre executam live, antes de qualquer lookup de cache. Materialização é recusada. Paginação interna em páginas de até500até o limite declarado (máximo5000), validando resposta/tipos/total. Não compartilha resultados entre sessões. Valores ausentes não se tornam zero.

Cartões permitidos: `kpi`, `progress`, `list`, `links`, `group`; agrupamento até3níveis. Referências de campo/dataset são validadas, IDs únicos e formatos fechados (`n0`, `n2`, `c2`, `percent0`, `percent2`). Percentuais já chegam em escala0–100; só a barra visual é limitada0–100. Texto é escapado. Grupos usam KendoButton com aria-expanded e estado inicial declarativo.

Listas aceitam `labelCase: "title"` para apresentar a inicial de cada palavra em maiúscula e as demais letras em minúsculas, com suporte a acentos. O padrão preserva o texto original; `none` também preserva. A transformação é somente visual, após receber os dados, mantendo escape HTML e sem alterar cadastro ou valores da API. Exemplo em `integrations/iol-inicial-gerencial/metadata/builder-draft.json`, cartão de vendedores.

Links referenciam apenas navigationId do catálogo, HTTPS em hosts autorizados, sem credenciais/query/fragment/porta. Abrem com noopener/noreferrer. Não recebem tokens de integração. Cada dataset é consultado uma vez por atualização; erro em qualquer consulta limpa o painel. Resposta após destroy é ignorada. Competência de resumo é exibida no status quando fornecida.

Verificação: `tests/frontend/analytics-dashboard-smoke.mjs`; testes PHP de Config, Builder roundtrip, API dataset e cache. Relatório operacional em `D:/opencode/conversao-sc/docs/integracao-iol-inicial-gerencial-validacao.md`. Demos/mocks não foram alterados, pois a skill de paridade exigida pelo AGENTS não estava disponível; o pacote de metadados é o exemplo real desta extensão.
