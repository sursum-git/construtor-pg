# Review package

Base: 5798ccae
Head: f24791b8

f24791b8 Adiciona editor declarativo de master detail
 .../task-3-report.md                               |  60 +++++++
 src/program-builder/program-builder-properties.js  | 116 +++++++++++++-
 src/program-builder/program-builder.js             | 172 +++++++++++++++++++--
 src/styles/program-builder.css                     |  61 ++++++++
 tests/frontend/program-builder-technical-smoke.mjs |  66 +++++++-
 5 files changed, 457 insertions(+), 18 deletions(-)
diff --git a/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md b/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md
new file mode 100644
index 00000000..6590dc99
--- /dev/null
+++ b/.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md
@@ -0,0 +1,60 @@
+# Task 3 — Editor declarativo e preview mestre-detalhe
+
+## Resultado
+
+- O Program Builder agora lista `master_detail` como tipo de pagina e mantém a entidade base como mestre.
+- O painel de propriedades apresenta editor declarativo com Kendo ComboBox/DropDownList para entidade filha, campo de vínculo, fluxo de criação e endpoint seguro `masterDetail.createGraph`; não há URL livre.
+- `collectProgramPayload()` envia `masterDetailConfig` com `masterEntityCode`, `createFlow` e `details`. O preview local passa a conter `master`, `details` e `createFlow`.
+- O painel contextual mostra mestre, modo, quantidade de filhos e endpoint ID. Os controles de modo custom não são renderizados para mestre-detalhe.
+
+## RED/GREEN
+
+### RED
+
+Foi acrescentado ao smoke o cenário que seleciona `master_detail`, escolhe `pedido_venda`, chama `addMasterDetail("pedido_item", "pedido_id")` e coleta o payload. Antes da implementação, a execução falhou como esperado:
+
+```text
+TypeError: app.addMasterDetail is not a function
+```
+
+Essa falha prova que o smoke cobre a API declarativa ausente no editor.
+
+### GREEN
+
+Após a implementação:
+
+```text
+node --check src/program-builder/program-builder.js
+node --check src/program-builder/program-builder-properties.js
+node tests/frontend/program-builder-technical-smoke.mjs
+git diff --check
+```
+
+Todos retornaram código 0. O smoke confirmou `pageType=master_detail`, mestre `pedido_venda`, filho `pedido_item`, `parentField=pedido_id`, preview local com `master/details/createFlow`, painel contextual presente e ausência do rótulo de modo custom.
+
+## Validação visual
+
+O smoke abre a página local do worktree por `file://` e valida a montagem do painel. O CSS usa duas colunas no desktop e uma coluna abaixo de 980px, preparada para 1366px e 768px.
+
+A inspeção visual manual nas duas larguras não foi concluída: a aba interna bloqueou a URL `file://` do worktree por política de segurança e não foi usada outra superfície para contornar isso. A validação em `file:///C:/construtor-pg/program-builder.html` deve ser repetida depois de integrar o commit ao checkout principal.
+
+## Arquivos
+
+- `src/program-builder/program-builder.js`
+- `src/program-builder/program-builder-properties.js`
+- `src/styles/program-builder.css`
+- `tests/frontend/program-builder-technical-smoke.mjs`
+- `.superpowers/sdd/2026-09-02-program-builder-master-detail/task-3-report.md`
+
+## Self-review
+
+- A lista de entidades filhas usa somente `this.state.entities` e aceita apenas `persistence`.
+- O vínculo é aceito somente quando é campo conhecido da entidade filha; o teste cobre `pedido_id`.
+- O endpoint é DropDownList fechado e não URL ou texto livre.
+- Cada alteração persistente agenda novo preview.
+- Não foram alterados `kendo/`, `production/app.html`, exemplos ou mocks; a paridade demo/produção não se aplica.
+
+## Preocupações
+
+- O endpoint seguro disponível na interface é o identificador fechado `masterDetail.createGraph`. Outros endpointIds aprovados pelo backend exigem ampliar uma fonte segura do catálogo, nunca entrada livre.
+- A confirmação visual na página principal fica pendente da integração do commit, porque o navegador interno não libera `file://` do worktree nesta sessão.
diff --git a/src/program-builder/program-builder-properties.js b/src/program-builder/program-builder-properties.js
index c85df06d..771bdd7c 100644
--- a/src/program-builder/program-builder-properties.js
+++ b/src/program-builder/program-builder-properties.js
@@ -30,46 +30,158 @@
   };
 
   ProgramBuilder.prototype.renderProgramProperties = function() {
     const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
     this.appendPropertyText(panel, "Codigo", () => this.programCodeInput.value(), (value) => this.programCodeInput.value(value), "text", this.programFieldTechnicalProperties("programCode"));
     this.appendPropertyText(panel, "Titulo", () => this.programTitleInput.value(), (value) => this.programTitleInput.value(value), "text", this.programFieldTechnicalProperties("programTitle"));
     this.appendPropertyText(panel, "Screen ID", () => this.screenIdInput.value(), (value) => this.screenIdInput.value(value), "text", this.programFieldTechnicalProperties("screenId"));
     this.appendPropertyText(panel, "Versao", () => this.versionInput.value(), (value) => this.versionInput.value(value), "text", this.programFieldTechnicalProperties("version"));
     this.appendPropertySelect(panel, "Tipo", [
       { value: "crud", text: "CRUD" },
+      { value: "master_detail", text: "Mestre-detalhe" },
       { value: "analytics", text: "Analytics / BI" },
       { value: "report", text: "Relatorios" },
       { value: "special_document", text: "Documento especial" },
       { value: "regulated_document", text: "Documento regulado" },
       { value: "custom", text: "Custom" }
-    ], () => this.pageTypeSelect.value(), (value) => { this.pageTypeSelect.value(value); this.syncProgramTypeState(); }, this.programFieldTechnicalProperties("pageType"));
+    ], () => this.pageTypeSelect.value(), (value) => { this.pageTypeSelect.value(value); this.syncProgramTypeState(); this.renderPropertyInspector(); }, this.programFieldTechnicalProperties("pageType"));
     this.appendPropertySelect(panel, "Modulo", this.state.modules.map(function(item) {
       return { value: item.code, text: item.code + " - " + item.name };
     }), () => this.moduleInput.value(), (value) => this.moduleInput.value(value), this.programFieldTechnicalProperties("programModule"));
