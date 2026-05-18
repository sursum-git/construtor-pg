import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const url = "file:///C:/construtor-pg/examples/pages/admin-system-updates.html";

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 980 } });
  const errors = [];

  try {
    page.on("pageerror", (error) => errors.push(String(error && error.message || error)));
    await page.goto(url);
    await page.waitForFunction(() => Boolean(window.jQuery(".system-updates-shell").length), null, { timeout: 30000 });
    await page.waitForFunction(() => document.body.innerText.includes("Versao atual: 1.0.0"), null, { timeout: 10000 });

    const initial = await page.evaluate(() => ({
      hasImpact: document.body.innerText.includes("custom_frozen") || document.body.innerText.includes("rebase_warning"),
      releaseRows: window.jQuery(".k-grid").first().find("tbody tr").length,
      executionRows: window.jQuery(".k-grid").last().find("tbody tr").length
    }));

    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Atualizar manifesto";
      }).get(0);
      if (!button) {
        throw new Error("Botao atualizar manifesto nao encontrado.");
      }
      button.click();
    });
    await page.waitForFunction(() => document.body.innerText.includes("Manifesto atualizado."), null, { timeout: 10000 });

    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Publicar artefatos";
      }).get(0);
      if (!button) {
        throw new Error("Botao publicar artefatos nao encontrado.");
      }
      button.click();
    });
    await page.waitForFunction(() => {
      const detail = window.jQuery(".program-builder-json-preview").text() || "";
      return detail.includes("\"distributionDirectory\"") && detail.includes("\"externalPublication\"");
    }, null, { timeout: 10000 });

    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Aplicar release";
      }).get(0);
      if (!button) {
        throw new Error("Botao aplicar release nao encontrado.");
      }
      button.click();
    });
    await page.waitForFunction(() => document.body.innerText.includes("Atualizacao enfileirada."), null, { timeout: 10000 });
    await page.waitForFunction(() => {
      const executionGrid = window.jQuery(".k-grid").last().data("kendoGrid");
      if (!executionGrid || !executionGrid.dataSource || typeof executionGrid.dataSource.view !== "function") {
        return false;
      }
      const rows = executionGrid.dataSource.view();
      if (!rows || !rows.length) {
        return false;
      }
      return String(rows[0].status || "").toLowerCase() === "succeeded";
    }, null, { timeout: 20000 });

    const result = await page.evaluate(() => ({
      summaryVisible: document.body.innerText.includes("Criticas:"),
      detailText: window.jQuery(".program-builder-json-preview").text(),
      executionRows: window.jQuery(".k-grid").last().find("tbody tr").length
    }));

    await page.evaluate(() => {
      const grid = window.jQuery(".k-grid").first().data("kendoGrid");
      const row = grid.tbody.find("tr").filter(function() {
        return window.jQuery(this).text().includes("1.0.2");
      }).first();
      if (!row.length) {
        throw new Error("Linha da release 1.0.2 nao encontrada.");
      }
      grid.select(row);
      grid.trigger("change");
    });
    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Registrar anuencia";
      }).get(0);
      if (!button) {
        throw new Error("Botao registrar anuencia nao encontrado.");
      }
      button.click();
    });
    await page.waitForSelector(".k-window:has-text('Deseja continuar?')", { timeout: 10000 });
    await page.getByRole("button", { name: "Confirmar", exact: true }).last().click();
    await page.waitForFunction(() => document.body.innerText.includes("Anuencia registrada."), null, { timeout: 10000 });

    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Plano SaaS";
      }).get(0);
      if (!button) {
        throw new Error("Botao plano SaaS nao encontrado.");
      }
      button.click();
    });
    await page.waitForFunction(() => {
      const detail = window.jQuery(".program-builder-json-preview").text() || "";
      return detail.includes("\"orchestratorAction\"") && document.body.innerText.includes("Dependencias obrigatorias:");
    }, null, { timeout: 10000 });

    if (errors.length) {
      throw new Error("Erros JavaScript: " + errors.join(" | "));
    }

    await page.screenshot({ path: path.join(outputDir, "admin-system-updates-smoke.png"), fullPage: true });
    await fs.writeFile(path.join(outputDir, "admin-system-updates-smoke.json"), JSON.stringify({ initial, result }, null, 2), "utf8");
  } finally {
    await page.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
