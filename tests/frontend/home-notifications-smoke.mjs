import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const pageUrl = "file:///C:/construtor-pg/home.html";
const outputDir = path.join(repoRoot, "tmp");

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

const browser = await chromium.launch({ headless: true });
try {
  await ensureOutputDir();
  const page = await browser.newPage({ viewport: { width: 1440, height: 980 } });
  const result = {
    categoryValue: "",
    severityValue: "",
    filteredItems: 0,
    persistedCategory: "",
    persistedModuleId: "",
    persistedSearchText: "",
    persistedFavoritesOnly: false,
    persistedProgramId: "",
    persistedSidebarCollapsed: false,
    persistedSubscriberName: "",
    persistedNotificationsWindow: false
  };

  await page.goto(pageUrl);
  await page.waitForFunction(() => !!window.homeDemoEngine, null, { timeout: 20000 });
  await page.getByRole("button", { name: "Abrir central de notificacoes" }).click();
  await page.waitForSelector(".home-appbar-list-window-content", { timeout: 15000 });
  await page.evaluate(async () => {
    const engine = window.homeDemoEngine;
    engine.notificationListFilters.severity = "info";
    engine.notificationListFilters.category = "Sistema";
    engine.saveNotificationFilterState();
    const targetModule = (engine.modules || []).find((item) => item && !item.isAll);
    engine.currentModuleId = targetModule ? String(targetModule.id || "") : "";
    engine.menuSearchText = "cad";
    engine.showOnlyFavorites = true;
    engine.applySubscriberChange({ id: "empresa-b", name: "Empresa B" });
    const fallbackProgram = Array.isArray(engine.definition && engine.definition.programs) ? engine.definition.programs[1] || engine.definition.programs[0] : null;
    if (fallbackProgram && fallbackProgram.id) {
      await engine.openProgram(fallbackProgram.id, { skipUnsavedCheck: true, syncModule: false });
    }
    if (engine.shell) {
      engine.shell.addClass("home-sidebar-collapsed");
    }
    engine.saveNavigationState();
  });
  await page.reload();
  await page.waitForFunction(() => !!window.homeDemoEngine, null, { timeout: 20000 });
  await page.waitForSelector(".home-appbar-list-window-content", { timeout: 15000 });
  await page.waitForTimeout(500);
  result.categoryValue = await page.evaluate(() => window.homeDemoEngine.notificationListFilters.category || "");
  result.severityValue = await page.evaluate(() => window.homeDemoEngine.notificationListFilters.severity || "");
  result.filteredItems = await page.locator(".home-appbar-list-item").count();
  result.persistedModuleId = await page.evaluate(() => window.homeDemoEngine.currentModuleId || "");
  result.persistedSearchText = await page.evaluate(() => window.homeDemoEngine.menuSearchText || "");
  result.persistedFavoritesOnly = await page.evaluate(() => window.homeDemoEngine.showOnlyFavorites === true);
  result.persistedProgramId = await page.evaluate(() => window.homeDemoEngine.currentProgram && window.homeDemoEngine.currentProgram.id || "");
  result.persistedSidebarCollapsed = await page.evaluate(() => window.homeDemoEngine.shell && window.homeDemoEngine.shell.hasClass("home-sidebar-collapsed"));
  result.persistedSubscriberName = await page.evaluate(() => {
    const subscriber = window.homeDemoEngine.getCurrentSubscriber();
    return subscriber && (subscriber.name || subscriber.displayName || subscriber.id) || "";
  });
  result.persistedNotificationsWindow = await page.locator(".home-appbar-list-window-content").count().then((count) => count > 0);
  await page.screenshot({ path: path.join(outputDir, "home-notifications-smoke.png"), fullPage: true });
  result.persistedCategory = result.categoryValue;

  await fs.writeFile(path.join(outputDir, "home-notifications-smoke-result.json"), JSON.stringify(result, null, 2), "utf8");
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
}
