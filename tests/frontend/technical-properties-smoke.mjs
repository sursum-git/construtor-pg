import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const baseUrl = "file:///C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function screenshot(page, fileName) {
  await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function runDesktopCrud(browser, result) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 } });
  const page = await context.newPage();
  await page.goto(baseUrl + "/index.html");
  await page.waitForSelector('th[data-field="nome"] [data-crud-role="grid-technical-info"]', { timeout: 30000 });
  await page.locator('th[data-field="nome"] [data-crud-role="grid-technical-info"]').click();
  await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
  await page.locator(".crud-technical-copy-all-button").click();
  await screenshot(page, "technical-properties-smoke-grid.png");
  await page.keyboard.press("Escape");

  await page.locator("#crud-action-filters").click();
  await page.waitForSelector('.crud-filter-window [data-crud-role="filter-technical-info"]', { timeout: 10000 });
  await page.locator('.crud-filter-window [data-crud-role="filter-technical-info"]').first().click();
  await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
  await page.getByRole("textbox", { name: "Filtrar propriedades" }).fill("Banco");
  await screenshot(page, "technical-properties-smoke-filter.png");
  await page.keyboard.press("Escape");
  await page.locator('.crud-filter-window button:has-text("Fechar")').click();

  await page.waitForSelector(".k-grid tbody tr", { timeout: 10000 });
  await page.locator(".k-grid tbody tr").first().dblclick();
  await page.waitForSelector('.crud-form [data-crud-role="form-technical-info"]', { timeout: 10000 });
  const formTriggers = await page.locator('.crud-form [data-crud-role="form-technical-info"]').count();
  await page.locator('.crud-form [data-crud-role="form-technical-info"]').first().click();
  await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
  await screenshot(page, "technical-properties-smoke-form.png");
  await page.keyboard.press("Escape");
  await page.keyboard.press("Escape");

  result.desktopCrud = {
    gridTriggers: await page.locator('[data-crud-role="grid-technical-info"]').count(),
    filterTriggers: await page.locator('[data-crud-role="filter-technical-info"]').count(),
    formTriggers
  };
  await context.close();
}

async function runMobileCrud(browser, result) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto(baseUrl + "/examples/pages/grid-mobile-template.html");
  await page.waitForSelector('[data-crud-role="mobile-technical-info"]', { timeout: 30000 });
  await page.locator('[data-crud-role="mobile-technical-info"]').first().click();
  await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
  await screenshot(page, "technical-properties-smoke-mobile.png");
  result.mobileCrud = {
    triggers: await page.locator('[data-crud-role="mobile-technical-info"]').count()
  };
  await context.close();
}

async function runProcess(browser, result) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  await page.goto(baseUrl + "/examples/pages/processamento-parametros.html");
  await page.waitForSelector('[data-crud-role="process-technical-info"]', { timeout: 30000 });
  await page.locator('[data-crud-role="process-technical-info"]').first().click();
  await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
  await screenshot(page, "technical-properties-smoke-process.png");
  result.process = {
    triggers: await page.locator('[data-crud-role="process-technical-info"]').count()
  };
  await context.close();
}

async function runHome(browser, result) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 } });
  const page = await context.newPage();
  await page.goto(baseUrl + "/home.html");
  await page.getByRole("button", { name: "Abrir central de notificacoes" }).click();
  await page.waitForSelector('.home-appbar-list-window [data-crud-role="home-list-technical-info"]', { timeout: 30000 });
  await page.locator('.home-appbar-list-window [data-crud-role="home-list-technical-info"]').first().click();
  await page.waitForSelector(".crud-technical-info-window", { timeout: 10000 });
  await screenshot(page, "technical-properties-smoke-home.png");
  result.home = {
    triggers: await page.locator('.home-appbar-list-window [data-crud-role="home-list-technical-info"]').count()
  };
  await context.close();
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const result = {};

  try {
    await runDesktopCrud(browser, result);
    await runMobileCrud(browser, result);
    await runProcess(browser, result);
    await runHome(browser, result);
  } finally {
    await browser.close();
  }

  await fs.writeFile(
    path.join(outputDir, "technical-properties-smoke-result.json"),
    JSON.stringify(result, null, 2),
    "utf8"
  );

  console.log(JSON.stringify(result, null, 2));
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
