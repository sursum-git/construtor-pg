import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const pageUrl = "file:///C:/construtor-pg/examples/pages/admin-integridade.html";
const outputDir = path.join(repoRoot, "tmp");
const adminStorageKey = "crud-demo-admin-runtime-v1-examples-admin-integridade";

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function screenshot(page, fileName) {
  await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function openFirstRecord(page) {
  const rowActionToggle = page.locator("#example-render-root .crud-row-actions-toggle");
  if (await rowActionToggle.count()) {
    await rowActionToggle.first().click();
    await page.waitForSelector(".crud-row-actions-popup .crud-row-action[data-action='view']", { timeout: 15000 });
    await page.locator(".crud-row-actions-popup .crud-row-action[data-action='view']").first().click();
    return;
  }
  await page.locator("#example-render-root .k-grid-content tr").first().dblclick();
}

const browser = await chromium.launch({ headless: true });
const pageErrors = [];

try {
  await ensureOutputDir();
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  page.on("pageerror", (error) => {
    pageErrors.push(String(error && error.message || error));
  });

  await page.goto(pageUrl);
  await page.waitForSelector("#example-render-root .k-grid-content tr", { timeout: 15000 });

  await openFirstRecord(page);

  await page.waitForSelector(".k-window .crud-other-actions-button", { timeout: 15000 });
  await screenshot(page, "admin-integridade-smoke-form.png");

  await page.locator(".k-window .crud-other-actions-button").click();
  await page.waitForSelector(".crud-other-actions-menu .crud-other-action", { timeout: 15000 });
  await page.locator(".crud-other-actions-menu .crud-other-action").getByText("Reassinar", { exact: true }).click();

  await page.waitForSelector(".k-window:has-text('Deseja continuar?')", { timeout: 15000 });
  await screenshot(page, "admin-integridade-smoke-confirm.png");

  await page.getByRole("button", { name: "Confirmar", exact: true }).last().click();
  await page.waitForTimeout(600);
  await page.waitForFunction(() => {
    const state = JSON.parse(localStorage.getItem("crud-demo-admin-runtime-v1-examples-admin-integridade") || "{}");
    const rows = Array.isArray(state["admin.integridade"]) ? state["admin.integridade"] : [];
    return rows.some((row) => Number(row.id) === 1 && String(row.last_check_status) === "valid" && !String(row.last_error_message || ""));
  }, null, { timeout: 15000 });

  const result = await page.evaluate((storageKey) => {
    const state = JSON.parse(localStorage.getItem(storageKey) || "{}");
    const rows = Array.isArray(state["admin.integridade"]) ? state["admin.integridade"] : [];
    const row = rows.find((item) => Number(item.id) === 1) || null;
    return {
      status: row ? row.last_check_status : null,
      signedBy: row ? row.signed_by : null,
      lastError: row ? row.last_error_message : null,
      metadataSource: row ? JSON.parse(String(row.metadata || "{}")).source || null : null
    };
  }, adminStorageKey);

  if (pageErrors.length) {
    throw new Error("Erros JavaScript detectados: " + pageErrors.join(" | "));
  }

  await screenshot(page, "admin-integridade-smoke-result.png");
  await fs.writeFile(path.join(outputDir, "admin-integridade-smoke-result.json"), JSON.stringify(result, null, 2), "utf8");
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
}
