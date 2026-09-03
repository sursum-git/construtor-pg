# Task 2: Persistir e publicar a definição

## Arquivos

- Alterar `backend/src/Builder/ProgramBuilderService.php` em `normalizeBuilderPayload()`, `saveDraft()`, `publishVersion()` e `programSchemaFingerprint()`.
- Alterar `backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php`.

## Contrato

Consumir `normalizeMasterDetailBuilderConfig()` já entregue na Task 1. Persistir o bloco em `builderConfig.masterDetailConfig` e publicar uma definição com `pageType=master_detail`. Manter `builderEntityCode` igual ao código da entidade mestre, para que histórico e rastreabilidade continuem coerentes.

## TDD obrigatório

1. Criar `testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition`: salvar `validProgramPayload()`, conferir `pageType=master_detail` e `builderConfig.masterDetailConfig.masterEntityCode=pedido_venda`; publicar e conferir definição/screenId.
2. Rodar `php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition`; registrar a falha RED esperada.
3. Incluir master_detail nos tipos aceitos e que exigem entidade, persistir `masterDetailConfig` somente para esse tipo e incluir master/details/createFlow no schema fingerprint.
4. Não alterar o despachante existente de `production/app.html`, que já suporta master_detail.
5. Rodar `php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php`; registrar GREEN.
6. Commitar com mensagem `Publica programas master detail pelo builder`.

## Restrições

- Não alterar kendo, UI, página de produção ou arquivos de exemplo.
- Não adicionar URL livre, SQL, JavaScript, template livre, eval, Function ou diálogo nativo.
- Não usar subagentes.
