import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const authenticityBaseUrl = pathToFileURL(path.join(root, "examples", "pages", "regulated-document-authenticity.html")).toString();
const outputDir = path.join(root, "tmp");

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  try {
    await page.goto(authenticityBaseUrl);
    await page.waitForFunction(() => Boolean(window.DemoMockHttpClient), null, { timeout: 30000 });
    const hash = await page.evaluate(async () => {
      const client = new window.DemoMockHttpClient();
      const issued = await client.issueRegulatedDocument("documentos.regulados-fiscal-base", {
        parameters: { status: "ATIVO" },
        format: "pdf"
      });
      return issued && issued.hash || "";
    });
    if (!hash) {
      throw new Error("O documento regulado nao gerou hash de autenticidade.");
    }

    await page.goto(authenticityBaseUrl + "?hash=" + encodeURIComponent(hash));
    await page.waitForFunction(() => document.body.innerText.includes("Conferencia valida"), null, { timeout: 30000 });
    const result = await page.evaluate(() => ({
      valid: document.body.innerText.includes("Conferencia valida"),
      found: document.body.innerText.includes("Documento regulado localizado."),
      track: document.body.innerText.includes("fiscal"),
      hashVisible: Boolean(document.body.innerText.match(/sha256:[a-f0-9]{64}/i))
    }));
    if (!result.valid || !result.found || !result.track || !result.hashVisible) {
      throw new Error("A pagina de conferencia do documento regulado nao exibiu os dados esperados.");
    }
    await page.screenshot({ path: path.join(outputDir, "regulated-document-authenticity-smoke.png"), fullPage: true });
    await fs.writeFile(path.join(outputDir, "regulated-document-authenticity-smoke.json"), JSON.stringify({ hash, result }, null, 2), "utf8");
  } finally {
    await page.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
