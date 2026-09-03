# Task 3: Editor declarativo e preview

## Arquivos

- Alterar `src/program-builder/program-builder.js` em `collectProgramPayload()`, `resetProgramForm()`, `handleProgramEntityChange()` e `renderLocalSummary()`.
- Alterar `src/program-builder/program-builder-properties.js` em `renderProgramProperties()`.
- Alterar `src/styles/program-builder.css`.
- Alterar `tests/frontend/program-builder-technical-smoke.mjs`.

## Contrato

Consumir apenas `this.state.entities` e campos existentes. Produzir `collectMasterDetailConfig()` no payload com `masterEntityCode`, `createFlow.mode`, `createFlow.endpointId` e detalhes. O tipo deve ser `master_detail`; não mostrar propriedades do modo custom.

## TDD obrigatório

1. Criar smoke que seleciona master_detail, escolhe `pedido_venda`, chama `addMasterDetail("pedido_item", "pedido_id")`, coleta payload e confirma `parentField`.
2. Rodar `node tests/frontend/program-builder-technical-smoke.mjs`; registrar RED esperado.
3. Implementar controles por Kendo ComboBox/DropDownList apenas com entidades/campos do Builder; toda alteração chama `schedulePreview()`. Não inserir URL.
4. Preview local deve conter master, details e createFlow; painel contextual mostra mestre, modo, número de filhos e endpointId.
5. Rodar `node --check src/program-builder/program-builder.js && node --check src/program-builder/program-builder-properties.js && node tests/frontend/program-builder-technical-smoke.mjs`; registrar GREEN.
6. Validar visualmente `file:///C:/construtor-pg/program-builder.html` em 1366px e 768px: controles/preview acessíveis e sem sobreposição.
7. Commitar como `Adiciona editor declarativo de master detail`.

## Restrições

- Não alterar kendo, production/app.html, exemplos ou mocks.
- Não usar alert, confirm, prompt, eval, Function, template livre, SQL, JavaScript ou URL livre.
- Não usar subagentes.
