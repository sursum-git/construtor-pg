import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const url = "file:///C:/construtor-pg/examples/pages/admin-subscriber-provisioning.html";

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 980 }, acceptDownloads: true });
  const page = await context.newPage();
  const errors = [];

  try {
    page.on("pageerror", (error) => errors.push(String(error && error.message || error)));
    await page.goto(url);
    await page.waitForFunction(() => Boolean(window.jQuery(".subscriber-provisioning-shell").length), null, { timeout: 30000 });

    await page.evaluate(() => {
      const fields = window.jQuery(".program-builder-field");
      fields.each(function() {
        const label = window.jQuery(this).find("span").first().text().trim();
        const input = window.jQuery(this).find("input");
        if (!input.length) {
          return;
        }
        const widget = input.data("kendoTextBox");
        const setValue = (value) => widget ? widget.value(value) : input.val(value);
        if (label === "Codigo") setValue("cliente-smoke");
        if (label === "Nome") setValue("Cliente Smoke");
        if (label === "Ambiente do banco") setValue("prod");
        if (label === "Identidade do banco") setValue("saas:cliente-smoke");
        if (label === "Nome do banco") setValue("construtor_pg_cliente_smoke");
        if (label === "Usuario admin") setValue("admin");
        if (label === "Nome do admin") setValue("Administrador Smoke");
        if (label === "Senha do admin") setValue("Senha@123");
      });
    });

    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() { return window.jQuery(this).text().trim() === "Salvar assinante"; }).get(0);
      if (!button) throw new Error("Botao salvar nao encontrado.");
      button.click();
    });
    await page.waitForFunction(() => document.body.innerText.includes("Assinante salvo."), null, { timeout: 10000 });

    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() { return window.jQuery(this).text().trim() === "Criar ambiente"; }).get(0);
      if (!button) throw new Error("Botao provisionar nao encontrado.");
      button.click();
    });
    await page.waitForFunction(() => document.body.innerText.includes("Provisionamento enfileirado."), null, { timeout: 10000 });
    await page.waitForFunction(() => document.body.innerText.includes("\"status\": \"succeeded\""), null, { timeout: 15000 });

    const downloadPromise = page.waitForEvent("download");
    await page.evaluate(() => {
      const button = window.jQuery("button").filter(function() { return window.jQuery(this).text().trim() === "Baixar pacote on-premise"; }).get(0);
      if (!button) throw new Error("Botao de pacote nao encontrado.");
      button.click();
    });
    const download = await downloadPromise;

    const result = await page.evaluate(() => {
      const detail = window.jQuery(".program-builder-json-preview").text();
      return {
        shell: window.jQuery(".subscriber-provisioning-shell").length,
        subscriberRows: window.jQuery(".k-grid tbody tr").length,
        detailText: detail
      };
    });

    if (errors.length) {
      throw new Error("Erros JavaScript: " + errors.join(" | "));
    }
    if (!/construtor-pg-onprem-cliente-smoke\.zip$/i.test(download.suggestedFilename())) {
      throw new Error("Nome do pacote on-premise inesperado: " + download.suggestedFilename());
    }

    await page.screenshot({ path: path.join(outputDir, "admin-subscriber-provisioning-smoke.png"), fullPage: true });
    await fs.writeFile(path.join(outputDir, "admin-subscriber-provisioning-smoke.json"), JSON.stringify(result, null, 2), "utf8");
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
