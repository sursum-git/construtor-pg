import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const pageUrl = "file:///C:/construtor-pg/examples/pages/import-export-admin-demo.html";
const outputDir = path.join(repoRoot, "tmp");

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function waitForTreeItems(page, selector) {
  await page.waitForFunction((treeSelector) => {
    const host = document.querySelector(treeSelector);
    return !!(host && host.querySelector(".k-treeview-item, .k-item"));
  }, selector, { timeout: 15000 });
}

async function screenshot(page, fileName) {
  await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

const browser = await chromium.launch({ headless: true });
try {
  await ensureOutputDir();
  const page = await browser.newPage({ viewport: { width: 1440, height: 980 } });
  const result = {
    txtTreeItems: 0,
    xmlTreeItems: 0,
    previewTreeItems: 0,
    executionHistoryItems: 0,
    selectedExecutionHasDetail: false,
    persistedTabIndex: -1,
    persistedFilterMode: "",
    persistedCurrentCode: "",
    persistedVersionNumber: "",
    persistedScheduleCode: "",
    persistedDesignerPath: "",
    persistedPreviewPath: ""
  };

  await page.goto(pageUrl);
  await page.waitForFunction(() => !!window.importExportAdminDemoApp, null, { timeout: 15000 });
  await page.evaluate(() => {
    if (window.CrudUtils) {
      window.CrudUtils.confirmAction = () => Promise.resolve(true);
    }
  });

  await page.click(".k-grid-content tr:first-child");
  await waitForTreeItems(page, ".import-export-admin-tree");
  result.txtTreeItems = await page.locator(".import-export-admin-tree .k-treeview-item, .import-export-admin-tree .k-item").count();
  await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    const treeView = app && app.treeElement ? app.treeElement.data("kendoTreeView") : null;
    if (!treeView || !app.treeElement) {
      return;
    }
    const rows = app.treeElement.find(".k-item, .k-treeview-item");
    const target = rows.length > 1 ? rows.eq(1) : rows.eq(0);
    if (target && target.length) {
      treeView.select(target);
      app.handleDesignerSelect({ node: target.get(0) });
    }
  });

  await page.locator('button:has-text("Preview")').click();
  await waitForTreeItems(page, ".import-export-admin-preview-splitter .import-export-admin-tree");
  result.previewTreeItems = await page.locator(".import-export-admin-preview-splitter .import-export-admin-tree .k-treeview-item, .import-export-admin-preview-splitter .import-export-admin-tree .k-item").count();
  await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    const treeView = app && app.previewStructureTree ? app.previewStructureTree.data("kendoTreeView") : null;
    if (!treeView || !app.previewStructureTree) {
      return;
    }
    const rows = app.previewStructureTree.find(".k-item, .k-treeview-item");
    const target = rows.length > 1 ? rows.eq(1) : rows.eq(0);
    if (target && target.length) {
      treeView.select(target);
      app.handlePreviewStructureSelect({ node: target.get(0) });
    }
  });
  await screenshot(page, "admin-integracoes-smoke-txt-preview.png");

  await page.getByRole("button", { name: "Executar", exact: true }).click();
  await page.waitForSelector(".import-export-admin-history-card", { timeout: 15000 });
  result.executionHistoryItems = await page.locator(".import-export-admin-history-card").count();
  await screenshot(page, "admin-integracoes-smoke-execution.png");

  await page.locator(".k-tabstrip-items li").nth(3).click();
  await page.locator(".import-export-admin-inline-filters .k-dropdownlist").first().click();
  await page.getByRole("option", { name: "Execucao" }).click();
  await page.getByRole("button", { name: "Aplicar filtro" }).click();
  await page.waitForTimeout(400);
  result.selectedExecutionHasDetail = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    if (!app || !app.state || !app.state.execution) {
      return false;
    }
    const summary = app.state.execution.counts || {};
    return typeof summary.read === "number" && typeof summary.written === "number";
  });
  await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    if (app && app.versionsGrid && app.versionsGrid.tbody && app.versionsGrid.tbody.children().length) {
      const firstRow = app.versionsGrid.tbody.children().first();
      app.versionsGrid.select(firstRow);
      app.handleVersionSelection();
    }
  });
  await page.locator(".k-tabstrip-items li").nth(4).click();
  await page.waitForTimeout(300);
  await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    if (app && app.schedulesGrid && app.schedulesGrid.tbody && app.schedulesGrid.tbody.children().length) {
      const firstRow = app.schedulesGrid.tbody.children().first();
      app.schedulesGrid.select(firstRow);
      app.handleScheduleSelection();
    }
  });
  await page.reload();
  await page.waitForFunction(() => !!window.importExportAdminDemoApp, null, { timeout: 15000 });
  await page.waitForFunction(() => {
    const app = window.importExportAdminDemoApp;
    return !!(app && app.codeInput && String(app.codeInput.value() || "").trim());
  }, null, { timeout: 15000 });
  result.persistedTabIndex = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.tabs ? app.tabs.select().index() : -1;
  });
  result.persistedFilterMode = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.executionFilterModeInput ? app.executionFilterModeInput.value() : "";
  });
  result.persistedCurrentCode = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.codeInput ? String(app.codeInput.value() || "") : "";
  });
  result.persistedVersionNumber = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.state && app.state.selectedVersion ? String(app.state.selectedVersion.versionNumber || "") : "";
  });
  result.persistedScheduleCode = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.state && app.state.selectedSchedule ? String(app.state.selectedSchedule.code || "") : "";
  });
  result.persistedDesignerPath = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.designerState ? String(app.designerState.selectedPath || "") : "";
  });
  await page.locator(".k-tabstrip-items li").nth(1).click();
  await page.locator('button:has-text("Preview")').click();
  await waitForTreeItems(page, ".import-export-admin-preview-splitter .import-export-admin-tree");
  result.persistedPreviewPath = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.previewState ? String(app.previewState.selectedPath || "") : "";
  });

  await page.locator(".k-tabstrip-items li").first().click();
  await page.click(".k-grid-content tr:nth-child(2)");
  await waitForTreeItems(page, ".import-export-admin-tree");
  result.xmlTreeItems = await page.locator(".import-export-admin-tree .k-treeview-item, .import-export-admin-tree .k-item").count();

  await page.locator('button:has-text("Preview")').click();
  await waitForTreeItems(page, ".import-export-admin-preview-splitter .import-export-admin-tree");
  await screenshot(page, "admin-integracoes-smoke-xml-preview.png");

  await fs.writeFile(path.join(outputDir, "admin-integracoes-smoke-result.json"), JSON.stringify(result, null, 2), "utf8");
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
}
