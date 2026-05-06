# Instrucoes para Codex neste projeto

Responda de forma simples e direta.

Antes de fazer alteracoes relevantes, leia estes arquivos:

- `CONTEXTO_PROJETO.md`
- `docs/arquitetura-crud-engine.md`
- `docs/padroes-ui-kendo.md`
- `docs/continuidade-codex.md`

Regras importantes:

- A pasta `kendo/` e biblioteca de terceiro. Nao alterar.
- O projeto e HTML simples, sem build inicial.
- Usar Kendo UI for jQuery local e jQuery local.
- Manter cultura e mensagens pt-BR.
- Nao usar `alert`, `confirm` ou `prompt` nativos do browser.
- Nao permitir template livre, `eval` ou JavaScript vindo do JSON.
- Sempre atualizar os exemplos quando uma funcionalidade nova for implementada.
- Sempre que mudar demonstracao, exemplos ou mocks, executar a skill `construtor-pg-demo-production-parity` e atualizar `docs/paridade-demo-producao.md`.
- Para validar interface, usar as paginas locais em `file:///C:/construtor-pg/...`.
