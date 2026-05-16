import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const baseUrl = "file:///C:/construtor-pg";
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

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });

  try {
    await page.goto(baseUrl + "/examples/pages/program-builder-technical-properties.html");
    await page.waitForFunction(() => Boolean(window.programBuilderDemoApp), null, { timeout: 30000 });
    await page.waitForFunction(() => document.querySelectorAll('[data-crud-role="program-builder-technical-info"]').length > 0, null, { timeout: 30000 });

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
      inspectorTriggers
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
