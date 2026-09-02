# Task 3 — Editor declarativo e preview mestre-detalhe

## Resultado

- O Program Builder agora lista `master_detail` como tipo de pagina e mantém a entidade base como mestre.
- O painel de propriedades apresenta editor declarativo com Kendo ComboBox/DropDownList para entidade filha, campo de vínculo, fluxo de criação e endpoint seguro `masterDetail.createGraph`; não há URL livre.
- `collectProgramPayload()` envia `masterDetailConfig` com `masterEntityCode`, `createFlow` e `details`. O preview local passa a conter `master`, `details` e `createFlow`.
- O painel contextual mostra mestre, modo, quantidade de filhos e endpoint ID. Os controles de modo custom não são renderizados para mestre-detalhe.

## RED/GREEN

### RED

Foi acrescentado ao smoke o cenário que seleciona `master_detail`, escolhe `pedido_venda`, chama `addMasterDetail("pedido_item", "pedido_id")` e coleta o payload. Antes da implementação, a execução falhou como esperado:

```text
TypeError: app.addMasterDetail is not a function
```

Essa falha prova que o smoke cobre a API declarativa ausente no editor.

### GREEN

Após a implementação:

```text
node --check src/program-builder/program-builder.js
node --check src/program-builder/program-builder-properties.js
node tests/frontend/program-builder-technical-smoke.mjs
git diff --check
```

Todos retornaram código 0. O smoke confirmou `pageType=master_detail`, mestre `pedido_venda`, filho `pedido_item`, `parentField=pedido_id`, preview local com `master/details/createFlow`, painel contextual presente e ausência do rótulo de modo custom.

## Validação visual

O smoke abre a página local do worktree por `file://` e valida a montagem do painel. O CSS usa duas colunas no desktop e uma coluna abaixo de 980px, preparada para 1366px e 768px.

A inspeção visual manual nas duas larguras não foi concluída: a aba interna bloqueou a URL `file://` do worktree por política de segurança e não foi usada outra superfície para contornar isso. A validação em `file:///C:/construtor-pg/program-builder.html` deve ser repetida depois de integrar o commit ao checkout principal.

## Arquivos

- `src/program-builder/program-builder.js`
- `src/program-builder/program-builder-properties.js`
- `src/styles/program-builder.css`
- `tests/frontend/program-builder-technical-smoke.mjs`
- `.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md`

## Self-review

- A lista de entidades filhas usa somente `this.state.entities` e aceita apenas `persistence`.
- O vínculo é aceito somente quando é campo conhecido da entidade filha; o teste cobre `pedido_id`.
- O endpoint é DropDownList fechado e não URL ou texto livre.
- Cada alteração persistente agenda novo preview.
- Não foram alterados `kendo/`, `production/app.html`, exemplos ou mocks; a paridade demo/produção não se aplica.

## Preocupações

- O endpoint seguro disponível na interface é o identificador fechado `masterDetail.createGraph`. Outros endpointIds aprovados pelo backend exigem ampliar uma fonte segura do catálogo, nunca entrada livre.
- A confirmação visual na página principal fica pendente da integração do commit, porque o navegador interno não libera `file://` do worktree nesta sessão.
