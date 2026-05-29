import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const reportUrl = "file:///C:/construtor-pg/examples/pages/report-operacional.html";
const authenticityBaseUrl = "file:///C:/construtor-pg/examples/pages/report-authenticity.html";
const outputDir = path.resolve("tmp");

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  try {
    await page.goto(reportUrl);
    await page.waitForFunction(() => document.body.innerText.includes("Codigo de autenticidade"), null, { timeout: 30000 });
    const hash = await page.evaluate(() => {
      const match = document.body.innerText.match(/sha256:[a-f0-9]{64}/i);
      return match ? match[0] : "";
    });
    if (!hash) {
      throw new Error("O relatorio nao exibiu hash de autenticidade.");
    }

    const authenticityPage = await browser.newPage({ viewport: { width: 1440, height: 960 } });
    await authenticityPage.goto(authenticityBaseUrl + "?hash=" + encodeURIComponent(hash));
    await authenticityPage.waitForFunction(() => document.body.innerText.includes("Conferencia valida"), null, { timeout: 30000 });
    const result = await authenticityPage.evaluate(() => ({
      found: document.body.innerText.includes("Conferencia valida"),
      reportTitle: document.body.innerText.includes("Relatorio operacional de clientes"),
      hashVisible: Boolean(document.body.innerText.match(/sha256:[a-f0-9]{64}/i))
    }));
    if (!result.found || !result.reportTitle || !result.hashVisible) {
      throw new Error("A pagina de conferencia nao exibiu os dados esperados.");
    }
    await authenticityPage.screenshot({ path: path.join(outputDir, "report-authenticity-smoke.png"), fullPage: true });
    await fs.writeFile(path.join(outputDir, "report-authenticity-smoke.json"), JSON.stringify({ hash, result }, null, 2), "utf8");
    await authenticityPage.close();
  } finally {
    await page.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
