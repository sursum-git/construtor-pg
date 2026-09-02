# Task 2 — Persistir e publicar a definicao mestre-detalhe

## Resultado

- O teste integrado `testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition` cobre o rascunho e a publicacao de `pageType=master_detail`.
- Ele confirma que `builderEntityCode` permanece `pedido_venda`, que o bloco normalizado fica em `builderConfig.masterDetailConfig`, que a definicao gerada e a `screen_definition` publicada mantem `pageType=master_detail` e `screenId=vendas.pedidos`.
- A cobertura tambem prova que o fingerprint publicado muda se `master`, `details` ou `createFlow` forem removidos da definicao.

## Arquivos

- `backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php`
- `.superpowers/sdd/2026-09-02-program-builder-master-detail/task-2-report.md`

O codigo de producao necessario ja estava no commit aprovado da Task 1: ele normalizou `masterDetailConfig`, aceitou `master_detail` com mestre `persistence`, gerou a definicao e incluiu `master`, `details` e `createFlow` no fingerprint. A persistencia em `saveDraft()` e a publicacao em `publishVersion()` usam o caminho generico existente, que preserva `builderConfig`, `pageType`, `builderEntityCode` e a definicao. Nao foi introduzida duplicacao desse contrato.

## Evidencia RED/GREEN

### RED

Como a Task 1 ja havia deixado o comportamento de producao disponivel, o teste de persistencia/publicacao ficou verde ao ser exercitado corretamente. Para comprovar que a nova cobertura protege o comportamento, foi aplicada e revertida uma mutacao local e temporaria em `saveDraft()`:

```text
->setBuilderConfig([])
```

Execucao:

```text
php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition

FAILURES!
Failed asserting that null is identical to 'pedido_venda'.
Tests: 1, Assertions: 3, Failures: 1
```

A mutacao nao foi mantida. Ela demonstra que a regressao de nao persistir `masterDetailConfig` e detectada pelo teste.

### GREEN

Com o codigo restaurado:

```text
php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition
OK (1 test, 13 assertions)

php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php
OK (13 tests, 76 assertions)
```

Tambem executados:

```text
php -l src/Builder/ProgramBuilderService.php
php -l tests/Builder/ProgramBuilderServiceMasterDetailTest.php
git diff --check
```

Sem erros de sintaxe ou espacos em branco invalidados pelo diff.

## Self-review

- O teste usa entidades, modulos e versoes reais do dominio; os dublês ficam somente nos repositorios e na persistencia externa.
- A expectativa de configuracao e literal (`pedido_venda`) e falha se o bloco for descartado no salvamento.
- A publicacao verifica o `Program` e a `ScreenDefinition` criados, nao apenas chamadas de mocks.
- O fingerprint e confrontado com uma definicao sem os tres blocos mestre-detalhe, evitando um teste meramente de presenca.
- Nao houve URL livre, SQL, JavaScript, template, `eval`, `Function` ou dialogo nativo.
- Nao houve alteracao em UI, exemplos, mocks, demos, producao HTML ou `kendo/`; por isso a paridade demo/producao nao se aplica.
- A revisao foi feita localmente porque o requisito desta Task proibe subagentes.

## Preocupacoes

- Esta Task valida a persistencia e a publicacao do contrato. Ela nao amplia o despachante de `production/app.html`, os endpoints runtime nem a interface do Program Builder, conforme o escopo definido.
- O worktree possui artefatos de orquestracao em `.superpowers/`, que devem ser incluidos junto com o teste e este relatorio no commit da Task.
