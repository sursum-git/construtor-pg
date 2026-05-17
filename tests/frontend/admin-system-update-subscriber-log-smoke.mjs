import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const url = "file:///C:/construtor-pg/examples/pages/admin-system-update-subscriber-log.html";

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 980 } });
  const errors = [];

  try {
    page.on("pageerror", (error) => errors.push(String(error && error.message || error)));
    await page.goto(url);
    await page.waitForFunction(() => Boolean(window.jQuery(".system-updates-shell").length), null, { timeout: 30000 });
    await page.waitForFunction(() => document.body.innerText.includes("Atualizacoes por assinante"), null, { timeout: 10000 });

    await page.evaluate(() => {
      const dropDown = window.jQuery("input").first().data("kendoDropDownList");
      if (!dropDown) {
        throw new Error("DropDown de assinantes nao encontrado.");
      }
      dropDown.value("empresa-a");
      dropDown.trigger("change");
    });

    await page.waitForFunction(() => document.body.innerText.includes("Consultando historico de empresa-a - Empresa A."), null, { timeout: 10000 });
    await page.waitForFunction(() => {
      const grid = window.jQuery(".k-grid").first().data("kendoGrid");
      if (!grid || !grid.dataSource) {
        return false;
      }
      const rows = grid.dataSource.view();
      return rows && rows.length > 0 && String(rows[0].targetSubscriberCode || "") === "empresa-a";
    }, null, { timeout: 10000 });

    await page.locator(".k-grid tbody tr").first().click();
    await page.waitForFunction(() => {
      const detail = window.jQuery(".program-builder-json-preview").text() || "";
      return detail.includes("\"targetSubscriberCode\": \"empresa-a\"");
    }, null, { timeout: 10000 });

    if (errors.length) {
      throw new Error("Erros JavaScript: " + errors.join(" | "));
    }

    await page.screenshot({ path: path.join(outputDir, "admin-system-update-subscriber-log-smoke.png"), fullPage: true });
  } finally {
    await page.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
