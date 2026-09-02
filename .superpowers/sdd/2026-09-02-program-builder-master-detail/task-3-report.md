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

O smoke Playwright abre a página local do worktree por `file://` e valida as duas larguras solicitadas.

- Em 1366px, o contexto mestre-detalhe ficou em 392px, todos os cinco controles tiveram largura positiva e não houve sobreposição. O preview ficou visível.
- Em 768px, o contexto foi reduzido para 300px e os controles passaram a uma coluna, com largura positiva e sem sobreposição. O preview ficou visível.

Não foi necessário gerar screenshot: o teste confirma por `getBoundingClientRect()` as dimensões, a presença no viewport e a ausência de interseções entre os controles.

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
- A troca de entidade mestre chama a atualização imediata do inspetor e do preview, além da sincronização do estado mestre-detalhe.
- `endpointId` é normalizado no carregamento e em chamadas programáticas: somente `masterDetail.createGraph` é aceito em `draftWithChildren`; no modo `parentFirst` ele fica vazio.
- Não foram alterados `kendo/`, `production/app.html`, exemplos ou mocks; a paridade demo/produção não se aplica.

## Preocupações

- O endpoint seguro disponível na interface é o identificador fechado `masterDetail.createGraph`. Outros endpointIds aprovados pelo backend exigem ampliar uma fonte segura do catálogo, nunca entrada livre.
- A validação visual automática foi executada por Playwright porque a aba interna bloqueia `file://` do worktree. Não há pendência de validação para as larguras 1366px e 768px.

## Correções da revisão

### RED

O smoke ampliado falhou antes da correção de P1 ao trocar o mestre, pois o método público `handleProgramEntityChange` apenas sincronizava o estado e aguardava a atualização assíncrona, sem redesenhar imediatamente o inspetor:

```text
Error: A troca de mestre nao atualizou imediatamente o inspetor e as opcoes de filhos.
```

O teste visual também identificou que o contexto mestre-detalhe excedia a largura de 1366px antes de receber largura responsiva:

```text
Error: Controles mestre-detalhe sem acesso ou sobrepostos em 1366px.
```

### GREEN

Foram incluídos `renderPropertyInspector()` e `schedulePreview()` na troca do mestre. O estado de fluxo de criação passa por normalização fechada tanto em carregamentos quanto em chamadas programáticas. O CSS limita o contexto a 392px no desktop e 300px abaixo de 980px.

O comando abaixo retornou código 0 após as correções:

```text
node --check src/program-builder/program-builder.js
node --check src/program-builder/program-builder-properties.js
node tests/frontend/program-builder-technical-smoke.mjs
git diff --check
```

O smoke cobre a troca de mestre pelo painel de propriedades, atualização imediata do resumo/opções/preview, normalização de `endpointId` arbitrário em carga e chamada programática, além das verificações Playwright de 1366px e 768px.
