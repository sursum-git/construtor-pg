# Program Builder Master Detail Implementation Plan

> Para agentes: use superpowers:subagent-driven-development ou superpowers:executing-plans. As etapas usam caixas de seleção.

**Goal:** Permitir que o Program Builder gere e publique programas master_detail consumidos pelo MasterDetailEngine, com validação gráfica automatizada.

**Architecture:** A configuração masterDetailConfig será persistida em builder_program_version.builder_config, normalizada pelo backend e convertida em pageType=master_detail. O editor só seleciona entidades e campos existentes; a tela publicada é carregada por screenId e suas operações usam endpointId.

**Tech Stack:** PHP/Symfony/Doctrine, JavaScript ES5, jQuery, Kendo UI, PHPUnit e Playwright.

**Spec:** docs/superpowers/specs/2026-09-02-program-builder-master-detail-design.md

## Global Constraints

- Não alterar kendo/.
- Manter Kendo UI for jQuery local, jQuery local e mensagens pt-BR.
- Não usar alert, confirm, prompt, template livre, eval, Function, SQL, JavaScript ou URL livre em metadados.
- A produção resolve tela por screenId e executa operações por endpointId ou actionId.
- Seguir TDD: teste falha, implementação mínima, teste passa.
- Não criar páginas HTML específicas de domínio.

---

## Estrutura de arquivos

- backend/src/Builder/ProgramBuilderService.php: normalização, geração, persistência e publicação.
- backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php: teste PHP novo do contrato e publicação.
- src/program-builder/program-builder.js: controles, coleta/restauração do payload e preview.
- src/program-builder/program-builder-properties.js: propriedades técnicas da composição.
- src/styles/program-builder.css: layout responsivo dos controles.
- tests/frontend/program-builder-master-detail-smoke.mjs: smoke do Builder e tela publicada.
- package.json: comando do novo smoke.
- docs/continuidade-codex.md: capacidade entregue e comando de validação.

## Contrato persistido

~~~json
{
  "masterDetailConfig": {
    "masterEntityCode": "pedido_venda",
    "createFlow": { "mode": "draftWithChildren", "endpointId": "createGraph" },
    "details": [{
      "entityCode": "pedido_item",
      "title": "Itens",
      "parentField": "pedido_id",
      "displayFields": ["produto", "quantidade", "valor_total"],
      "totals": [{ "field": "valor_total", "label": "Total dos itens", "type": "currency" }]
    }]
  }
}
~~~

### Task 1: Gerar e validar o contrato no backend

**Files:**
- Create: backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
- Modify: backend/src/Builder/ProgramBuilderService.php: normalizeBuilderPayload(), generateProgramDefinition(), programSchemaFingerprint()

**Interfaces:**
- Consumes: pageType: "master_detail", entidade mestre e masterDetailConfig.
- Produces: normalizeMasterDetailBuilderConfig(array $value, BuilderEntity $master): array e generateMasterDetailDefinition(array $config): array.

- [ ] **Step 1: Escrever o teste de definição gerada**

~~~php
public function testGenerateMasterDetailDefinitionBuildsGraph(): void
{
    $definition = $this->invokePrivateMixed($this->service(), 'generateMasterDetailDefinition', [[
        'pageType' => 'master_detail', 'programCode' => 'vd0101',
        'programTitle' => 'Pedido de venda', 'screenId' => 'vendas.pedidos',
        'module' => 'vendas', 'permissionPrefix' => 'vendas.pedido',
        'version' => '1.0.0', '_entity' => $this->pedidoEntity(),
        'masterDetailConfig' => $this->validMasterDetailConfig(),
    ]]);

    self::assertSame('master_detail', $definition['pageType']);
    self::assertSame('pedido_venda', $definition['master']['entity']);
    self::assertSame('pedido_id', $definition['details'][0]['parentField']);
    self::assertSame('createGraph', $definition['createFlow']['endpointId']);
    self::assertArrayNotHasKey('url', $definition['createFlow']);
}
~~~

- [ ] **Step 2: Executar o teste e confirmar a falha**

Run: php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testGenerateMasterDetailDefinitionBuildsGraph

Expected: FAIL porque o gerador não existe.

- [ ] **Step 3: Implementar a normalização e geração mínimas**

