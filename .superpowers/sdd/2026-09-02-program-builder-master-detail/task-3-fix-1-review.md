# Scoped re-review
Base: f24791b8
Head: a9369984
Open: inspector refresh; visual 1366/768; closed endpoint state; smoke coverage.

diff --git a/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md b/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md
index 6590dc99..36edab43 100644
--- a/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md
+++ b/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md
@@ -29,32 +29,68 @@ node --check src/program-builder/program-builder-properties.js
 node tests/frontend/program-builder-technical-smoke.mjs
 git diff --check
 ```
 
 Todos retornaram código 0. O smoke confirmou `pageType=master_detail`, mestre `pedido_venda`, filho `pedido_item`, `parentField=pedido_id`, preview local com `master/details/createFlow`, painel contextual presente e ausência do rótulo de modo custom.
 
 ## Validação visual
 
-O smoke abre a página local do worktree por `file://` e valida a montagem do painel. O CSS usa duas colunas no desktop e uma coluna abaixo de 980px, preparada para 1366px e 768px.
+O smoke Playwright abre a página local do worktree por `file://` e valida as duas larguras solicitadas.
 
-A inspeção visual manual nas duas larguras não foi concluída: a aba interna bloqueou a URL `file://` do worktree por política de segurança e não foi usada outra superfície para contornar isso. A validação em `file:///C:/construtor-pg/program-builder.html` deve ser repetida depois de integrar o commit ao checkout principal.
+- Em 1366px, o contexto mestre-detalhe ficou em 392px, todos os cinco controles tiveram largura positiva e não houve sobreposição. O preview ficou visível.
+- Em 768px, o contexto foi reduzido para 300px e os controles passaram a uma coluna, com largura positiva e sem sobreposição. O preview ficou visível.
+
+Não foi necessário gerar screenshot: o teste confirma por `getBoundingClientRect()` as dimensões, a presença no viewport e a ausência de interseções entre os controles.
 
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
+- A troca de entidade mestre chama a atualização imediata do inspetor e do preview, além da sincronização do estado mestre-detalhe.
+- `endpointId` é normalizado no carregamento e em chamadas programáticas: somente `masterDetail.createGraph` é aceito em `draftWithChildren`; no modo `parentFirst` ele fica vazio.
 - Não foram alterados `kendo/`, `production/app.html`, exemplos ou mocks; a paridade demo/produção não se aplica.
 
 ## Preocupações
 
 - O endpoint seguro disponível na interface é o identificador fechado `masterDetail.createGraph`. Outros endpointIds aprovados pelo backend exigem ampliar uma fonte segura do catálogo, nunca entrada livre.
-- A confirmação visual na página principal fica pendente da integração do commit, porque o navegador interno não libera `file://` do worktree nesta sessão.
+- A validação visual automática foi executada por Playwright porque a aba interna bloqueia `file://` do worktree. Não há pendência de validação para as larguras 1366px e 768px.
+
+## Correções da revisão
+
+### RED
+
+O smoke ampliado falhou antes da correção de P1 ao trocar o mestre, pois o método público `handleProgramEntityChange` apenas sincronizava o estado e aguardava a atualização assíncrona, sem redesenhar imediatamente o inspetor:
+
+```text
+Error: A troca de mestre nao atualizou imediatamente o inspetor e as opcoes de filhos.
+```
+
+O teste visual também identificou que o contexto mestre-detalhe excedia a largura de 1366px antes de receber largura responsiva:
+
+```text
+Error: Controles mestre-detalhe sem acesso ou sobrepostos em 1366px.
+```
+
+### GREEN
+
+Foram incluídos `renderPropertyInspector()` e `schedulePreview()` na troca do mestre. O estado de fluxo de criação passa por normalização fechada tanto em carregamentos quanto em chamadas programáticas. O CSS limita o contexto a 392px no desktop e 300px abaixo de 980px.
+
+O comando abaixo retornou código 0 após as correções:
+
+```text
+node --check src/program-builder/program-builder.js
+node --check src/program-builder/program-builder-properties.js
+node tests/frontend/program-builder-technical-smoke.mjs
+git diff --check
+```
+
+O smoke cobre a troca de mestre pelo painel de propriedades, atualização imediata do resumo/opções/preview, normalização de `endpointId` arbitrário em carga e chamada programática, além das verificações Playwright de 1366px e 768px.
diff --git a/src/program-builder/program-builder.js b/src/program-builder/program-builder.js
index 42516365..af586536 100644
--- a/src/program-builder/program-builder.js
+++ b/src/program-builder/program-builder.js
@@ -3863,25 +3863,31 @@
     }
     if (this.state.currentEntityCode === code) {
       return this.collectEntityPayload().fields || [];
     }
     const entity = this.state.entityDetailCache && this.state.entityDetailCache[code];
     return entity && Array.isArray(entity.fields) ? entity.fields : [];
   };
 
