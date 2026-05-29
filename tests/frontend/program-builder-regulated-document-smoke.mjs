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
      app.pageTypeSelect.value("regulated_document");
      app.builderEntitySelect.value("cliente");
      app.handleProgramEntityChange(true);
      app.programCodeInput.value("rd3001");
      app.programTitleInput.value("Documento regulado de teste");
      app.screenIdInput.value("documentos.regulados-teste");
      app.versionInput.value("1.0.0");
      app.moduleInput.value("documentos");
      app.regulatedDocumentTrackSelect.val("banking").trigger("change");
      app.regulatedDocumentTypeInput.val("banking_base");
      app.regulatedDocumentSourceTypeSelect.val("analytic").trigger("change");
      app.regulatedDocumentAnalyticsScreenIdInput.val("analytics.clientes");
      app.regulatedDocumentAnalyticsDatasetIdInput.val("clientes-uf-status");
      app.regulatedDocumentTitleInput.val("Documento regulado banking");
      app.regulatedDocumentSubtitleInput.val("Preview local");
      app.regulatedDocumentVerificationPathInput.val("regulated-document-authenticity.html");
      await app.requestPreview(true);
    });

    await page.waitForFunction(() => {
      const app = window.programBuilderDemoApp;
      return app && app.state && app.state.preview && app.state.preview.generatedDefinition
        && app.state.preview.generatedDefinition.pageType === "regulated_document";
    }, null, { timeout: 15000 });

    const result = await page.evaluate(() => {
      const preview = window.programBuilderDemoApp.state.preview.generatedDefinition;
      return {
        pageType: preview && preview.pageType || "",
        track: preview && preview.regulatedDocument && preview.regulatedDocument.track || "",
        sourceType: preview && preview.regulatedDocument && preview.regulatedDocument.source && preview.regulatedDocument.source.type || "",
        analyticsScreenId: preview && preview.regulatedDocument && preview.regulatedDocument.source && preview.regulatedDocument.source.analyticsScreenId || "",
        title: preview && preview.program && preview.program.title || ""
      };
    });

    await page.screenshot({ path: path.join(outputDir, "program-builder-regulated-document.png"), fullPage: true });
    await fs.writeFile(
      path.join(outputDir, "program-builder-regulated-document-result.json"),
      JSON.stringify(result, null, 2),
      "utf8"
    );

    if (result.pageType !== "regulated_document" || result.track !== "banking" || result.sourceType !== "analytic" || result.analyticsScreenId !== "analytics.clientes") {
      throw new Error("O builder nao gerou o preview esperado para documento regulado.");
    }
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