~~~php
private function generateProgramDefinition(array $config): array
{
    $definition = match ($config['pageType']) {
        'master_detail' => $this->generateMasterDetailDefinition($config),
        'custom' => $this->generateCustomDefinition($config),
        default => $this->generateCrudDefinition($config),
    };
    // manter a decoração comum existente
}

private function generateMasterDetailDefinition(array $config): array
{
    return [
        'schemaVersion' => '1.0', 'pageType' => 'master_detail',
        'screenId' => $config['screenId'],
        'program' => $this->masterDetailProgramPayload($config),
        'permissions' => $this->masterDetailPermissions($config),
        'master' => $this->buildMasterDetailSection($config['_entity']),
        'details' => $this->buildMasterDetailSections($config['masterDetailConfig']['details']),
        'createFlow' => $this->masterDetailCreateFlow($config),
    ];
}
~~~

A normalização aceita somente entidades existentes, detalhes não repetidos, parentField existente na filha, displayFields/totais da própria filha e endpointId=createGraph para draftWithChildren.

- [ ] **Step 4: Executar o teste e confirmar que passa**

Run: php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testGenerateMasterDetailDefinitionBuildsGraph

Expected: PASS.

- [ ] **Step 5: Escrever os quatro testes de rejeição**

~~~php
#[DataProvider('invalidMasterDetailConfigs')]
public function testNormalizeMasterDetailConfigRejectsInvalidReferences(array $config, string $errorCode): void
{
    $this->expectException(RuntimeHttpException::class);
    $this->expectExceptionMessage($errorCode);
    $this->invokePrivateMixed($this->service(), 'normalizeMasterDetailBuilderConfig', [$config, $this->pedidoEntity()]);
}
~~~

A provider cobre filha repetida (PROGRAM_BUILDER_MASTER_DETAIL_DETAIL_DUPLICATE), FK inválida (...PARENT_FIELD_INVALID), campo/totais inválidos (...FIELD_INVALID) e fluxo conjunto sem endpoint (...CREATE_GRAPH_REQUIRED).

- [ ] **Step 6: Implementar os erros fechados e validar**

~~~php
throw new RuntimeHttpException(
    'PROGRAM_BUILDER_MASTER_DETAIL_PARENT_FIELD_INVALID',
    'A entidade filha deve informar um campo de vínculo existente para a entidade mestre.',
    422,
    ['entityCode' => $detailCode, 'parentField' => $parentField]
);
~~~

Run: php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php

Expected: PASS.

- [ ] **Step 7: Commitar**

~~~bash
git add backend/src/Builder/ProgramBuilderService.php backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
git commit -m "Adiciona geracao master detail no builder"
~~~

### Task 2: Persistir e publicar a definição

**Files:**
- Modify: backend/src/Builder/ProgramBuilderService.php: normalizeBuilderPayload(), saveDraft(), publishVersion(), programSchemaFingerprint()
- Modify: backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php

**Interfaces:**
- Consumes: normalizeMasterDetailBuilderConfig().
- Produces: builderConfig.masterDetailConfig e tela publicada com pageType=master_detail.

- [ ] **Step 1: Escrever o teste de rascunho e publicação**

~~~php
public function testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition(): void
{
    $draft = $this->service()->saveDraft($this->validProgramPayload());
    self::assertSame('master_detail', $draft['pageType']);
    self::assertSame('pedido_venda', $draft['builderConfig']['masterDetailConfig']['masterEntityCode']);

    $published = $this->service()->publishVersion((int) $draft['id']);
    self::assertSame('master_detail', $published['generatedDefinition']['pageType']);
    self::assertSame('vendas.pedidos', $published['generatedDefinition']['screenId']);
}
~~~

- [ ] **Step 2: Executar e confirmar a falha**

Run: php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php --filter testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition

Expected: FAIL porque o payload ainda bloqueia ou descarta o novo tipo.

- [ ] **Step 3: Persistir o bloco e rastrear seu fingerprint**

~~~php
'masterDetailConfig' => $pageType === 'master_detail'
    ? $this->normalizeMasterDetailBuilderConfig($payload['masterDetailConfig'] ?? [], $entity)
    : null,

'masterDetail' => $definition['master'] ?? [],
'details' => $definition['details'] ?? [],
'createFlow' => $definition['createFlow'] ?? [],
~~~

Manter builderEntityCode com o código do mestre. Incluir master_detail nos tipos suportados e que exigem entidade; não alterar o despachante já existente de production/app.html.

