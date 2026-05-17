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
    persistedCategory: ""
  };

  await page.goto(pageUrl);
  await page.waitForFunction(() => !!window.homeDemoEngine, null, { timeout: 20000 });
  await page.getByRole("button", { name: "Abrir central de notificacoes" }).click();
  await page.waitForSelector(".home-appbar-list-window-content", { timeout: 15000 });
  await page.evaluate(() => {
    const engine = window.homeDemoEngine;
    engine.notificationListFilters.severity = "info";
    engine.notificationListFilters.category = "Sistema";
    engine.saveNotificationFilterState();
  });
  await page.reload();
  await page.waitForFunction(() => !!window.homeDemoEngine, null, { timeout: 20000 });
  await page.getByRole("button", { name: "Abrir central de notificacoes" }).click();
  await page.waitForSelector(".home-appbar-list-window-content", { timeout: 15000 });
  await page.waitForTimeout(500);
  result.categoryValue = await page.evaluate(() => window.homeDemoEngine.notificationListFilters.category || "");
  result.severityValue = await page.evaluate(() => window.homeDemoEngine.notificationListFilters.severity || "");
  result.filteredItems = await page.locator(".home-appbar-list-item").count();
  await page.screenshot({ path: path.join(outputDir, "home-notifications-smoke.png"), fullPage: true });
  result.persistedCategory = result.categoryValue;

  await fs.writeFile(path.join(outputDir, "home-notifications-smoke-result.json"), JSON.stringify(result, null, 2), "utf8");
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
}
