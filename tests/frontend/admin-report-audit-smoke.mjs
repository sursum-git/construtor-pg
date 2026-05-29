import { chromium } from "playwright";
import path from "node:path";
import fs from "node:fs/promises";

const repoRoot = "C:/construtor-pg";
const pageUrl = "file:///C:/construtor-pg/examples/pages/admin-report-audit.html";
const outputDir = path.join(repoRoot, "tmp");

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  try {
    await page.goto(pageUrl);
    await page.waitForSelector(".k-grid-content tbody tr", { timeout: 15000 });

    const initialRows = await page.locator(".k-grid-content tbody tr").count();
    if (initialRows < 1) {
      throw new Error("A auditoria de relatorios nao renderizou linhas.");
    }

    await page.getByRole("button", { name: "Aplicar filtros" }).click();
    await page.waitForTimeout(500);

    const detailText = await page.locator(".program-builder-json-preview").first().textContent();
    if (!detailText || !detailText.includes("reportId")) {
      throw new Error("O detalhe da auditoria de relatorios nao mostrou o payload esperado.");
    }

    await page.screenshot({ path: path.join(outputDir, "admin-report-audit.png"), fullPage: true });
    console.log(JSON.stringify({ initialRows, detailPreview: detailText.slice(0, 200) }, null, 2));
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
