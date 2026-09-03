# Task 1 — Gerar e validar o contrato no backend

## Implementacao

- `ProgramBuilderService` aceita `pageType=master_detail` com entidade mestre `persistence`.
- `normalizeMasterDetailBuilderConfig()` normaliza o mestre, filhos, `parentField`, campos exibidos, totais e `createFlow`.
- Filhos sao resolvidos somente pelo catalogo de entidades; referencias invalidas retornam `RuntimeHttpException` HTTP 422 com detalhes seguros.
- `generateMasterDetailDefinition()` cria contrato declarativo com `schemaVersion`, `pageType`, `screenId`, `program`, `permissions`, `master`, `details` e `createFlow`.
- Operacoes do mestre e dos filhos usam apenas `endpointId`; `createFlow` nao expoe `url`.
- O fingerprint passou a considerar `master`, `details` e `createFlow`.

## Arquivos

- `backend/src/Builder/ProgramBuilderService.php`
- `backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php`

## Evidencia TDD

### RED

Com o teste novo criado, a primeira execucao no worktree identificou que `backend/vendor` nao estava instalado. As dependencias foram instaladas localmente no worktree, sem alterar o produto. Em seguida:

```text
php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testGenerateMasterDetailDefinitionBuildsGraph
ReflectionException: Method App\Builder\ProgramBuilderService::generateMasterDetailDefinition() does not exist
```

O erro confirma a ausencia do gerador antes da implementacao.

### GREEN

```text
php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testGenerateMasterDetailDefinitionBuildsGraph
OK (1 test, 5 assertions)
```

### Regressao focada

```text
php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php
OK (10 tests, 57 assertions)
```

Tambem executados sem erros:

```text
php -l src/Builder/ProgramBuilderService.php
php -l tests/Builder/ProgramBuilderServiceMasterDetailTest.php
git diff --check
```

## Self-review

- O tipo novo exige mestre `persistence`; cada filha tambem precisa ser `persistence` existente.
- Filha repetida, `parentField` inexistente, `displayFields`/totais invalidos e `draftWithChildren` sem `endpointId` retornam os codigos fechados requisitados em HTTP 422.
- O endpoint de `createGraph` aceita somente caracteres seguros de identificador; URL e demais propriedades livres de entrada nao sao reproduzidas na definicao.
- A definicao inclui campos declarativos, endpoints `master.*` e `detail.<entidade>.*`, sem SQL, JavaScript, template ou URL livre.
- Nao houve alteracao de UI, exemplos, mocks, demos ou `kendo/`; a verificacao de paridade demo/producao nao se aplica.

## Preocupacoes

- Esta tarefa gera e valida o contrato. A persistencia/publicacao de `masterDetailConfig` e a interface do Builder continuam nas tarefas subsequentes do plano.
- As dependencias PHP foram instaladas apenas no worktree para a execucao dos testes; `backend/vendor/` esta ignorado pelo Git.

## Commit

`5351eb25 Adiciona geracao master detail no builder`

## Fix da revisao

### Correcao

- `backend/src/Builder/ProgramBuilderService.php`: o tipo de um total agora e derivado do `dataType` catalogado no campo da entidade filha. Um `total.type` enviado no payload nao pode transformar campo textual em total numerico/currency.
- `backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php`: foram adicionados os casos de campo de total inexistente e campo textual enviado como `currency`.

### Evidencia

RED registrado antes da correcao:

```text
FAILURES!
campo textual nao pode ser total currency
A configuracao mestre-detalhe invalida deveria ser rejeitada.
Tests: 7, Assertions: 21, Failures: 1
```

GREEN apos a correcao:

```text
php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php
OK (12 tests, 63 assertions)
```

Tambem executados com sucesso: `php -l src/Builder/ProgramBuilderService.php`, `php -l tests/Builder/ProgramBuilderServiceMasterDetailTest.php` e `git diff --check`.

Commit da correcao: `91d60b52 Valida totais master detail pelo catalogo`.
