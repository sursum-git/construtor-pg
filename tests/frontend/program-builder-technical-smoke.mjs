import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";

const repoRoot = process.cwd();
const baseUrl = pathToFileURL(repoRoot + path.sep).href.replace(/\/$/, "");
const outputDir = path.join(repoRoot, "tmp");

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function clickFirstFromContainer(page, containerName) {
  const clicked = await page.evaluate((name) => {
    const app = window.programBuilderDemoApp;
    const container = app && app[name];
    if (!container || typeof container.find !== "function") {
      return false;
    }
    const button = container.find('[data-crud-role="program-builder-technical-info"]').get(0);
    if (!button) {
      return false;
    }
    button.click();
    return true;
  }, containerName);
  if (!clicked) {
    throw new Error("Nenhum botao tecnico encontrado em " + containerName);
  }
}

async function closeTechnicalDialog(page) {
  await page.evaluate(() => {
    window.jQuery(".crud-technical-info-window").each(function() {
      const widget = window.jQuery(this).data("kendoWindow");
      if (widget) {
        widget.close();
      }
    });
  });
}

async function verifyMasterDetailLayout(page, width) {
  await page.setViewportSize({ width, height: 900 });
  const controls = await page.evaluate(() => {
    const app = window.programBuilderDemoApp;
    app.activateSideTab(1);
    const context = app.propertiesElement.find(".program-builder-master-detail-context").get(0);
    const rect = context.getBoundingClientRect();
    const controlRects = Array.from(context.querySelectorAll(".program-builder-master-detail-editor > .program-builder-field, .program-builder-master-detail-editor > button")).map((element) => {
      const controlRect = element.getBoundingClientRect();
      return { left: controlRect.left, top: controlRect.top, right: controlRect.right, bottom: controlRect.bottom, width: controlRect.width, height: controlRect.height };
    });
    const overlaps = controlRects.some((current, index) => controlRects.slice(index + 1).some((next) => current.left < next.right && current.right > next.left && current.top < next.bottom && current.bottom > next.top));
    return {
      context: { left: rect.left, right: rect.right, width: rect.width, height: rect.height },
      controls: controlRects,
      overlaps,
      viewportWidth: window.innerWidth
    };
  });
  const preview = await page.evaluate(() => {
    const app = window.programBuilderDemoApp;
    app.activateSideTab(0);
    const rect = app.previewPanel.get(0).getBoundingClientRect();
    return { left: rect.left, right: rect.right, width: rect.width, height: rect.height };
  });
  if (controls.context.width <= 0 || controls.context.height <= 0 || controls.context.left < 0 || controls.context.right > controls.viewportWidth || controls.controls.some((item) => item.width <= 0 || item.height <= 0) || controls.overlaps) {
    throw new Error(`Controles mestre-detalhe sem acesso ou sobrepostos em ${width}px.`);
  }
  if (preview.width <= 0 || preview.height <= 0 || preview.left >= controls.viewportWidth || preview.right <= 0) {
    throw new Error(`Preview nao acessivel em ${width}px.`);
  }
  return { width, controls, preview };
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });

  try {
    await page.goto(baseUrl + "/examples/pages/program-builder-technical-properties.html");
    await page.waitForFunction(() => Boolean(window.programBuilderDemoApp), null, { timeout: 30000 });
    await page.waitForFunction(() => document.querySelectorAll('[data-crud-role="program-builder-technical-info"]').length > 0, null, { timeout: 30000 });

    const masterDetailPayload = await page.evaluate(async () => {
      const app = window.programBuilderDemoApp;
      app.state.entities = [
        { code: "cliente", name: "Cliente", entityType: "persistence", tableName: "t_cliente" },
        { code: "pedido_venda", name: "Pedido de venda", entityType: "persistence", tableName: "t_pedido_venda" },
        { code: "pedido_item", name: "Item do pedido", entityType: "persistence", tableName: "t_pedido_item" }
      ];
      app.state.entityDetailCache = {
        cliente: {
          code: "cliente",
          tableName: "t_cliente",
          fields: [
            { code: "id", columnName: "id", label: "ID", dataType: "integer", primaryKey: true },
            { code: "nome", columnName: "nome", label: "Nome", dataType: "string" }
          ]
        },
        pedido_venda: {
          code: "pedido_venda",
          tableName: "t_pedido_venda",
          fields: [
            { code: "id", columnName: "id", label: "ID", dataType: "integer", primaryKey: true },
            { code: "numero", label: "Numero", dataType: "string" }
          ]
        },
        pedido_item: {
          code: "pedido_item",
          tableName: "t_pedido_item",
          fields: [
            { code: "id", label: "ID", dataType: "integer", primaryKey: true },
            { code: "pedido_id", label: "Pedido", dataType: "integer", foreignKeyTable: "t_pedido_venda", foreignKeyColumn: "id" },
            { code: "cliente_id", label: "Cliente", dataType: "integer", foreignKeyTable: "t_cliente", foreignKeyColumn: "id" },
            { code: "produto", label: "Produto", dataType: "string" },
            { code: "quantidade", label: "Quantidade", dataType: "decimal" },
            { code: "valor_total", label: "Valor total", dataType: "currency" }
          ]
        }
      };
      app.applyBootstrapData();
      app.pageTypeSelect.value("master_detail");
      app.builderEntitySelect.value("pedido_venda");
      const rejectsNonRelationship = app.addMasterDetail("pedido_item", "produto") === false;
      const acceptsRelationship = app.addMasterDetail("pedido_item", "pedido_id") === true;
      app.updateMasterDetailDetail("pedido_item", {
        title: "Produtos do pedido",
        displayFields: ["produto", "valor_total"],
        totals: [{ field: "valor_total", label: "Total dos produtos", type: "currency" }]
      });
      app.syncProgramTypeState();
      app.selectPropertyNode("program", { code: "" });
      const configuredDetail = app.masterDetailConfigValue().details[0];
      const detailCombo = app.propertiesElement.find("[data-role='combobox']").data("kendoComboBox");
      detailCombo.value("pedido_item");
      detailCombo.trigger("change");
      await new Promise(function(resolve) { window.setTimeout(resolve, 0); });
      const eligibleParentFields = app.propertiesElement.find("[data-role='dropdownlist']").filter(function() {
        return window.jQuery(this).data("kendoDropDownList")?.options?.dataValueField === "value";
      }).first().data("kendoDropDownList").dataSource.data().map(function(item) { return item.value; });
      const editorControls = {
        title: app.propertiesElement.find(".program-builder-master-detail-title").length,
        displayFields: app.propertiesElement.find(".program-builder-master-detail-display-fields").length,
        totals: app.propertiesElement.find(".program-builder-master-detail-totals").length
      };
      app.builderEntitySelect.value("cliente");
      app.handleProgramEntityChange(false);
      const propertyBaseEntity = app.propertiesElement.find(".program-builder-field").filter(function() {
        return window.jQuery(this).find(".program-builder-field-label-row span").first().text() === "Entidade base";
      }).find("select");
      propertyBaseEntity.val("cliente").triggerHandler("change");
      const context = app.propertiesElement.find(".program-builder-master-detail-context");
      const changedMaster = {
        masterEntityCode: app.masterDetailConfigValue().masterEntityCode,
        contextualText: context.find(".program-builder-master-detail-summary").text(),
        detailOptions: context.find("[data-role='combobox']").data("kendoComboBox").dataSource.data().map(function(item) { return item.value; }),
        details: app.masterDetailConfigValue().details
      };
      app.setMasterDetailConfig({
        masterEntityCode: "cliente",
        createFlow: { mode: "draftWithChildren", endpointId: "carregamento.nao.permitido" },
        details: app.masterDetailConfigValue().details
      });
      const loadedEndpoint = app.masterDetailConfigValue().createFlow.endpointId;
      app.setMasterDetailCreateFlow("draftWithChildren", "nao.permitido");
      const normalizedEndpoint = app.masterDetailConfigValue().createFlow.endpointId;
      const payload = app.collectProgramPayload();
      app.renderLocalSummary(payload);
      return {
        payload,
        preview: app.state.preview && app.state.preview.generatedDefinition,
        contextualPanel: app.propertiesElement.find(".program-builder-master-detail-context").length,
        hasCustomModeLabel: app.propertiesElement.text().indexOf("Modo custom") >= 0,
        switchedViaPropertyPanel: propertyBaseEntity.length === 1,
        changedMaster,
        configuredDetail,
        eligibleParentFields,
        editorControls,
        rejectsNonRelationship,
        acceptsRelationship,
        loadedEndpoint,
        normalizedEndpoint
      };
    });
    if (masterDetailPayload.payload.pageType !== "master_detail") {
      throw new Error("O editor nao coletou pageType=master_detail.");
    }
    if (masterDetailPayload.payload.builderEntityCode !== "cliente") {
      throw new Error("O editor nao atualizou a entidade mestre pelo painel de propriedades.");
    }
    if (!masterDetailPayload.payload.masterDetailConfig || masterDetailPayload.payload.masterDetailConfig.masterEntityCode !== "cliente") {
      throw new Error("O payload nao atualizou a configuracao declarativa do mestre.");
    }
    if (!masterDetailPayload.rejectsNonRelationship || !masterDetailPayload.acceptsRelationship || masterDetailPayload.eligibleParentFields.join(",") !== "pedido_id") {
      throw new Error("O editor ofereceu ou aceitou parentField sem FK coerente para o mestre: " + JSON.stringify({
        rejectsNonRelationship: masterDetailPayload.rejectsNonRelationship,
        acceptsRelationship: masterDetailPayload.acceptsRelationship,
        eligibleParentFields: masterDetailPayload.eligibleParentFields
      }));
    }
    if (masterDetailPayload.configuredDetail.title !== "Produtos do pedido" || masterDetailPayload.configuredDetail.displayFields.join(",") !== "produto,valor_total" || masterDetailPayload.configuredDetail.totals[0].label !== "Total dos produtos") {
      throw new Error("O editor nao persistiu titulo, displayFields e totals da filha.");
    }
    if (masterDetailPayload.editorControls.title < 1 || masterDetailPayload.editorControls.displayFields < 1 || masterDetailPayload.editorControls.totals < 1) {
      throw new Error("O painel nao exibiu os controles de titulo, displayFields e totals da filha: " + JSON.stringify(masterDetailPayload.editorControls));
    }
    if (!masterDetailPayload.preview || masterDetailPayload.preview.master.entityCode !== "cliente" || masterDetailPayload.preview.details.length !== 0) {
      throw new Error("O preview local nao removeu a filha incompatível ao trocar o mestre.");
    }
    if (masterDetailPayload.preview.createFlow.mode !== "draftWithChildren" || masterDetailPayload.contextualPanel !== 1 || masterDetailPayload.hasCustomModeLabel) {
      throw new Error("O painel contextual do mestre-detalhe esta incompleto ou exibiu controles custom.");
    }
    if (!masterDetailPayload.switchedViaPropertyPanel || masterDetailPayload.changedMaster.masterEntityCode !== "cliente" || masterDetailPayload.changedMaster.contextualText.indexOf("cliente") < 0 || masterDetailPayload.changedMaster.detailOptions.includes("cliente") || masterDetailPayload.changedMaster.details.length !== 0) {
      throw new Error("A troca de mestre nao atualizou imediatamente o inspetor e as opcoes de filhos.");
    }
    if (masterDetailPayload.loadedEndpoint !== "createGraph" || masterDetailPayload.normalizedEndpoint !== "createGraph" || masterDetailPayload.payload.masterDetailConfig.createFlow.endpointId !== "createGraph") {
      throw new Error("O endpointId fora da lista fechada permaneceu no estado ou payload.");
    }
    const layout1366 = await verifyMasterDetailLayout(page, 1366);
    const layout768 = await verifyMasterDetailLayout(page, 768);

    const entityTriggers = await page.evaluate(() => window.programBuilderDemoApp.entityPanel.find('[data-crud-role="program-builder-technical-info"]').length);
    await clickFirstFromContainer(page, "entityPanel");
    await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
    await page.locator(".crud-technical-download-json-button").click();
    await page.screenshot({ path: path.join(outputDir, "program-builder-technical-entity.png"), fullPage: true });
    await closeTechnicalDialog(page);

    await page.locator(".k-tabstrip-items li").nth(2).click();
    await page.waitForFunction(() => window.programBuilderDemoApp.programPanel.find('[data-crud-role="program-builder-technical-info"]').length > 0, null, { timeout: 10000 });
    const programTriggers = await page.evaluate(() => window.programBuilderDemoApp.programPanel.find('[data-crud-role="program-builder-technical-info"]').length);
    await clickFirstFromContainer(page, "programPanel");
    await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "program-builder-technical-program.png"), fullPage: true });
    await closeTechnicalDialog(page);

    await page.evaluate(async () => {
      const app = window.programBuilderDemoApp;
      app.entityTypeSelect.value("api");
      app.syncEntityTypeState();
      app.apiCatalogSourceSelect.value("odoo_mock");
      await app.handleApiSourceSelectionChange();
    });
    await page.locator(".k-tabstrip-items li").nth(1).click();
    await page.waitForFunction(() => window.programBuilderDemoApp.apiSourcePanel.find('[data-crud-role="program-builder-technical-info"]').length > 0, null, { timeout: 10000 });
    const apiTriggers = await page.evaluate(() => window.programBuilderDemoApp.apiSourcePanel.find('[data-crud-role="program-builder-technical-info"]').length);
    await clickFirstFromContainer(page, "apiSourcePanel");
    await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "program-builder-technical-api.png"), fullPage: true });
    await closeTechnicalDialog(page);

    const inspectorTriggers = await page.evaluate(() => window.programBuilderDemoApp.propertiesElement.find('[data-crud-role="program-builder-technical-info"]').length);
    await clickFirstFromContainer(page, "propertiesElement");
    await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "program-builder-technical-inspector.png"), fullPage: true });
    await closeTechnicalDialog(page);

    const result = {
      entityTriggers,
      programTriggers,
      apiTriggers,
      inspectorTriggers,
      masterDetailPayload,
      layout1366,
      layout768
    };

    await fs.writeFile(
      path.join(outputDir, "program-builder-technical-smoke-result.json"),
      JSON.stringify(result, null, 2),
      "utf8"
    );

    console.log(JSON.stringify(result, null, 2));
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