-    if (String(this.pageTypeSelect.value() || "crud") === "crud") {
+    const pageType = String(this.pageTypeSelect.value() || "crud");
+    if (pageType === "crud" || pageType === "master_detail") {
       this.appendPropertySelect(panel, "Entidade base", this.state.entities.map(function(item) {
         return { value: item.code, text: item.code + " - " + item.name };
       }), () => this.builderEntitySelect.value(), (value) => { this.builderEntitySelect.value(value); this.handleProgramEntityChange(false); }, this.programFieldTechnicalProperties("baseEntity"));
+      if (pageType === "master_detail") {
+        this.renderMasterDetailProperties(panel);
+        return;
+      }
       this.appendPropertyCheckbox(panel, "Permite incluir", () => this.allowCreateInput.is(":checked"), (checked) => this.allowCreateInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Programa", "Permite incluir", "Habilita a acao create no CRUD quando o runtime possui endpoint compativel."));
       this.appendPropertyCheckbox(panel, "Permite alterar", () => this.allowUpdateInput.is(":checked"), (checked) => this.allowUpdateInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Programa", "Permite alterar", "Habilita a acao update no CRUD quando o runtime possui endpoint compativel."));
       this.appendPropertyCheckbox(panel, "Permite excluir", () => this.allowDeleteInput.is(":checked"), (checked) => this.allowDeleteInput.prop("checked", checked).trigger("change"), this.buildTechnicalProperties("Programa", "Permite excluir", "Habilita a acao delete no CRUD quando o runtime possui endpoint compativel."));
       return;
     }
     this.appendPropertySelect(panel, "Modo custom", [
       { value: "iframe", text: "Iframe interno" },
       { value: "htmlUrl", text: "Fragmento HTML por URL" }
     ], () => this.customModeSelect.value(), (value) => { this.customModeSelect.value(value); this.schedulePreview(); }, this.programFieldTechnicalProperties("customMode"));
     this.appendPropertyText(panel, "Entry URL", () => this.customEntryUrlInput.value(), (value) => this.customEntryUrlInput.value(value), "text", this.programFieldTechnicalProperties("customEntryUrl"));
     this.appendPropertyText(panel, "Titulo do frame", () => this.customFrameTitleInput.value(), (value) => this.customFrameTitleInput.value(value), "text", this.programFieldTechnicalProperties("customFrameTitle"));
   };
 
+  ProgramBuilder.prototype.renderMasterDetailProperties = function(panel) {
+    const config = this.masterDetailConfigValue();
+    const masterCode = String(config.masterEntityCode || "");
+    const context = $("<section class=\"program-builder-master-detail-context\"></section>").appendTo(panel);
+    $("<h3></h3>").text("Configuracao mestre-detalhe").appendTo(context);
+    const summary = $("<div class=\"program-builder-master-detail-summary\"></div>").appendTo(context);
+    [
+      { label: "Mestre", value: masterCode || "Selecione a entidade mestre" },
+      { label: "Modo", value: config.createFlow.mode === "draftWithChildren" ? "Rascunho com filhos" : "Salvar mestre primeiro" },
+      { label: "Filhos", value: String(config.details.length) },
+      { label: "Endpoint ID", value: config.createFlow.endpointId || "Nao informado" }
+    ].forEach(function(item) {
+      const itemElement = $("<div class=\"program-builder-master-detail-summary-item\"></div>").appendTo(summary);
+      $("<span></span>").text(item.label).appendTo(itemElement);
+      $("<strong></strong>").text(item.value).appendTo(itemElement);
+    });
+
+    const editor = $("<div class=\"program-builder-master-detail-editor\"></div>").appendTo(context);
+    const thisProgram = this;
+    let parentSelect;
+    const detailField = this.appendPropertyField(editor, "Entidade filha", this.buildTechnicalProperties("Programa", "Entidade filha", "Entidade persistence que sera exibida como detalhe do mestre."));
+    const detailOptions = this.state.entities.filter(function(entity) {
+      return entity && entity.entityType === "persistence" && entity.code !== masterCode;
+    }).map(function(entity) {
+      return { value: entity.code, text: entity.code + " - " + entity.name };
+    });
+    const detailInput = $("<input>").appendTo(detailField).kendoComboBox({
+      dataSource: detailOptions,
+      dataTextField: "text",
+      dataValueField: "value",
+      optionLabel: "Selecione a entidade filha",
+      change: function() {
+        const code = String(this.value() || "");
+        parentSelect.setDataSource(new kendo.data.DataSource({ data: [] }));
+        parentSelect.value("");
+        if (!code) {
+          thisProgram.schedulePreview();
+          return;
+        }
+        thisProgram.ensureEntityDetail(code).then(function() {
+          const fields = thisProgram.masterDetailEntityFields(code).map(function(field) {
+            return { value: field.code, text: field.code + " - " + (field.label || field.code) };
+          });
+          parentSelect.setDataSource(new kendo.data.DataSource({ data: fields }));
+          thisProgram.schedulePreview();
+        });
+      }
+    }).data("kendoComboBox");
+    const parentField = this.appendPropertyField(editor, "Campo de vinculo", this.buildTechnicalProperties("Programa", "Campo de vinculo", "Campo existente na entidade filha que referencia o mestre."));
+    parentSelect = $("<input>").appendTo(parentField).kendoDropDownList({
+      dataTextField: "text",
+      dataValueField: "value",
+      optionLabel: "Selecione o campo de vinculo",
+      dataSource: []
+    }).data("kendoDropDownList");
+    const addButton = $("<button type=\"button\" class=\"k-button k-button-md k-rounded-md k-button-solid k-button-solid-primary\"></button>").text("Adicionar filho").appendTo(editor);
+    addButton.on("click", function() {
+      if (thisProgram.addMasterDetail(detailInput.value(), parentSelect.value())) {
+        thisProgram.renderPropertyInspector();
+      }
+    });
+
+    const flowField = this.appendPropertyField(editor, "Fluxo de criacao", this.buildTechnicalProperties("Programa", "Fluxo de criacao", "Define se os filhos exigem o mestre salvo ou podem ser enviados no mesmo comando declarativo."));
+    const endpointField = this.appendPropertyField(editor, "Endpoint ID", this.buildTechnicalProperties("Programa", "Endpoint ID", "Identificador seguro do endpoint transacional para inclusao conjunta, sem URL livre."));
+    const endpointSelect = $("<input>").appendTo(endpointField).kendoDropDownList({
+      dataSource: [{ value: "", text: "Nao se aplica" }, { value: "masterDetail.createGraph", text: "masterDetail.createGraph" }],
+      dataTextField: "text",
+      dataValueField: "value",
+      value: config.createFlow.endpointId,
+      change: function() {
+        thisProgram.setMasterDetailCreateFlow(flowSelect.value(), this.value());
+        thisProgram.renderPropertyInspector();
+      }
+    }).data("kendoDropDownList");
+    const flowSelect = $("<input>").appendTo(flowField).kendoDropDownList({
+      dataSource: [{ value: "parentFirst", text: "Salvar mestre primeiro" }, { value: "draftWithChildren", text: "Rascunho com filhos" }],
+      dataTextField: "text",
+      dataValueField: "value",
+      value: config.createFlow.mode,
+      change: function() {
+        const joint = this.value() === "draftWithChildren";
+        const endpointId = joint ? (endpointSelect.value() || "masterDetail.createGraph") : "";
+        endpointSelect.enable(joint);
+        endpointSelect.value(endpointId);
+        thisProgram.setMasterDetailCreateFlow(this.value(), endpointId);
+        thisProgram.renderPropertyInspector();
+      }
+    }).data("kendoDropDownList");
+    endpointSelect.enable(config.createFlow.mode === "draftWithChildren");
+
+    const detailList = $("<div class=\"program-builder-master-detail-list\"></div>").appendTo(context);
+    if (!config.details.length) {
+      $("<p class=\"program-builder-inline-muted\"></p>").text("Adicione ao menos uma entidade filha e o campo que a vincula ao mestre.").appendTo(detailList);
+      return;
+    }
+    config.details.forEach(function(detail) {
+      const row = $("<div class=\"program-builder-master-detail-row\"></div>").appendTo(detailList);
+      $("<strong></strong>").text(detail.title || detail.entityCode).appendTo(row);
+      $("<span></span>").text(detail.entityCode + " • " + detail.parentField).appendTo(row);
+      $("<button type=\"button\" class=\"k-button k-button-md k-rounded-md\"></button>").text("Remover").appendTo(row).on("click", function() {
+        thisProgram.removeMasterDetail(detail.entityCode);
+        thisProgram.renderPropertyInspector();
+      });
+    });
+  };
+
   ProgramBuilder.prototype.renderFieldProperties = function(index) {
     const pair = this.getFieldRowPair(index);
     if (!pair.row) {
       $("<p class=\"program-builder-empty\"></p>").text("Selecione um campo.").appendTo(this.propertiesElement);
       return;
     }
     const panel = $("<div class=\"program-builder-properties-grid\"></div>").appendTo(this.propertiesElement);
     this.appendPropertyText(panel, "Codigo", () => pair.row.find(".program-builder-field-code").val(), (value) => pair.row.find(".program-builder-field-code").val(value), "text", this.buildTechnicalProperties("Campo", "Codigo", "Identificador tecnico do campo dentro da entidade.", [{ section: "Modelo", label: "Uso", value: "Referencia em regras, FKs, grid e runtime.", critical: true }]));
     this.appendPropertyText(panel, "Label", () => pair.row.find(".program-builder-field-label").val(), (value) => pair.row.find(".program-builder-field-label").val(value), "text", this.buildTechnicalProperties("Campo", "Label", "Rotulo funcional exibido no grid, filtro e formulario."));
     this.appendPropertySelect(panel, "Tipo", [
diff --git a/src/program-builder/program-builder.js b/src/program-builder/program-builder.js
index fb074ecf..42516365 100644
--- a/src/program-builder/program-builder.js
+++ b/src/program-builder/program-builder.js
@@ -17,20 +17,21 @@
       currentModuleId: null,
       programs: [],
       entityVersions: [],
       currentEntityVersion: null,
       historySourceEntity: null,
       versions: [],
       currentVersion: null,
       currentProgramCode: "",
       currentEntityCode: "",
       entityDetailCache: {},
+      masterDetailConfig: null,
       originalEntityTableName: "",
       preview: null,
       databaseTables: [],
       importInspection: null,
       ddlImportInspection: null,
       externalImportInspection: null,
       aiSettings: null,
       aiSessionId: "",
       aiSessionMeta: null,
       aiDraftInspection: null,
@@ -3218,20 +3219,21 @@
     const splitD = $("<div class=\"program-builder-split\"></div>").appendTo(form);
     this.subtitleInput = this.createTextField(splitD, "Subtitulo", this.programFieldTechnicalProperties("subtitle"));
     this.iconInput = this.createTextField(splitD, "Icone", this.programFieldTechnicalProperties("icon"));
 
     const splitE = $("<div class=\"program-builder-split\"></div>").appendTo(form);
     this.permissionPrefixInput = this.createTextField(splitE, "Prefixo de permissao", this.programFieldTechnicalProperties("permissionPrefix"));
     const pageTypeField = this.appendField(splitE, "Tipo de pagina", this.programFieldTechnicalProperties("pageType"));
     this.pageTypeSelect = $("<input>").appendTo(pageTypeField).kendoDropDownList({
       dataSource: [
         { value: "crud", text: "CRUD" },
+        { value: "master_detail", text: "Mestre-detalhe" },
         { value: "analytics", text: "Analytics / BI" },
         { value: "report", text: "Relatorios" },
         { value: "special_document", text: "Documento especial" },
         { value: "regulated_document", text: "Documento regulado" },
         { value: "custom", text: "Custom" }
       ],
       dataTextField: "text",
       dataValueField: "value",
       value: "crud",
       change: this.syncProgramTypeState.bind(this)
@@ -3847,20 +3849,133 @@
       const entity = payload && payload.entity ? payload.entity : null;
       if (entity) {
         this.state.entityDetailCache[code] = entity;
       }
       return entity;
     }.bind(this)).catch(function() {
       return null;
     });
   };
 
+  ProgramBuilder.prototype.masterDetailEntityFields = function(entityCode) {
+    const code = String(entityCode || "").trim();
+    if (!code) {
+      return [];
+    }
+    if (this.state.currentEntityCode === code) {
+      return this.collectEntityPayload().fields || [];
+    }
+    const entity = this.state.entityDetailCache && this.state.entityDetailCache[code];
+    return entity && Array.isArray(entity.fields) ? entity.fields : [];
+  };
+
+  ProgramBuilder.prototype.masterDetailConfigValue = function() {
+    const current = this.state.masterDetailConfig || {};
+    const masterEntityCode = String(current.masterEntityCode || this.builderEntitySelect && this.builderEntitySelect.value && this.builderEntitySelect.value() || "").trim();
+    return {
+      masterEntityCode: masterEntityCode,
+      createFlow: {
+        mode: current.createFlow && current.createFlow.mode === "draftWithChildren" ? "draftWithChildren" : "parentFirst",
+        endpointId: String(current.createFlow && current.createFlow.endpointId || "").trim()
+      },
+      details: Array.isArray(current.details) ? current.details.map(function(detail) {
+        return {
+          entityCode: String(detail && detail.entityCode || "").trim(),
+          title: String(detail && detail.title || "").trim(),
+          singularTitle: String(detail && detail.singularTitle || "").trim(),
+          parentField: String(detail && detail.parentField || "").trim(),
+          displayFields: Array.isArray(detail && detail.displayFields) ? detail.displayFields.slice() : [],
+          totals: Array.isArray(detail && detail.totals) ? detail.totals.map(function(total) {
+            return {
+              field: String(total && total.field || "").trim(),
+              label: String(total && total.label || "").trim(),
+              type: String(total && total.type || "").trim()
+            };
+          }) : []
+        };
+      }).filter(function(detail) {
+        return detail.entityCode && detail.parentField;
+      }) : []
+    };
+  };
+
+  ProgramBuilder.prototype.setMasterDetailConfig = function(config) {
+    this.state.masterDetailConfig = config || null;
+  };
+
+  ProgramBuilder.prototype.syncMasterDetailMasterEntity = function() {
+    const config = this.masterDetailConfigValue();
+    const masterEntityCode = String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "").trim();
+    config.masterEntityCode = masterEntityCode;
+    config.details = config.details.filter(function(detail) {
+      return detail.entityCode !== masterEntityCode;
+    });
+    this.setMasterDetailConfig(config);
+  };
+
+  ProgramBuilder.prototype.addMasterDetail = function(entityCode, parentField) {
+    const detailCode = String(entityCode || "").trim();
+    const parent = String(parentField || "").trim();
+    const masterCode = String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "").trim();
+    const detailEntity = this.findEntitySummary(detailCode);
+    const fields = this.masterDetailEntityFields(detailCode);
+    if (!masterCode || detailCode === masterCode || !detailEntity || detailEntity.entityType !== "persistence" || !parent || !fields.some(function(field) {
+      return String(field && field.code || "") === parent;
+    })) {
+      return false;
+    }
+    const config = this.masterDetailConfigValue();
+    config.masterEntityCode = masterCode;
+    const detail = {
+      entityCode: detailCode,
+      title: String(detailEntity.name || detailCode),
+      singularTitle: "",
+      parentField: parent,
+      displayFields: fields.filter(function(field) {
+        return String(field && field.code || "") !== parent && field && field.primaryKey !== true;
+      }).map(function(field) {
+        return String(field.code || "");
+      }).filter(Boolean),
+      totals: []
+    };
+    const index = config.details.findIndex(function(item) {
+      return item.entityCode === detailCode;
+    });
+    if (index >= 0) {
+      config.details[index] = detail;
+    } else {
+      config.details.push(detail);
+    }
+    this.setMasterDetailConfig(config);
+    this.schedulePreview();
+    return true;
+  };
+
+  ProgramBuilder.prototype.removeMasterDetail = function(entityCode) {
+    const config = this.masterDetailConfigValue();
+    config.details = config.details.filter(function(detail) {
+      return detail.entityCode !== String(entityCode || "").trim();
+    });
+    this.setMasterDetailConfig(config);
+    this.schedulePreview();
+  };
+
+  ProgramBuilder.prototype.setMasterDetailCreateFlow = function(mode, endpointId) {
+    const config = this.masterDetailConfigValue();
+    config.createFlow = {
+      mode: mode === "draftWithChildren" ? "draftWithChildren" : "parentFirst",
+      endpointId: String(endpointId || "").trim()
+    };
+    this.setMasterDetailConfig(config);
+    this.schedulePreview();
+  };
+
   ProgramBuilder.prototype.refreshAnalyticsConfigOptions = function() {
     if (!this.analyticsProgramPanel) {
       return;
     }
     const blueprint = this.state.analyticsWizard && this.state.analyticsWizard.datasetBlueprint || null;
     const fieldItems = this.analyticsFieldOptionItems().slice();
     const measureItems = this.analyticsMeasureOptionItems().slice();
     if (blueprint && Array.isArray(blueprint.dimensions)) {
       blueprint.dimensions.forEach(function(item) {
         if (!item || !item.id || fieldItems.some(function(existing) { return existing.value === item.id; })) {
@@ -5686,60 +5801,61 @@
     [this.apiListMethodSelect, this.apiDetailMethodSelect].forEach(function(widget) {
       if (widget && typeof widget.bind === "function") {
         widget.bind("change", self.schedulePreview.bind(self));
       }
     });
   };
 
   ProgramBuilder.prototype.syncProgramTypeState = function() {
     const pageType = String(this.pageTypeSelect && this.pageTypeSelect.value ? this.pageTypeSelect.value() || "crud" : "crud");
     const isCrud = pageType === "crud";
+    const isMasterDetail = pageType === "master_detail";
     const isAnalytics = pageType === "analytics";
     const isReport = pageType === "report";
     const isSpecialDocument = pageType === "special_document";
     const isRegulatedDocument = pageType === "regulated_document";
     const reportSourceType = String(this.reportSourceTypeSelect && this.reportSourceTypeSelect.val ? this.reportSourceTypeSelect.val() || "operational" : "operational");
     const specialSourceType = String(this.specialDocumentSourceTypeSelect && this.specialDocumentSourceTypeSelect.val ? this.specialDocumentSourceTypeSelect.val() || "operational" : "operational");
     const regulatedSourceType = String(this.regulatedDocumentSourceTypeSelect && this.regulatedDocumentSourceTypeSelect.val ? this.regulatedDocumentSourceTypeSelect.val() || "operational" : "operational");
-    const usesEntity = isCrud || isAnalytics || (isReport && reportSourceType !== "analytic") || (isSpecialDocument && specialSourceType !== "analytic") || (isRegulatedDocument && regulatedSourceType !== "analytic");
+    const usesEntity = isCrud || isMasterDetail || isAnalytics || (isReport && reportSourceType !== "analytic") || (isSpecialDocument && specialSourceType !== "analytic") || (isRegulatedDocument && regulatedSourceType !== "analytic");
     const entity = usesEntity ? this.findEntitySummary(String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "")) : null;
     const apiEntity = !!(entity && entity.entityType === "api");
     const sameLoadedApi = isCrud && apiEntity && this.state.currentEntityCode === String(this.builderEntitySelect && this.builderEntitySelect.value ? this.builderEntitySelect.value() || "" : "");
     const readOnlyApi = apiEntity && sameLoadedApi
       ? !String(this.apiCatalogCreateOperationSelect.value() || "").trim() && !String(this.apiCatalogUpdateOperationSelect.value() || "").trim() && !String(this.apiCatalogDeleteOperationSelect.value() || "").trim()
       : false;
 
     if (this.builderEntityField) {
       this.builderEntityField.toggle(usesEntity);
     }
     if (this.programWriteFlagsField) {
-      this.programWriteFlagsField.toggle(isCrud);
+      this.programWriteFlagsField.toggle(isCrud || isMasterDetail);
     }
     if (this.customProgramPanel) {
       this.customProgramPanel.toggle(pageType === "custom");
     }
     if (this.analyticsProgramPanel) {
       this.analyticsProgramPanel.toggle(isAnalytics);
     }
     if (this.reportProgramPanel) {
       this.reportProgramPanel.toggle(isReport);
     }
     if (this.specialDocumentProgramPanel) {
       this.specialDocumentProgramPanel.toggle(isSpecialDocument);
     }
     if (this.regulatedDocumentProgramPanel) {
       this.regulatedDocumentProgramPanel.toggle(isRegulatedDocument);
     }
     if (this.builderEntitySelect) {
       this.builderEntitySelect.enable(usesEntity);
     }
-    if (!isCrud) {
+    if (!isCrud && !isMasterDetail) {
       this.allowCreateInput.prop("checked", false);
       this.allowUpdateInput.prop("checked", false);
       this.allowDeleteInput.prop("checked", false);
     }
     if (isAnalytics && entity && entity.entityType && entity.entityType !== "persistence") {
       this.previewFooter.text("Analytics v1 aceita somente entidades persistence como fonte interna.");
     }
     if (isReport && reportSourceType !== "analytic" && entity && entity.entityType && entity.entityType !== "persistence") {
       this.previewFooter.text("Reports v1 aceitam somente entidades persistence na fonte operacional.");
     }
@@ -5763,26 +5879,29 @@
     }
     if (readOnlyApi) {
       this.allowCreateInput.prop("checked", false).prop("disabled", true);
       this.allowUpdateInput.prop("checked", false).prop("disabled", true);
       this.allowDeleteInput.prop("checked", false).prop("disabled", true);
     } else if (apiEntity && sameLoadedApi) {
       this.allowCreateInput.prop("disabled", String(this.apiCatalogCreateOperationSelect.value() || "").trim() === "");
       this.allowUpdateInput.prop("disabled", String(this.apiCatalogUpdateOperationSelect.value() || "").trim() === "");
       this.allowDeleteInput.prop("disabled", String(this.apiCatalogDeleteOperationSelect.value() || "").trim() === "");
     } else {
-      this.allowCreateInput.prop("disabled", !isCrud);
-      this.allowUpdateInput.prop("disabled", !isCrud);
-      this.allowDeleteInput.prop("disabled", !isCrud);
+      this.allowCreateInput.prop("disabled", !isCrud && !isMasterDetail);
+      this.allowUpdateInput.prop("disabled", !isCrud && !isMasterDetail);
+      this.allowDeleteInput.prop("disabled", !isCrud && !isMasterDetail);
     }
     this.syncAnalyticsProgramPanelState();
     this.schedulePreview();
+    if (this.state.propertySelection && this.state.propertySelection.kind === "program") {
+      this.renderPropertyInspector();
+    }
   };
 
   ProgramBuilder.prototype.renderVersionsGrid = function() {
     this.versionsGridElement.kendoGrid({
       dataSource: {
         data: [],
         pageSize: 8
       },
       selectable: "row",
       pageable: false,
@@ -10321,32 +10440,37 @@
           }
         });
       }).catch((error) => {
         this.handleError(error, "Nao foi possivel restaurar a entidade.");
       });
     });
   };
 
   ProgramBuilder.prototype.handleProgramEntityChange = function(prefillOnlyWhenEmpty) {
     const pageType = String(this.pageTypeSelect.value() || "crud");
-    if (pageType !== "crud" && pageType !== "analytics" && pageType !== "report") {
+    if (pageType !== "crud" && pageType !== "master_detail" && pageType !== "analytics" && pageType !== "report") {
       this.schedulePreview();
       return;
     }
     const entityCode = String(this.builderEntitySelect.value() || "");
     if (!entityCode) {
       this.schedulePreview();
       return;
     }
 
     const entity = this.findEntitySummary(entityCode);
-    if (pageType === "analytics" && entity && entity.entityType !== "persistence") {
+    if (pageType === "master_detail") {
+      this.syncMasterDetailMasterEntity();
+      if (entity && entity.entityType !== "persistence") {
+        this.previewFooter.text("Mestre-detalhe aceita somente entidade mestre persistence.");
+      }
+    } else if (pageType === "analytics" && entity && entity.entityType !== "persistence") {
       this.previewFooter.text("Analytics v1 aceita somente entidades persistence como fonte interna.");
     } else if (entity && entity.entityType === "api") {
       if (this.state.currentEntityCode === entityCode) {
         this.syncProgramWriteFlagsForApi();
       }
       this.previewFooter.text("Entidade API usa operacoes do cadastro da API. Habilite apenas create/update/delete que existirem no contrato.");
       this.syncProgramTypeState();
     } else if (entity && entity.entityType && entity.entityType !== "persistence") {
       this.previewFooter.text("Nesta etapa, a geracao de programa CRUD continua suportada apenas para entidades persistence e api.");
     }
@@ -10538,20 +10662,21 @@
     this.customEntryUrlInput.value(version.customEntryUrl || "");
     this.customFrameTitleInput.value(version.customFrameTitle || "");
     this.allowCreateInput.prop("checked", version.allowCreate !== false);
     this.allowUpdateInput.prop("checked", version.allowUpdate !== false);
     this.allowDeleteInput.prop("checked", version.allowDelete === true);
     this.changeSummaryTextArea.value(version.changeSummary || "");
     this.populateAnalyticsProgramConfig(version.builderConfig && version.builderConfig.analyticsConfig);
     this.populateReportProgramConfig(version.builderConfig && version.builderConfig.reportConfig);
     this.populateSpecialDocumentProgramConfig(version.builderConfig && version.builderConfig.specialDocumentConfig);
     this.populateRegulatedDocumentProgramConfig(version.builderConfig && version.builderConfig.regulatedDocumentConfig);
+    this.setMasterDetailConfig(version.builderConfig && version.builderConfig.masterDetailConfig);
     this.ensureEntityDetail(version.builderEntityCode || "");
     this.syncProgramTypeState();
     this.renderDefinition(version.generatedDefinition || {});
     this.updatePreviewMeta(version);
     this.updateBanner();
     this.syncSelectedVersionRow();
     this.syncToolbarState();
     this.setNavigatorSelection(version.programCode ? "program" : null, version.programCode || "");
     this.selectPropertyNode("program", { code: version.programCode || "" });
     this.refreshCompareChoices();
@@ -10586,20 +10711,21 @@
     this.customEntryUrlInput.value("");
     this.customFrameTitleInput.value("");
     this.allowCreateInput.prop("checked", true);
     this.allowUpdateInput.prop("checked", true);
     this.allowDeleteInput.prop("checked", false);
     this.changeSummaryTextArea.value("");
     this.populateAnalyticsProgramConfig(null);
     this.populateReportProgramConfig(null);
     this.populateSpecialDocumentProgramConfig(null);
     this.populateRegulatedDocumentProgramConfig(null);
+    this.setMasterDetailConfig(null);
     this.state.analyticsValidator = {
       signature: "",
       datasetId: "",
       pipelineId: "",
       rollbackVersionNo: "",
       parameterValues: {},
       sampleResult: null,
       sampleError: "",
       cacheStatus: null,
       cacheError: "",
@@ -10687,20 +10813,21 @@
     this.renderRelationshipView();
     this.updateWorkspaceSummary();
     this.syncToolbarState();
   };
 
   ProgramBuilder.prototype.collectProgramPayload = function() {
     const current = this.state.currentVersion || {};
     const editableCurrent = current.status === "draft";
     const pageType = String(this.pageTypeSelect.value() || "crud");
     const usesEntity = pageType === "crud"
+      || pageType === "master_detail"
       || pageType === "analytics"
       || (pageType === "report" && String(this.reportSourceTypeSelect && this.reportSourceTypeSelect.val ? this.reportSourceTypeSelect.val() || "operational" : "operational") !== "analytic")
       || (pageType === "special_document" && String(this.specialDocumentSourceTypeSelect && this.specialDocumentSourceTypeSelect.val ? this.specialDocumentSourceTypeSelect.val() || "operational" : "operational") !== "analytic")
       || (pageType === "regulated_document" && String(this.regulatedDocumentSourceTypeSelect && this.regulatedDocumentSourceTypeSelect.val ? this.regulatedDocumentSourceTypeSelect.val() || "operational" : "operational") !== "analytic");
     return {
       id: editableCurrent ? (current.id || null) : null,
       programCode: this.programCodeInput.value(),
       programTitle: this.programTitleInput.value(),
       module: String(this.moduleInput.value() || ""),
       screenId: this.screenIdInput.value(),
@@ -10714,42 +10841,43 @@
       ownerScope: String(this.ownerScopeSelect.value() || "system"),
       customizationPolicy: String(this.customizationPolicySelect.value() || "overlay_only"),
       subscriberId: this.subscriberIdInput.value(),
       baseProgramCode: this.baseProgramCodeInput.value(),
       baseProgramVersionId: this.baseProgramVersionIdInput.value(),
       upgradeFrozen: this.upgradeFrozenInput.is(":checked"),
       frozenReason: this.frozenReasonInput.value(),
       publicationPolicy: {
         allowedDatabaseEnvironments: this.parseCommaSeparatedValues(this.publicationPolicyInput.value())
       },
-      allowCreate: pageType === "crud" && this.allowCreateInput.is(":checked"),
-      allowUpdate: pageType === "crud" && this.allowUpdateInput.is(":checked"),
-      allowDelete: pageType === "crud" && this.allowDeleteInput.is(":checked"),
+      allowCreate: (pageType === "crud" || pageType === "master_detail") && this.allowCreateInput.is(":checked"),
+      allowUpdate: (pageType === "crud" || pageType === "master_detail") && this.allowUpdateInput.is(":checked"),
+      allowDelete: (pageType === "crud" || pageType === "master_detail") && this.allowDeleteInput.is(":checked"),
+      masterDetailConfig: pageType === "master_detail" ? this.masterDetailConfigValue() : null,
       analyticsConfig: pageType === "analytics" ? this.collectAnalyticsConfig() : null,
       reportConfig: pageType === "report" ? this.collectReportConfig() : null,
       specialDocumentConfig: pageType === "special_document" ? this.collectSpecialDocumentConfig() : null,
       regulatedDocumentConfig: pageType === "regulated_document" ? this.collectRegulatedDocumentConfig() : null,
       customMode: pageType === "custom" ? String(this.customModeSelect.value() || "iframe") : "",
       customEntryUrl: pageType === "custom" ? this.customEntryUrlInput.value() : "",
       customFrameTitle: pageType === "custom" ? this.customFrameTitleInput.value() : "",
       changeSummary: this.changeSummaryTextArea.value() || ""
     };
   };
 
   ProgramBuilder.prototype.handleNewDraft = function() {
     this.releaseCurrentLock();
     this.state.currentVersion = null;
     this.state.currentProgramCode = "";
     this.state.versions = [];
     this.versionsGrid.dataSource.data([]);
     this.resetProgramForm();
-    if (["crud", "analytics", "report", "special_document", "regulated_document"].indexOf(String(this.pageTypeSelect.value() || "crud")) >= 0) {
+    if (["crud", "master_detail", "analytics", "report", "special_document", "regulated_document"].indexOf(String(this.pageTypeSelect.value() || "crud")) >= 0) {
       this.builderEntitySelect.value(this.state.currentEntityCode || "");
       this.handleProgramEntityChange(true);
     }
     this.bannerElement.text("Novo rascunho. Escolha uma entidade modelada, gere o preview e publique quando estiver pronto.");
     this.activateEditorTab(2);
     this.activateSideTab(0);
   };
 
   ProgramBuilder.prototype.schedulePreview = function() {
     const self = this;
@@ -10760,20 +10888,21 @@
     }, 250);
   };
 
   ProgramBuilder.prototype.handlePreview = function() {
     return this.requestPreview(true);
   };
 
   ProgramBuilder.prototype.requestPreview = function(notifyOnSuccess) {
     const payload = this.collectProgramPayload();
     const usesEntity = payload.pageType === "crud"
+      || payload.pageType === "master_detail"
       || payload.pageType === "analytics"
       || (payload.pageType === "report" && String(payload.reportConfig && payload.reportConfig.sourceType || "operational") !== "analytic")
       || (payload.pageType === "special_document" && String(payload.specialDocumentConfig && payload.specialDocumentConfig.sourceType || "operational") !== "analytic")
       || (payload.pageType === "regulated_document" && String(payload.regulatedDocumentConfig && payload.regulatedDocumentConfig.sourceType || "operational") !== "analytic");
     const hasRequiredCrud = usesEntity
       ? (!!payload.programCode && !!payload.programTitle && !!payload.builderEntityCode && !!payload.screenId && !!payload.version)
       : (!!payload.programCode && !!payload.programTitle && !!payload.screenId && !!payload.version && (payload.pageType !== "custom" || !!payload.customEntryUrl));
     if (!hasRequiredCrud) {
       this.renderLocalSummary(payload);
       return Promise.resolve(null);
@@ -10823,20 +10952,37 @@
         programId: payload.programCode
       },
       permissions: {
         create: payload.allowCreate,
         edit: payload.allowUpdate,
         delete: payload.allowDelete
       }
     };
     if (payload.pageType === "crud") {
       preview.runtime.entityCode = payload.builderEntityCode;
+    } else if (payload.pageType === "master_detail") {
+      const masterDetailConfig = payload.masterDetailConfig || {};
+      preview.runtime.entityCode = payload.builderEntityCode;
+      preview.master = {
+        entityCode: masterDetailConfig.masterEntityCode || payload.builderEntityCode
+      };
+      preview.details = (masterDetailConfig.details || []).map(function(detail) {
+        return {
+          entityCode: detail.entityCode,
+          title: detail.title,
+          singularTitle: detail.singularTitle,
+          parentField: detail.parentField,
+          displayFields: detail.displayFields || [],
+          totals: detail.totals || []
+        };
+      });
+      preview.createFlow = masterDetailConfig.createFlow || { mode: "parentFirst", endpointId: "" };
     } else if (payload.pageType === "analytics") {
       const analyticsConfig = Object.assign({}, this.defaultAnalyticsConfig(), payload.analyticsConfig || {});
       const semanticPipelines = Array.isArray(analyticsConfig.semanticPipelines) ? analyticsConfig.semanticPipelines : [];
       const firstPipeline = semanticPipelines[0] || null;
       const publishedDatasetId = firstPipeline && firstPipeline.publishConfig && firstPipeline.publishConfig.publishedDatasetId || "principal_published";
       const blueprint = analyticsConfig.datasetBlueprint || null;
       const blueprintFields = blueprint && Array.isArray(blueprint.fields) ? blueprint.fields : null;
       const blueprintDimensions = blueprint && Array.isArray(blueprint.dimensions) ? blueprint.dimensions : null;
       const blueprintMeasures = blueprint && Array.isArray(blueprint.measures) ? blueprint.measures : null;
       const blueprintParameters = blueprint && Array.isArray(blueprint.parameters) ? blueprint.parameters : null;
@@ -11000,21 +11146,21 @@
       };
     }
     this.state.preview = {
       generatedDefinition: preview,
       localOnly: true
     };
     this.renderDefinition(preview);
     this.updatePreviewMeta({
       status: this.state.currentVersion && this.state.currentVersion.status || "draft",
       version: payload.version,
-      builderEntityCode: payload.pageType === "crud" || payload.pageType === "analytics" || payload.pageType === "report" || payload.pageType === "special_document" || payload.pageType === "regulated_document" ? payload.builderEntityCode : "",
+      builderEntityCode: payload.pageType === "crud" || payload.pageType === "master_detail" || payload.pageType === "analytics" || payload.pageType === "report" || payload.pageType === "special_document" || payload.pageType === "regulated_document" ? payload.builderEntityCode : "",
       screenId: payload.screenId
     });
     this.previewFooter.text("Resumo local. O JSON completo aparece quando os campos obrigatorios permitem gerar preview no backend.");
   };
 
   ProgramBuilder.prototype.handleSaveDraft = function() {
     const payload = this.collectProgramPayload();
     this.http.request({
       url: "/api/admin/program-builder/drafts",
       method: "POST",
diff --git a/src/styles/program-builder.css b/src/styles/program-builder.css
index 6d22a594..491d2428 100644
--- a/src/styles/program-builder.css
+++ b/src/styles/program-builder.css
@@ -743,20 +743,75 @@
 
 .program-builder-check-cell {
   text-align: center;
 }
 
 .program-builder-inline-hint {
   color: #667085;
   font-size: 13px;
 }
 
+.program-builder-master-detail-context {
+  display: grid;
+  gap: 12px;
+  padding: 12px;
+  border: 1px solid #b8d6f5;
+  border-radius: 8px;
+  background: #f5f9ff;
+}
+
+.program-builder-master-detail-context h3 {
+  margin: 0;
+  color: #0f4f8f;
+  font-size: 15px;
+}
+
+.program-builder-master-detail-summary,
+.program-builder-master-detail-editor {
+  display: grid;
+  grid-template-columns: repeat(2, minmax(0, 1fr));
+  gap: 10px;
+}
+
+.program-builder-master-detail-summary-item,
+.program-builder-master-detail-row {
+  display: grid;
+  gap: 3px;
+  min-width: 0;
+  padding: 8px 10px;
+  border: 1px solid #d9dee7;
+  border-radius: 6px;
+  background: #ffffff;
+}
+
+.program-builder-master-detail-summary-item span,
+.program-builder-master-detail-row span {
+  color: #667085;
+  font-size: 12px;
+}
+
+.program-builder-master-detail-summary-item strong,
+.program-builder-master-detail-row strong {
+  overflow-wrap: anywhere;
+  color: #344054;
+}
+
+.program-builder-master-detail-list {
+  display: grid;
+  gap: 8px;
+}
+
+.program-builder-master-detail-row {
+  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
+  align-items: center;
+}
+
 .program-builder-preview {
   display: grid;
   grid-template-rows: auto 1fr auto;
   gap: 12px;
   min-height: 100%;
 }
 
 .program-builder-properties,
 .program-builder-relations,
 .program-builder-compare {
@@ -1353,11 +1408,17 @@
     grid-template-columns: 1fr;
   }
 
   .program-builder-navigator-stats {
     grid-template-columns: 1fr;
   }
 
   .program-builder-diff-values {
     grid-template-columns: 1fr;
   }
+
+  .program-builder-master-detail-summary,
+  .program-builder-master-detail-editor,
+  .program-builder-master-detail-row {
+    grid-template-columns: 1fr;
+  }
 }
diff --git a/tests/frontend/program-builder-technical-smoke.mjs b/tests/frontend/program-builder-technical-smoke.mjs
index 9d2479c0..32ba648b 100644
--- a/tests/frontend/program-builder-technical-smoke.mjs
+++ b/tests/frontend/program-builder-technical-smoke.mjs
@@ -1,16 +1,17 @@
 import { chromium } from "playwright";
 import fs from "node:fs/promises";
 import path from "node:path";
+import { pathToFileURL } from "node:url";
 
-const repoRoot = "C:/construtor-pg";
-const baseUrl = "file:///C:/construtor-pg";
+const repoRoot = process.cwd();
+const baseUrl = pathToFileURL(repoRoot + path.sep).href.replace(/\/$/, "");
 const outputDir = path.join(repoRoot, "tmp");
 
 async function ensureOutputDir() {
   await fs.mkdir(outputDir, { recursive: true });
 }
 
 async function clickFirstFromContainer(page, containerName) {
   const clicked = await page.evaluate((name) => {
     const app = window.programBuilderDemoApp;
     const container = app && app[name];
@@ -43,20 +44,78 @@ async function closeTechnicalDialog(page) {
 async function main() {
   await ensureOutputDir();
   const browser = await chromium.launch({ headless: true });
   const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
 
   try {
     await page.goto(baseUrl + "/examples/pages/program-builder-technical-properties.html");
     await page.waitForFunction(() => Boolean(window.programBuilderDemoApp), null, { timeout: 30000 });
     await page.waitForFunction(() => document.querySelectorAll('[data-crud-role="program-builder-technical-info"]').length > 0, null, { timeout: 30000 });
 
+    const masterDetailPayload = await page.evaluate(() => {
+      const app = window.programBuilderDemoApp;
+      app.state.entities = [
+        { code: "pedido_venda", name: "Pedido de venda", entityType: "persistence" },
+        { code: "pedido_item", name: "Item do pedido", entityType: "persistence" }
+      ];
+      app.state.entityDetailCache = {
+        pedido_venda: {
+          code: "pedido_venda",
+          fields: [
+            { code: "id", label: "ID", dataType: "integer", primaryKey: true },
+            { code: "numero", label: "Numero", dataType: "string" }
+          ]
+        },
+        pedido_item: {
+          code: "pedido_item",
+          fields: [
+            { code: "id", label: "ID", dataType: "integer", primaryKey: true },
+            { code: "pedido_id", label: "Pedido", dataType: "integer" },
+            { code: "produto", label: "Produto", dataType: "string" },
+            { code: "quantidade", label: "Quantidade", dataType: "decimal" }
+          ]
+        }
+      };
+      app.applyBootstrapData();
+      app.pageTypeSelect.value("master_detail");
+      app.builderEntitySelect.value("pedido_venda");
+      app.addMasterDetail("pedido_item", "pedido_id");
+      app.syncProgramTypeState();
+      app.selectPropertyNode("program", { code: "" });
+      const payload = app.collectProgramPayload();
+      app.renderLocalSummary(payload);
+      return {
+        payload,
+        preview: app.state.preview && app.state.preview.generatedDefinition,
+        contextualPanel: app.propertiesElement.find(".program-builder-master-detail-context").length,
+        hasCustomModeLabel: app.propertiesElement.text().indexOf("Modo custom") >= 0
+      };
+    });
+    if (masterDetailPayload.payload.pageType !== "master_detail") {
+      throw new Error("O editor nao coletou pageType=master_detail.");
+    }
+    if (masterDetailPayload.payload.builderEntityCode !== "pedido_venda") {
+      throw new Error("O editor nao manteve pedido_venda como entidade mestre.");
+    }
+    if (!masterDetailPayload.payload.masterDetailConfig || masterDetailPayload.payload.masterDetailConfig.masterEntityCode !== "pedido_venda") {
+      throw new Error("O payload nao informou a configuracao declarativa do mestre.");
+    }
+    if (masterDetailPayload.payload.masterDetailConfig.details.length !== 1 || masterDetailPayload.payload.masterDetailConfig.details[0].parentField !== "pedido_id") {
+      throw new Error("O payload nao preservou parentField para pedido_item.");
+    }
+    if (!masterDetailPayload.preview || masterDetailPayload.preview.master.entityCode !== "pedido_venda" || masterDetailPayload.preview.details[0].parentField !== "pedido_id") {
+      throw new Error("O preview local nao exibiu mestre e filhos declarativos.");
+    }
+    if (masterDetailPayload.preview.createFlow.mode !== "parentFirst" || masterDetailPayload.contextualPanel !== 1 || masterDetailPayload.hasCustomModeLabel) {
+      throw new Error("O painel contextual do mestre-detalhe esta incompleto ou exibiu controles custom.");
+    }
+
     const entityTriggers = await page.evaluate(() => window.programBuilderDemoApp.entityPanel.find('[data-crud-role="program-builder-technical-info"]').length);
     await clickFirstFromContainer(page, "entityPanel");
     await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
     await page.locator(".crud-technical-download-json-button").click();
     await page.screenshot({ path: path.join(outputDir, "program-builder-technical-entity.png"), fullPage: true });
     await closeTechnicalDialog(page);
 
     await page.locator(".k-tabstrip-items li").nth(2).click();
     await page.waitForFunction(() => window.programBuilderDemoApp.programPanel.find('[data-crud-role="program-builder-technical-info"]').length > 0, null, { timeout: 10000 });
     const programTriggers = await page.evaluate(() => window.programBuilderDemoApp.programPanel.find('[data-crud-role="program-builder-technical-info"]').length);
@@ -83,21 +142,22 @@ async function main() {
     const inspectorTriggers = await page.evaluate(() => window.programBuilderDemoApp.propertiesElement.find('[data-crud-role="program-builder-technical-info"]').length);
     await clickFirstFromContainer(page, "propertiesElement");
     await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
     await page.screenshot({ path: path.join(outputDir, "program-builder-technical-inspector.png"), fullPage: true });
     await closeTechnicalDialog(page);
 
     const result = {
       entityTriggers,
       programTriggers,
       apiTriggers,
-      inspectorTriggers
+      inspectorTriggers,
+      masterDetailPayload
     };
 
     await fs.writeFile(
       path.join(outputDir, "program-builder-technical-smoke-result.json"),
       JSON.stringify(result, null, 2),
       "utf8"
     );
 
     console.log(JSON.stringify(result, null, 2));
   } finally {