- [ ] **Step 4: Executar teste de publicação e regressão**

Run: php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php

Expected: PASS.

- [ ] **Step 5: Commitar**

~~~bash
git add backend/src/Builder/ProgramBuilderService.php backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
git commit -m "Publica programas master detail pelo builder"
~~~

### Task 3: Adicionar editor declarativo e preview

**Files:**
- Modify: src/program-builder/program-builder.js: collectProgramPayload(), resetProgramForm(), handleProgramEntityChange(), renderLocalSummary()
- Modify: src/program-builder/program-builder-properties.js: renderProgramProperties()
- Modify: src/styles/program-builder.css
- Modify: tests/frontend/program-builder-technical-smoke.mjs

**Interfaces:**
- Consumes: this.state.entities e masterDetailConfig.
- Produces: collectMasterDetailConfig(): object no payload de preview/salvamento.

- [ ] **Step 1: Escrever o smoke de coleta**

~~~js
const payload = await page.evaluate(() => {
  const builder = window.currentProgramBuilder;
  builder.pageTypeSelect.value("master_detail");
  builder.masterDetailMasterEntitySelect.value("pedido_venda");
  builder.addMasterDetail("pedido_item", "pedido_id");
  return builder.collectProgramPayload();
});
if (payload.pageType !== "master_detail" || payload.masterDetailConfig.details[0].parentField !== "pedido_id") {
  throw new Error("O Builder nao coletou o contrato master_detail.");
}
~~~

- [ ] **Step 2: Executar e confirmar a falha**

Run: node tests/frontend/program-builder-technical-smoke.mjs

Expected: FAIL porque o seletor e os métodos não existem.

- [ ] **Step 3: Implementar controles e coleta sem dados livres**

~~~js
ProgramBuilder.prototype.collectMasterDetailConfig = function() {
  return {
    masterEntityCode: String(this.masterDetailMasterEntitySelect.value() || ""),
    createFlow: {
      mode: String(this.masterDetailCreateFlowSelect.value() || "parentFirst"),
      endpointId: String(this.masterDetailCreateGraphEndpointInput.val() || "")
    },
    details: this.collectMasterDetailRows()
  };
};
~~~

Adicionar master_detail ao seletor. Mestre, filha, FK e campos de exibição são DropDownList/ComboBox derivados de entidades/campos existentes. Toda alteração chama schedulePreview(); não adicionar entrada de URL.

- [ ] **Step 4: Exibir preview e propriedades corretos**

~~~js
if (payload.pageType === "master_detail") {
  preview.master = this.localMasterDetailSection(payload.masterDetailConfig.masterEntityCode);
  preview.details = this.localMasterDetailSections(payload.masterDetailConfig.details);
  preview.createFlow = payload.masterDetailConfig.createFlow;
}
~~~

No painel contextual, mostrar mestre, modo, quantidade de filhos e endpointId, nunca campos do modo custom.

- [ ] **Step 5: Rodar sintaxe, smoke e inspeção gráfica do Builder**

Run: node --check src/program-builder/program-builder.js && node --check src/program-builder/program-builder-properties.js && node tests/frontend/program-builder-technical-smoke.mjs

Expected: PASS.

Abrir file:///C:/construtor-pg/program-builder.html a 1366px e 768px. Confirmar que mestre, filha, FK, fluxo e preview não se sobrepõem e permanecem acessíveis.

- [ ] **Step 6: Commitar**

~~~bash
git add src/program-builder/program-builder.js src/program-builder/program-builder-properties.js src/styles/program-builder.css tests/frontend/program-builder-technical-smoke.mjs
git commit -m "Adiciona editor declarativo de master detail"
~~~

### Task 4: Validar a tela publicada graficamente

**Files:**
- Create: tests/frontend/program-builder-master-detail-smoke.mjs
- Modify: package.json
- Modify: backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php

**Interfaces:**
- Consumes: programa master_detail publicado pelo Task 2 e a rota já despachada por production/app.html.
- Produces: smoke desktop/reduzido da tela gerada pelo Builder.

- [ ] **Step 1: Escrever o smoke desktop**

~~~js
await page.setViewportSize({ width: 1366, height: 900 });
await page.goto(publishedMasterDetailUrl);
await page.waitForSelector(".master-detail-screen");
await page.waitForSelector(".master-detail-parent-panel");
await page.waitForSelector(".master-detail-child-panel");
await page.getByText("Itens", { exact: true }).click();
await page.getByText("Parcelas", { exact: true }).click();
~~~