+  ProgramBuilder.prototype.normalizeMasterDetailCreateFlow = function(mode, endpointId) {
+    const createFlowMode = mode === "draftWithChildren" ? "draftWithChildren" : "parentFirst";
+    const safeEndpointId = String(endpointId || "").trim() === "masterDetail.createGraph" ? "masterDetail.createGraph" : "";
+    return {
+      mode: createFlowMode,
+      endpointId: createFlowMode === "draftWithChildren" ? (safeEndpointId || "masterDetail.createGraph") : ""
+    };
+  };
+
   ProgramBuilder.prototype.masterDetailConfigValue = function() {
     const current = this.state.masterDetailConfig || {};
     const masterEntityCode = String(current.masterEntityCode || this.builderEntitySelect && this.builderEntitySelect.value && this.builderEntitySelect.value() || "").trim();
     return {
       masterEntityCode: masterEntityCode,
-      createFlow: {
-        mode: current.createFlow && current.createFlow.mode === "draftWithChildren" ? "draftWithChildren" : "parentFirst",
-        endpointId: String(current.createFlow && current.createFlow.endpointId || "").trim()
-      },
+      createFlow: this.normalizeMasterDetailCreateFlow(current.createFlow && current.createFlow.mode, current.createFlow && current.createFlow.endpointId),
       details: Array.isArray(current.details) ? current.details.map(function(detail) {
         return {
           entityCode: String(detail && detail.entityCode || "").trim(),
           title: String(detail && detail.title || "").trim(),
           singularTitle: String(detail && detail.singularTitle || "").trim(),
           parentField: String(detail && detail.parentField || "").trim(),
           displayFields: Array.isArray(detail && detail.displayFields) ? detail.displayFields.slice() : [],
           totals: Array.isArray(detail && detail.totals) ? detail.totals.map(function(total) {
@@ -3895,16 +3901,17 @@
       }).filter(function(detail) {
         return detail.entityCode && detail.parentField;
       }) : []
     };
   };
 
   ProgramBuilder.prototype.setMasterDetailConfig = function(config) {
     this.state.masterDetailConfig = config || null;
+    this.state.masterDetailConfig = this.masterDetailConfigValue();
   };
 
   ProgramBuilder.prototype.syncMasterDetailMasterEntity = function() {
     const config = this.masterDetailConfigValue();
     const masterEntityCode = String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "").trim();
     config.masterEntityCode = masterEntityCode;
     config.details = config.details.filter(function(detail) {
       return detail.entityCode !== masterEntityCode;
@@ -3956,20 +3963,17 @@
       return detail.entityCode !== String(entityCode || "").trim();
     });
     this.setMasterDetailConfig(config);
     this.schedulePreview();
   };
 
   ProgramBuilder.prototype.setMasterDetailCreateFlow = function(mode, endpointId) {
     const config = this.masterDetailConfigValue();
-    config.createFlow = {
-      mode: mode === "draftWithChildren" ? "draftWithChildren" : "parentFirst",
-      endpointId: String(endpointId || "").trim()
-    };
+    config.createFlow = this.normalizeMasterDetailCreateFlow(mode, endpointId);
     this.setMasterDetailConfig(config);
     this.schedulePreview();
   };
 
   ProgramBuilder.prototype.refreshAnalyticsConfigOptions = function() {
     if (!this.analyticsProgramPanel) {
       return;
     }
@@ -10455,16 +10459,18 @@
     if (!entityCode) {
       this.schedulePreview();
       return;
     }
 
     const entity = this.findEntitySummary(entityCode);
     if (pageType === "master_detail") {
       this.syncMasterDetailMasterEntity();
+      this.renderPropertyInspector();
+      this.schedulePreview();
       if (entity && entity.entityType !== "persistence") {
         this.previewFooter.text("Mestre-detalhe aceita somente entidade mestre persistence.");
       }
     } else if (pageType === "analytics" && entity && entity.entityType !== "persistence") {
       this.previewFooter.text("Analytics v1 aceita somente entidades persistence como fonte interna.");
     } else if (entity && entity.entityType === "api") {
       if (this.state.currentEntityCode === entityCode) {
         this.syncProgramWriteFlagsForApi();
diff --git a/src/styles/program-builder.css b/src/styles/program-builder.css
index 491d2428..884845af 100644
--- a/src/styles/program-builder.css
+++ b/src/styles/program-builder.css
@@ -748,16 +748,20 @@
 .program-builder-inline-hint {
   color: #667085;
   font-size: 13px;
 }
 
 .program-builder-master-detail-context {
   display: grid;
   gap: 12px;
+  width: 392px;
+  min-width: 0;
+  max-width: 100%;
+  box-sizing: border-box;
   padding: 12px;
   border: 1px solid #b8d6f5;
   border-radius: 8px;
   background: #f5f9ff;
 }
 
 .program-builder-master-detail-context h3 {
   margin: 0;
@@ -765,16 +769,24 @@
   font-size: 15px;
 }
 
 .program-builder-master-detail-summary,
 .program-builder-master-detail-editor {
   display: grid;
   grid-template-columns: repeat(2, minmax(0, 1fr));
   gap: 10px;
+  min-width: 0;
+}
+
+.program-builder-master-detail-editor > .program-builder-field,
+.program-builder-master-detail-editor .k-input,
+.program-builder-master-detail-editor .k-picker {
+  min-width: 0;
+  max-width: 100%;
 }
 
 .program-builder-master-detail-summary-item,
 .program-builder-master-detail-row {
   display: grid;
   gap: 3px;
   min-width: 0;
   padding: 8px 10px;
@@ -1416,9 +1428,13 @@
     grid-template-columns: 1fr;
   }
 
   .program-builder-master-detail-summary,
   .program-builder-master-detail-editor,
   .program-builder-master-detail-row {
     grid-template-columns: 1fr;
   }
+
+  .program-builder-master-detail-context {
+    width: 300px;
+  }
 }
diff --git a/tests/frontend/program-builder-technical-smoke.mjs b/tests/frontend/program-builder-technical-smoke.mjs
index 32ba648b..85207624 100644
--- a/tests/frontend/program-builder-technical-smoke.mjs
+++ b/tests/frontend/program-builder-technical-smoke.mjs
@@ -36,29 +36,64 @@ async function closeTechnicalDialog(page) {
       const widget = window.jQuery(this).data("kendoWindow");
       if (widget) {
         widget.close();
       }
     });
   });
 }
 
