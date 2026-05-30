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
      app.pageTypeSelect.value("analytics");
      app.builderEntitySelect.value("cliente");
      app.handleProgramEntityChange(true);
      app.programCodeInput.value("bi1001");
      app.programTitleInput.value("BI Clientes");
      app.screenIdInput.value("analytics.clientes");
      app.versionInput.value("1.0.0");
      app.moduleInput.value("cadastros");
      app.handleAddAnalyticsPipelineRow();
      await app.requestPreview(true);
      app.activateSideTab(6);
    });

    await page.waitForSelector(".program-builder-analytics-validator", { timeout: 10000 });
    await page.waitForSelector(".program-builder-analytics-dataset-card", { timeout: 10000 });

    await page.getByRole("button", { name: "Executar amostra" }).click();
    await page.waitForSelector(".program-builder-analytics-sample-table", { timeout: 10000 });

    await page.getByRole("button", { name: "Preview pipeline" }).click();
    await page.waitForFunction(() => {
      const blocks = Array.from(document.querySelectorAll(".program-builder-json-preview"));
      return blocks.some((item) => item.textContent.includes("\"workingDataset\"") || item.textContent.includes("\"rows\""));
    }, null, { timeout: 10000 });

    await page.getByRole("button", { name: "Executar pipeline" }).click();
    await page.getByRole("button", { name: "Versoes" }).click();
    await page.waitForFunction(() => {
      const text = document.body.textContent || "";
      return text.includes("Dataset publicado e comparacao");
    }, null, { timeout: 10000 });

    await page.waitForFunction(() => {
      const text = document.body.textContent || "";
      return text.includes("Impacto antes do publish");
    }, null, { timeout: 10000 });

    await page.getByRole("button", { name: "Status do cache" }).click();
    await page.waitForFunction(() => {
      const blocks = Array.from(document.querySelectorAll(".program-builder-analytics-runtime-meta"));
      return blocks.some((item) => item.textContent.includes("Status: ready"));
    }, null, { timeout: 10000 });

    await page.getByRole("button", { name: "Materializar cache" }).click();
    await page.waitForFunction(() => {
      const text = document.querySelector(".program-builder-analytics-validator");
      return text && text.textContent.includes("Cache analytics materializado");
    }, null, { timeout: 3000 }).catch(() => null);

    await page.evaluate(() => {
      const field = Array.from(document.querySelectorAll(".program-builder-analytics-parameters-grid .program-builder-field")).find((item) => item.textContent.includes("UF"));
      const input = field && field.querySelector("input, select");
      if (!input) {
        throw new Error("Campo UF nao encontrado no validador analytics.");
      }
      input.value = "SP";
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
    });
    await page.getByRole("button", { name: "Executar amostra" }).click();
    await page.waitForFunction(() => {
      const cells = Array.from(document.querySelectorAll(".program-builder-analytics-sample-table tbody td"));
      return cells.some((item) => item.textContent.includes("SP")) && !cells.some((item) => item.textContent.includes("CE")) && !cells.some((item) => item.textContent.includes("RJ"));
    }, null, { timeout: 10000 });

    await page.screenshot({ path: path.join(outputDir, "program-builder-analytics-validator.png"), fullPage: true });

    const result = await page.evaluate(() => {
      return {
        datasetCards: document.querySelectorAll(".program-builder-analytics-dataset-card").length,
        runtimeBlocks: document.querySelectorAll(".program-builder-analytics-runtime-block").length,
        joinRows: document.querySelectorAll(".program-builder-analytics-joins-table tbody tr").length,
        diagnosticsText: document.querySelector(".program-builder-analytics-validator") && document.querySelector(".program-builder-analytics-validator").textContent || ""
      };
    });

    await fs.writeFile(
      path.join(outputDir, "program-builder-analytics-validator-result.json"),
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
