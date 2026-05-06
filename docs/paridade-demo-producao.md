# Paridade Demo x Producao

Este arquivo controla o que mudou na demonstracao e o que ainda precisa ser levado, bloqueado ou descartado para producao.

Use junto com a skill local `construtor-pg-demo-production-parity`.

## Regra

Sempre que houver alteracao em demo, exemplos, mocks ou paginas locais de demonstracao, atualizar esta matriz antes de concluir a tarefa.

Arquivos normalmente considerados demo:

- `index.html`
- `home.html`
- `exemplos.html`
- `theme-builder.html`
- `examples/**`
- `src/demo/**`
- `src/examples/**`
- JSONs de exemplo em `examples/*.json`

## Status

- `ja-compativel`: a producao ja usa o mesmo motor/recurso.
- `pendente`: precisa aplicar em `production/`, configuracao segura, schema, validador ou bootstrap.
- `nao-aplicavel`: recurso existe apenas para demonstracao.
- `bloqueado-backend`: depende de backend real, tenant, permissao, banco ou persistencia por usuario.
- `revisar-seguranca`: pode afetar JSON, URLs, documentos, eventos, HTML ou chamadas de API.

## Checklist

- Identificar arquivos de demo alterados no `git status --short`.
- Conferir se a mudanca exige atualizar exemplos e opcoes de propriedades.
- Conferir se a producao continua carregando por `screenId`.
- Conferir se a producao nao carrega mock, JSON local de `examples/` ou dados embedded.
- Conferir se APIs de producao usam `endpointId` ou `actionId`.
- Registrar abaixo o status e a acao necessaria.

## Matriz

| Data | Recurso | Arquivos de demo | Impacto em producao | Status | Acao necessaria | Observacao |
| --- | --- | --- | --- | --- | --- | --- |
| 2026-05-06 | Camada segura inicial de producao por `screenId` | `examples/pages/seguranca-producao.html`, `src/examples/examples-catalog.js`, `src/demo/DemoMockHttpClient.js` | Criadas entradas separadas em `production/` e configuracao segura inicial | `ja-compativel` | Nenhuma imediata | Sem backend real, producao ainda depende de endpoints futuros |
| 2026-05-06 | Controle de paridade demo x producao | `AGENTS.md`, `docs/continuidade-codex.md`, `docs/paridade-demo-producao.md` | Obriga registro das proximas divergencias antes de fechar tarefa | `ja-compativel` | Executar a skill quando demo mudar | Skill local: `construtor-pg-demo-production-parity` |
| 2026-05-06 | Assinante corrente no cabecalho da Home | `examples/home.home.json`, `src/demo/home-embedded-data.js`, `src/examples/examples-catalog.js` | Motor, schema e validador tambem foram atualizados; producao recebe o valor pelo JSON autorizado da Home | `ja-compativel` | Nenhuma imediata | Valor e apenas informativo; backend deve validar tenant/assinante nas APIs |