+async function verifyMasterDetailLayout(page, width) {
+  await page.setViewportSize({ width, height: 900 });
+  const controls = await page.evaluate(() => {
+    const app = window.programBuilderDemoApp;
+    app.activateSideTab(1);
+    const context = app.propertiesElement.find(".program-builder-master-detail-context").get(0);
+    const rect = context.getBoundingClientRect();
+    const controlRects = Array.from(context.querySelectorAll(".program-builder-master-detail-editor > .program-builder-field, .program-builder-master-detail-editor > button")).map((element) => {
+      const controlRect = element.getBoundingClientRect();
+      return { left: controlRect.left, top: controlRect.top, right: controlRect.right, bottom: controlRect.bottom, width: controlRect.width, height: controlRect.height };
+    });
+    const overlaps = controlRects.some((current, index) => controlRects.slice(index + 1).some((next) => current.left < next.right && current.right > next.left && current.top < next.bottom && current.bottom > next.top));
+    return {
+      context: { left: rect.left, right: rect.right, width: rect.width, height: rect.height },
+      controls: controlRects,
+      overlaps,
+      viewportWidth: window.innerWidth
+    };
+  });
+  const preview = await page.evaluate(() => {
+    const app = window.programBuilderDemoApp;
+    app.activateSideTab(0);
+    const rect = app.previewPanel.get(0).getBoundingClientRect();
+    return { left: rect.left, right: rect.right, width: rect.width, height: rect.height };
+  });
+  if (controls.context.width <= 0 || controls.context.height <= 0 || controls.context.left < 0 || controls.context.right > controls.viewportWidth || controls.controls.some((item) => item.width <= 0 || item.height <= 0) || controls.overlaps) {
+    throw new Error(`Controles mestre-detalhe sem acesso ou sobrepostos em ${width}px.`);
+  }
+  if (preview.width <= 0 || preview.height <= 0 || preview.left >= controls.viewportWidth || preview.right <= 0) {
+    throw new Error(`Preview nao acessivel em ${width}px.`);
+  }
+  return { width, controls, preview };
+}
+
 async function main() {
   await ensureOutputDir();
   const browser = await chromium.launch({ headless: true });
   const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
 
   try {
     await page.goto(baseUrl + "/examples/pages/program-builder-technical-properties.html");
     await page.waitForFunction(() => Boolean(window.programBuilderDemoApp), null, { timeout: 30000 });
     await page.waitForFunction(() => document.querySelectorAll('[data-crud-role="program-builder-technical-info"]').length > 0, null, { timeout: 30000 });
 
     const masterDetailPayload = await page.evaluate(() => {
       const app = window.programBuilderDemoApp;
       app.state.entities = [
+        { code: "cliente", name: "Cliente", entityType: "persistence" },
         { code: "pedido_venda", name: "Pedido de venda", entityType: "persistence" },
         { code: "pedido_item", name: "Item do pedido", entityType: "persistence" }
       ];
       app.state.entityDetailCache = {
         pedido_venda: {
           code: "pedido_venda",
           fields: [
             { code: "id", label: "ID", dataType: "integer", primaryKey: true },
@@ -76,43 +111,75 @@ async function main() {
         }
       };
       app.applyBootstrapData();
       app.pageTypeSelect.value("master_detail");
       app.builderEntitySelect.value("pedido_venda");
       app.addMasterDetail("pedido_item", "pedido_id");
       app.syncProgramTypeState();
       app.selectPropertyNode("program", { code: "" });
+      app.builderEntitySelect.value("cliente");
+      app.handleProgramEntityChange(false);
+      const propertyBaseEntity = app.propertiesElement.find(".program-builder-field").filter(function() {
+        return window.jQuery(this).find(".program-builder-field-label-row span").first().text() === "Entidade base";
+      }).find("select");
+      propertyBaseEntity.val("cliente").triggerHandler("change");
+      const context = app.propertiesElement.find(".program-builder-master-detail-context");
+      const changedMaster = {
+        masterEntityCode: app.masterDetailConfigValue().masterEntityCode,
+        contextualText: context.find(".program-builder-master-detail-summary").text(),
+        detailOptions: context.find("[data-role='combobox']").data("kendoComboBox").dataSource.data().map(function(item) { return item.value; })
+      };
+      app.setMasterDetailConfig({
+        masterEntityCode: "cliente",
+        createFlow: { mode: "draftWithChildren", endpointId: "carregamento.nao.permitido" },
+        details: app.masterDetailConfigValue().details
+      });
+      const loadedEndpoint = app.masterDetailConfigValue().createFlow.endpointId;
+      app.setMasterDetailCreateFlow("draftWithChildren", "nao.permitido");
+      const normalizedEndpoint = app.masterDetailConfigValue().createFlow.endpointId;
       const payload = app.collectProgramPayload();
       app.renderLocalSummary(payload);
       return {
         payload,
         preview: app.state.preview && app.state.preview.generatedDefinition,
         contextualPanel: app.propertiesElement.find(".program-builder-master-detail-context").length,
-        hasCustomModeLabel: app.propertiesElement.text().indexOf("Modo custom") >= 0
+        hasCustomModeLabel: app.propertiesElement.text().indexOf("Modo custom") >= 0,
+        switchedViaPropertyPanel: propertyBaseEntity.length === 1,
+        changedMaster,
+        loadedEndpoint,
+        normalizedEndpoint
       };
     });
     if (masterDetailPayload.payload.pageType !== "master_detail") {
       throw new Error("O editor nao coletou pageType=master_detail.");
     }
-    if (masterDetailPayload.payload.builderEntityCode !== "pedido_venda") {
-      throw new Error("O editor nao manteve pedido_venda como entidade mestre.");
+    if (masterDetailPayload.payload.builderEntityCode !== "cliente") {
+      throw new Error("O editor nao atualizou a entidade mestre pelo painel de propriedades.");
     }
-    if (!masterDetailPayload.payload.masterDetailConfig || masterDetailPayload.payload.masterDetailConfig.masterEntityCode !== "pedido_venda") {
-      throw new Error("O payload nao informou a configuracao declarativa do mestre.");
+    if (!masterDetailPayload.payload.masterDetailConfig || masterDetailPayload.payload.masterDetailConfig.masterEntityCode !== "cliente") {
+      throw new Error("O payload nao atualizou a configuracao declarativa do mestre.");
     }
     if (masterDetailPayload.payload.masterDetailConfig.details.length !== 1 || masterDetailPayload.payload.masterDetailConfig.details[0].parentField !== "pedido_id") {
       throw new Error("O payload nao preservou parentField para pedido_item.");
     }
-    if (!masterDetailPayload.preview || masterDetailPayload.preview.master.entityCode !== "pedido_venda" || masterDetailPayload.preview.details[0].parentField !== "pedido_id") {
+    if (!masterDetailPayload.preview || masterDetailPayload.preview.master.entityCode !== "cliente" || masterDetailPayload.preview.details[0].parentField !== "pedido_id") {
       throw new Error("O preview local nao exibiu mestre e filhos declarativos.");
     }
-    if (masterDetailPayload.preview.createFlow.mode !== "parentFirst" || masterDetailPayload.contextualPanel !== 1 || masterDetailPayload.hasCustomModeLabel) {
+    if (masterDetailPayload.preview.createFlow.mode !== "draftWithChildren" || masterDetailPayload.contextualPanel !== 1 || masterDetailPayload.hasCustomModeLabel) {
       throw new Error("O painel contextual do mestre-detalhe esta incompleto ou exibiu controles custom.");
     }
+    if (!masterDetailPayload.switchedViaPropertyPanel || masterDetailPayload.changedMaster.masterEntityCode !== "cliente" || masterDetailPayload.changedMaster.contextualText.indexOf("cliente") < 0 || masterDetailPayload.changedMaster.detailOptions.includes("cliente")) {
+      throw new Error("A troca de mestre nao atualizou imediatamente o inspetor e as opcoes de filhos.");
+    }
+    if (masterDetailPayload.loadedEndpoint !== "masterDetail.createGraph" || masterDetailPayload.normalizedEndpoint !== "masterDetail.createGraph" || masterDetailPayload.payload.masterDetailConfig.createFlow.endpointId !== "masterDetail.createGraph") {
+      throw new Error("O endpointId fora da lista fechada permaneceu no estado ou payload.");
+    }
+    const layout1366 = await verifyMasterDetailLayout(page, 1366);
+    const layout768 = await verifyMasterDetailLayout(page, 768);
 
     const entityTriggers = await page.evaluate(() => window.programBuilderDemoApp.entityPanel.find('[data-crud-role="program-builder-technical-info"]').length);
     await clickFirstFromContainer(page, "entityPanel");
     await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
     await page.locator(".crud-technical-download-json-button").click();
     await page.screenshot({ path: path.join(outputDir, "program-builder-technical-entity.png"), fullPage: true });
     await closeTechnicalDialog(page);
 
@@ -145,17 +212,19 @@ async function main() {
     await page.screenshot({ path: path.join(outputDir, "program-builder-technical-inspector.png"), fullPage: true });
     await closeTechnicalDialog(page);
 
     const result = {
       entityTriggers,
       programTriggers,
       apiTriggers,
       inspectorTriggers,
-      masterDetailPayload
+      masterDetailPayload,
+      layout1366,
+      layout768
     };
 
     await fs.writeFile(
       path.join(outputDir, "program-builder-technical-smoke-result.json"),
       JSON.stringify(result, null, 2),
       "utf8"
     );
 

