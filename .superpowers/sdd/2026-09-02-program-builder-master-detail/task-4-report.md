# Task 4 — Smoke da tela mestre-detalhe publicada

## Resultado

- Foi criado `tests/frontend/program-builder-master-detail-smoke.mjs` para abrir `production/app.html` somente por `screenId`.
- A definição é produzida em tempo de teste por `ProgramBuilderService::previewDraft()` com as entidades Builder `pedido_venda`, `pedido_item` e `pedido_parcela`.
- O runtime é um mock fechado do smoke: entrega registros somente pelos endpoints `master.read` e `detail.*.read`, sem `records` na definição ou HTML de domínio.
- O smoke valida desktop (1366px), reduzido (768px), abas Itens/Parcelas, uma única chamada `createGraph`, erro estruturado dentro da janela Kendo e ausência de diálogo nativo.
- Foi incluído o comando `npm run test:program-builder-master-detail`.

## RED/GREEN

### RED

O smoke foi criado antes da fixture de Builder e executado em seguida. Falhou como esperado porque a fixture ainda não existia:

```text
Could not open input file: .../backend/tests/Builder/ProgramBuilderMasterDetailFixture.php
```

### GREEN

A fixture foi implementada usando o serviço real do Builder e o smoke passou, junto com as regressões mestre-detalhe.

## Testes executados

```text
php bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php
OK (9 tests, 43 assertions)

npm run test:program-builder-master-detail
program builder master detail smoke ok

node tests/frontend/master-detail-smoke.mjs
master detail smoke ok

node tests/frontend/master-detail-create-flow-smoke.mjs
master detail create flow smoke ok

node --check tests/frontend/program-builder-master-detail-smoke.mjs
git diff --check
```

## Segurança e paridade

- A definição testada não contém `records`, URL livre, template ou JavaScript vindo de metadado.
- O erro do runtime segue o contrato estruturado e é exibido na janela Kendo; o smoke monitora e rejeita qualquer diálogo nativo.
- Não foram alterados exemplos, páginas de demonstração ou `DemoMockHttpClient`; portanto, não há atualização de `docs/paridade-demo-producao.md` nesta Task.

## Preocupações

- A fixture é intencionalmente de teste e depende das dependências de desenvolvimento do backend (PHPUnit); ela não cria ou publica dados persistentes no runtime real.
- A futura ligação a um endpoint Symfony real para `createGraph` permanece fora do escopo desta Task; o contrato visual e transacional é protegido pelo runtime mock fechado.
