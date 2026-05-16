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
    executionHistoryItems: 0
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

  await page.locator('button:has-text("Preview")').click();
  await waitForTreeItems(page, ".import-export-admin-preview-splitter .import-export-admin-tree");
  result.previewTreeItems = await page.locator(".import-export-admin-preview-splitter .import-export-admin-tree .k-treeview-item, .import-export-admin-preview-splitter .import-export-admin-tree .k-item").count();
  await screenshot(page, "admin-integracoes-smoke-txt-preview.png");

  await page.getByRole("button", { name: "Executar", exact: true }).click();
  await page.waitForSelector(".import-export-admin-history-card", { timeout: 15000 });
  result.executionHistoryItems = await page.locator(".import-export-admin-history-card").count();
  await screenshot(page, "admin-integracoes-smoke-execution.png");

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
