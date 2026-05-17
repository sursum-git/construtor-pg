import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function writeResult(fileName, payload) {
  await fs.writeFile(path.join(outputDir, fileName), JSON.stringify(payload, null, 2), "utf8");
}

const browser = await chromium.launch({ headless: true });
try {
  await ensureOutputDir();
  const page = await browser.newPage({ viewport: { width: 1440, height: 980 } });
  const result = {
    rememberedUserAfterLogin: "",
    subscriberAfterLogin: "",
    homeProgramAfterRestore: "",
    homeSidebarCollapsed: false,
    homeNotificationsReopened: false,
    sessionRevokedClearedHomeState: false,
    sessionRevokedPreservedUsername: "",
    importExportTabAfterReload: -1,
    importExportMappingAfterReload: "",
    importExportDesignerPathAfterReload: "",
    importExportPreviewPathAfterReload: "",
    finalLocalSessionCleared: false
  };

  await page.goto("file:///C:/construtor-pg/login.html");
  await page.waitForSelector("#login-user", { timeout: 15000 });
  await page.evaluate(() => localStorage.clear());
  await page.locator("#login-user").fill("analista.demo");
  await page.locator("#login-password").fill("123456");
  await page.getByRole("button", { name: "Entrar" }).click();
  await page.getByRole("button", { name: "Continuar" }).click();
  await page.getByRole("button", { name: "Area principal" }).click();
  await page.waitForURL(/home\.html/, { timeout: 15000 });
  await page.waitForFunction(() => !!window.homeDemoEngine, null, { timeout: 20000 });

  result.rememberedUserAfterLogin = await page.evaluate(() => localStorage.getItem("crudEngine.lastUsername") || "");
  result.subscriberAfterLogin = await page.evaluate(() => {
    const raw = localStorage.getItem("crudEngine.currentSubscriber") || "{}";
    try {
      const parsed = JSON.parse(raw);
      return parsed && (parsed.name || parsed.id) || "";
    } catch (_) {
      return "";
    }
  });

  await page.getByRole("button", { name: "Abrir central de notificacoes" }).click();
  await page.waitForSelector(".home-appbar-list-window-content", { timeout: 15000 });
  await page.evaluate(async () => {
    const engine = window.homeDemoEngine;
    engine.notificationListFilters.category = "Sistema";
    engine.notificationListFilters.severity = "info";
    engine.saveNotificationFilterState();
    const targetModule = (engine.modules || []).find((item) => item && !item.isAll);
    engine.currentModuleId = targetModule ? String(targetModule.id || "") : "";
    engine.menuSearchText = "cad";
    engine.showOnlyFavorites = true;
    const targetProgram = Array.isArray(engine.definition && engine.definition.programs)
      ? engine.definition.programs[1] || engine.definition.programs[0]
      : null;
    if (targetProgram && targetProgram.id) {
      await engine.openProgram(targetProgram.id, { skipUnsavedCheck: true, syncModule: false });
    }
    if (engine.shell) {
      engine.shell.addClass("home-sidebar-collapsed");
    }
    engine.savedAppbarPanelKind = "notifications";
    engine.saveNavigationState();
  });
  await page.reload();
  await page.waitForFunction(() => !!window.homeDemoEngine, null, { timeout: 20000 });
  await page.waitForTimeout(1000);
  result.homeProgramAfterRestore = await page.evaluate(() => window.homeDemoEngine.currentProgram && window.homeDemoEngine.currentProgram.id || "");
  result.homeSidebarCollapsed = await page.evaluate(() => window.homeDemoEngine.shell && window.homeDemoEngine.shell.hasClass("home-sidebar-collapsed"));
  result.homeNotificationsReopened = await page.locator(".home-appbar-list-window-content").count().then((count) => count > 0);

  await page.evaluate(() => {
    window.homeDemoEngine.handleSessionRevoked("Sessao encerrada para validacao.", { reason: "force-logout-test" });
  });
  await page.waitForTimeout(200);
  result.sessionRevokedClearedHomeState = await page.evaluate(() => {
    return !localStorage.getItem("homeEngine.home.navigationState") &&
      !localStorage.getItem("crudEngine.currentSubscriber") &&
      !localStorage.getItem("crudEngine.runtimeSessionId");
  });
  result.sessionRevokedPreservedUsername = await page.evaluate(() => localStorage.getItem("crudEngine.lastUsername") || "");

  await page.goto("file:///C:/construtor-pg/examples/pages/import-export-admin-demo.html");
  await page.waitForFunction(() => !!window.importExportAdminDemoApp, null, { timeout: 15000 });
  await page.evaluate(() => {
    if (window.CrudUtils) {
      window.CrudUtils.confirmAction = () => Promise.resolve(true);
    }
  });
  await page.click(".k-grid-content tr:first-child");
  await page.waitForFunction(() => {
    const host = document.querySelector(".import-export-admin-tree");
    return !!(host && host.querySelector(".k-treeview-item, .k-item"));
  }, null, { timeout: 15000 });
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
  await page.waitForFunction(() => {
    const host = document.querySelector(".import-export-admin-preview-splitter .import-export-admin-tree");
    return !!(host && host.querySelector(".k-treeview-item, .k-item"));
  }, null, { timeout: 15000 });
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
  await page.locator(".k-tabstrip-items li").nth(3).click();
  await page.locator(".import-export-admin-inline-filters .k-dropdownlist").first().click();
  await page.getByRole("option", { name: "Execucao" }).click();
  await page.getByRole("button", { name: "Aplicar filtro" }).click();
  await page.waitForTimeout(400);
  await page.reload();
  await page.waitForFunction(() => !!window.importExportAdminDemoApp, null, { timeout: 15000 });
  await page.waitForFunction(() => {
    const app = window.importExportAdminDemoApp;
    return !!(app && app.codeInput && String(app.codeInput.value() || "").trim());
  }, null, { timeout: 15000 });
  result.importExportTabAfterReload = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.tabs ? app.tabs.select().index() : -1;
  });
  result.importExportMappingAfterReload = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.codeInput ? String(app.codeInput.value() || "") : "";
  });
  result.importExportDesignerPathAfterReload = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.designerState ? String(app.designerState.selectedPath || "") : "";
  });
  await page.locator(".k-tabstrip-items li").nth(1).click();
  await page.locator('button:has-text("Preview")').click();
  await page.waitForFunction(() => {
    const host = document.querySelector(".import-export-admin-preview-splitter .import-export-admin-tree");
    return !!(host && host.querySelector(".k-treeview-item, .k-item"));
  }, null, { timeout: 15000 });
  result.importExportPreviewPathAfterReload = await page.evaluate(() => {
    const app = window.importExportAdminDemoApp;
    return app && app.previewState ? String(app.previewState.selectedPath || "") : "";
  });

  await page.goto("file:///C:/construtor-pg/login.html");
  await page.waitForSelector("#login-clear-session", { timeout: 15000 });
  await page.getByRole("button", { name: "Limpar sessao local" }).click();
  await page.waitForTimeout(200);
  result.finalLocalSessionCleared = await page.evaluate(() => {
    return !localStorage.getItem("crudEngine.currentSubscriber") &&
      !localStorage.getItem("crudEngine.accessArea") &&
      !localStorage.getItem("crudEngine.lastUsername") &&
      !localStorage.getItem("homeEngine.home.navigationState") &&
      !localStorage.getItem("importExportAdmin.import-export-admin.state");
  });

  await page.screenshot({ path: path.join(outputDir, "context-resume-full-e2e.png"), fullPage: true });
  await writeResult("context-resume-full-e2e-result.json", result);
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
}
