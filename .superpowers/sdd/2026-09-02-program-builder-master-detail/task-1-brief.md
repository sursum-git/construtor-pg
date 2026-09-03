# Task 1: Gerar e validar o contrato no backend

## Arquivos

- Criar `backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php`.
- Alterar `backend/src/Builder/ProgramBuilderService.php` em `normalizeBuilderPayload()`, `generateProgramDefinition()` e `programSchemaFingerprint()`.

## Contrato

Consumir `pageType: "master_detail"`, uma entidade mestre e `masterDetailConfig`. Produzir:

- `normalizeMasterDetailBuilderConfig(array $value, BuilderEntity $master): array`;
- `generateMasterDetailDefinition(array $config): array`.

O gerador deve produzir `schemaVersion`, `pageType=master_detail`, `screenId`, `program`, `permissions`, `master`, `details` e `createFlow`; não pode expor `url`.

## TDD obrigatório

1. Criar teste para `generateMasterDetailDefinition` com mestre `pedido_venda`, filha `pedido_item`, `parentField=pedido_id` e `createFlow.endpointId=createGraph`.
2. Rodar `php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testGenerateMasterDetailDefinitionBuildsGraph`; registrar falha RED porque o gerador ainda não existe.
3. Implementar a normalização e o gerador mínimos.
4. Rodar o mesmo teste e registrar o resultado GREEN.
5. Criar provider de rejeições para: filha repetida (`PROGRAM_BUILDER_MASTER_DETAIL_DETAIL_DUPLICATE`), FK inexistente (`PROGRAM_BUILDER_MASTER_DETAIL_PARENT_FIELD_INVALID`), campo/totais inválidos (`PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID`) e `draftWithChildren` sem endpoint (`PROGRAM_BUILDER_MASTER_DETAIL_CREATE_GRAPH_REQUIRED`).
6. Implementar esses erros por `RuntimeHttpException` estruturada, HTTP 422 e detalhes seguros.
7. Rodar `php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php`.
8. Fazer self-review e commit com `Adiciona geracao master detail no builder`.

## Restrições

- Não alterar `kendo/` nem arquivos de UI.
- Não usar URL livre, SQL, JavaScript, template livre, `eval`, `Function` ou diálogos nativos.
- Respeitar pt-BR e padrões existentes do serviço.
- Não usar subagentes.