- [ ] **Step 2: Executar e confirmar a falha**

Run: node tests/frontend/program-builder-master-detail-smoke.mjs

Expected: FAIL porque a fixture publicada ainda não existe.

- [ ] **Step 3: Criar fixture por serviço do Builder**

~~~php
$preview = $this->service()->previewDraft($this->validProgramPayload());
self::assertSame('master_detail', $preview['generatedDefinition']['pageType']);
~~~

A fixture usa entidades pedido_venda, pedido_item e pedido_parcela modeladas pelo Builder. Os registros são obtidos do runtime/mock fechado; não embutir records na página nem criar HTML específico.

- [ ] **Step 4: Implementar asserts reduzidos e inclusão conjunta**

~~~js
await page.setViewportSize({ width: 768, height: 900 });
await page.reload();
await page.waitForSelector(".master-detail-screen");
const boxes = await page.locator(".master-detail-panel").evaluateAll((nodes) =>
  nodes.map((node) => node.getBoundingClientRect())
);
if (boxes.some((box) => box.width <= 0 || box.height <= 0)) {
  throw new Error("Painel mestre-detalhe invisivel em largura reduzida.");
}
await page.locator(".master-detail-actions").getByRole("button", { name: "Incluir" }).first().click();
await page.waitForSelector(".master-detail-create-graph-window");
~~~

Interceptar a chamada e confirmar uma única operação createGraph. Em erro de validação, confirmar mensagem dentro da janela Kendo e ausência de diálogo nativo.

- [ ] **Step 5: Executar interface e regressões**

Run: node tests/frontend/program-builder-master-detail-smoke.mjs && node tests/frontend/master-detail-smoke.mjs && node tests/frontend/master-detail-create-flow-smoke.mjs

Expected: PASS, sem pageerror nem erro de console.

- [ ] **Step 6: Adicionar comando e commitar**

~~~json
"test:program-builder-master-detail": "node tests/frontend/program-builder-master-detail-smoke.mjs"
~~~

~~~bash
git add tests/frontend/program-builder-master-detail-smoke.mjs package.json backend/tests/Builder/ProgramBuilderServiceMasterDetailTest.php
git commit -m "Valida renderizacao master detail gerada pelo builder"
~~~

### Task 5: Fechar a fase e registrar continuidade

**Files:**
- Modify: docs/continuidade-codex.md

**Interfaces:**
- Consumes: resultados dos Tasks 1–4.
- Produces: instrução operacional e evidência verificável.

- [ ] **Step 1: Rodar a suíte diretamente relacionada**

Run: php backend/bin/phpunit tests/Builder/ProgramBuilderServiceMasterDetailTest.php tests/Builder/ProgramBuilderServiceTechnicalPropertiesTest.php && node tests/frontend/program-builder-technical-smoke.mjs && node tests/frontend/program-builder-master-detail-smoke.mjs && node tests/frontend/master-detail-smoke.mjs && node tests/frontend/master-detail-create-flow-smoke.mjs

Expected: PASS em todos os comandos.

- [ ] **Step 2: Atualizar continuidade**

~~~markdown
- O Program Builder gera pageType=master_detail por masterDetailConfig, com mestre, filhos, FK, totais e createGraph fechado; validar com npm run test:program-builder-master-detail.
~~~

- [ ] **Step 3: Executar checagem de segurança e estado**

Run: rg -n 'alert\(|confirm\(|prompt\(|eval\(|Function\(' src/program-builder backend/src/Builder tests/frontend/program-builder-master-detail-smoke.mjs && git status --short

Expected: nenhuma ocorrência nova de APIs nativas ou execução dinâmica; status contém apenas arquivos desta fase antes do commit.

- [ ] **Step 4: Commitar**

~~~bash
git add docs/continuidade-codex.md
git commit -m "Documenta master detail no program builder"
~~~

## Cobertura do spec

- Geração, validação e segurança: Tasks 1 e 2.
- Configuração visual e preview: Task 3.
- Publicação e validação gráfica desktop/reduzida: Task 4.
- Continuidade e verificação: Task 5.
- Produtos, pessoas, pedido de venda e adaptador mock terão plano posterior, após esta fase estar aprovada.

