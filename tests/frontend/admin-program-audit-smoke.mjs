import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const auditUrl = "file:///C:/construtor-pg/examples/pages/admin-program-audit.html?programCode=cd1001&builderProgramVersionId=501";

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function clickButtonByText(page, text) {
  await page.evaluate((buttonText) => {
    const button = window.jQuery("button").filter(function() {
      const $button = window.jQuery(this);
      return $button.text().trim() === buttonText && $button.is(":visible") && !$button.prop("disabled");
    }).get(0);
    if (!button) {
      throw new Error("Botao nao encontrado: " + buttonText);
    }
    button.click();
  }, text);
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const errors = [];

  try {
    page.on("pageerror", (error) => {
      errors.push(String(error && error.message || error));
    });
    await page.goto(auditUrl);
    await page.waitForFunction(() => Boolean(window.jQuery(".program-governance-admin-shell").length), null, { timeout: 30000 });

    await page.evaluate(() => {
      window.jQuery(".program-builder-field").each(function() {
        const label = window.jQuery(this).find("label").text().trim();
        const input = window.jQuery(this).find("input").first();
        const textBox = input.data("kendoTextBox");
        const dropDown = input.data("kendoDropDownList");
        if (label === "Usuario" && textBox) {
          textBox.value("analista");
        }
        if (label === "Tipo" && dropDown) {
          dropDown.value("grant");
        }
      });
    });
    await clickButtonByText(page, "Aplicar filtro");
    await page.waitForFunction(() => document.body.innerText.includes("Resumo da auditoria"), null, { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "admin-program-audit-smoke.png"), fullPage: true });

    const resultBeforeReload = await page.evaluate(() => ({
      timelineCount: window.jQuery(".program-governance-admin-timeline-item").length,
      hasSummary: document.body.innerText.includes("Resumo da auditoria"),
      canOpenFocused: window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Abrir tela focada" && window.jQuery(this).is(":visible");
      }).length > 0
    }));

    await page.reload();
    await page.waitForFunction(() => Boolean(window.jQuery(".program-governance-admin-shell").length), null, { timeout: 30000 });
    const resultAfterReload = await page.evaluate(() => {
      let userValue = "";
      let typeValue = "";
      window.jQuery(".program-builder-field").each(function() {
        const label = window.jQuery(this).find("label").text().trim();
        const input = window.jQuery(this).find("input").first();
        const textBox = input.data("kendoTextBox");
        const dropDown = input.data("kendoDropDownList");
        if (label === "Usuario" && textBox) {
          userValue = textBox.value();
        }
        if (label === "Tipo" && dropDown) {
          typeValue = dropDown.value();
        }
      });
      return {
        userValue: userValue,
        typeValue: typeValue,
        hasSummary: document.body.innerText.includes("Resumo da auditoria")
      };
    });

    if (errors.length) {
      throw new Error("Erros JavaScript: " + errors.join(" | "));
    }

    const finalResult = {
      ...resultBeforeReload,
      ...resultAfterReload
    };
    await fs.writeFile(path.join(outputDir, "admin-program-audit-smoke-result.json"), JSON.stringify(finalResult, null, 2), "utf8");
    console.log(JSON.stringify(finalResult, null, 2));
  } finally {
    await page.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
