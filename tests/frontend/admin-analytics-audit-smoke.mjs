import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const url = "file:///C:/construtor-pg/examples/pages/admin-analytics-audit.html";

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const errors = [];

  try {
    page.on("pageerror", (error) => errors.push(String(error && error.message || error)));
    await page.goto(url);
    await page.waitForFunction(() => Boolean(window.jQuery(".program-governance-admin-shell").length), null, { timeout: 30000 });
    await page.waitForFunction(() => window.jQuery(".k-grid tbody tr").length >= 1, null, { timeout: 15000 });

    const result = await page.evaluate(() => {
      return {
        shell: window.jQuery(".program-governance-admin-shell").length,
        rows: window.jQuery(".k-grid tbody tr").length,
        bodyText: document.body.innerText
      };
    });

    if (errors.length) {
      throw new Error("Erros JavaScript: " + errors.join(" | "));
    }
    if (!result.shell) {
      throw new Error("Shell da tela administrativa nao carregou.");
    }
    if (result.rows < 1) {
      throw new Error("Grid da auditoria analytics nao carregou linhas.");
    }
    if (!String(result.bodyText || "").includes("Auditoria analytics")) {
      throw new Error("Titulo principal da auditoria analytics nao apareceu.");
    }

    await page.screenshot({ path: path.join(outputDir, "admin-analytics-audit-smoke.png"), fullPage: true });
    await fs.writeFile(path.join(outputDir, "admin-analytics-audit-smoke.json"), JSON.stringify(result, null, 2), "utf8");
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
