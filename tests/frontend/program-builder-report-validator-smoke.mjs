import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const baseUrl = "file:///C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });

  try {
    await page.goto(baseUrl + "/examples/pages/program-builder-technical-properties.html");
    await page.waitForFunction(() => Boolean(window.programBuilderDemoApp), null, { timeout: 30000 });

    await page.evaluate(async () => {
      const app = window.programBuilderDemoApp;
      app.pageTypeSelect.value("report");
      app.builderEntitySelect.value("cliente");
      app.handleProgramEntityChange(true);
      app.programCodeInput.value("rp1001");
      app.programTitleInput.value("Relatorio Clientes");
      app.screenIdInput.value("relatorios.clientes-operacional");
      app.versionInput.value("1.0.0");
      app.moduleInput.value("cadastros");
      app.reportDocumentKindSelect.val("management").trigger("change");
      app.reportSortFieldSelect.val("nome").trigger("change");
      await app.requestPreview(true);
      app.activateSideTab(6);
    });

    await page.waitForSelector(".program-builder-report-validator", { timeout: 10000 });
    await page.waitForSelector(".program-builder-report-sample-table", { timeout: 10000 }).catch(() => null);

    await page.evaluate(() => window.programBuilderDemoApp.handleRunReportSample());
    await page.waitForSelector(".program-builder-report-sample-table tbody tr", { timeout: 10000 });

    await page.evaluate(() => window.programBuilderDemoApp.handleExportReportSample("csv"));
    await page.waitForFunction(() => {
      const text = document.querySelector(".program-builder-report-validator");
      return text && text.textContent.includes("arquivo:");
    }, null, { timeout: 10000 });

    await page.evaluate(() => window.programBuilderDemoApp.handleExportReportSample("excel"));
    await page.waitForFunction(() => {
      const text = document.querySelector(".program-builder-report-validator");
      return text && text.textContent.includes(".xlsx");
    }, null, { timeout: 10000 });

    await page.evaluate(async () => {
      const app = window.programBuilderDemoApp;
      app.reportDocumentKindSelect.val("danfe").trigger("change");
      await app.requestPreview(true);
      app.activateSideTab(6);
    });
    await page.waitForFunction(() => {
      const text = document.querySelector(".program-builder-report-validator");
      return text && text.textContent.includes("Documento especial bloqueado");
    }, null, { timeout: 10000 });

    await page.screenshot({ path: path.join(outputDir, "program-builder-report-validator.png"), fullPage: true });

    const result = await page.evaluate(() => ({
      validatorText: document.querySelector(".program-builder-report-validator") && document.querySelector(".program-builder-report-validator").textContent || "",
      sampleRows: document.querySelectorAll(".program-builder-report-sample-table tbody tr").length,
      diagnostics: Array.from(document.querySelectorAll(".program-builder-report-validator .program-builder-diagnostic-item")).map((item) => item.textContent.trim())
    }));

    await fs.writeFile(
      path.join(outputDir, "program-builder-report-validator-result.json"),
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
