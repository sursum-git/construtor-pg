import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const url = "file:///C:/construtor-pg/examples/pages/admin-program-governance.html?programCode=cd1001&builderProgramVersionId=501&tab=grants";
const grantsUrl = "file:///C:/construtor-pg/examples/pages/admin-program-grants.html?programCode=cd1001&builderProgramVersionId=501";
const approvalsUrl = "file:///C:/construtor-pg/examples/pages/admin-program-approvals.html?programCode=cd1001&builderProgramVersionId=501";
const retentionUrl = "file:///C:/construtor-pg/examples/pages/admin-program-retention.html?programCode=cd1001&builderProgramVersionId=501";

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

async function inspectFocusedMode(browser, targetUrl, expectedTitle) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const errors = [];
  try {
    page.on("pageerror", (error) => {
      errors.push(String(error && error.message || error));
    });
    await page.goto(targetUrl);
    await page.waitForFunction(() => {
      return Boolean(window.jQuery(".program-governance-admin-shell").length);
    }, null, { timeout: 30000 });
    const result = await page.evaluate(() => {
      const text = document.body.innerText;
      return {
        title: window.jQuery(".program-governance-admin-title h1").text().trim(),
        hasGrantAction: text.includes("Liberar grant"),
        hasApprovalAction: text.includes("Aprovar publicacao"),
        hasRetentionAction: text.includes("Salvar retencao")
      };
    });
    if (errors.length) {
      throw new Error("Erros JavaScript: " + errors.join(" | "));
    }
    if (result.title !== expectedTitle) {
      throw new Error("Titulo inesperado em modo focado: " + result.title);
    }
    if (expectedTitle === "Grants de programas" && (!result.hasGrantAction || result.hasApprovalAction || result.hasRetentionAction)) {
      throw new Error("Modo focado de grants nao ficou restrito como esperado.");
    }
    if (expectedTitle === "Aprovacoes de publicacao" && (!result.hasApprovalAction || result.hasGrantAction || result.hasRetentionAction)) {
      throw new Error("Modo focado de aprovacoes nao ficou restrito como esperado.");
    }
    if (expectedTitle === "Retencao da governanca" && (!result.hasRetentionAction || result.hasGrantAction || result.hasApprovalAction)) {
      throw new Error("Modo focado de retencao nao ficou restrito como esperado.");
    }
    return result;
  } finally {
    await page.close();
  }
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const pageErrors = [];

  try {
    page.on("pageerror", (error) => {
      pageErrors.push(String(error && error.message || error));
    });
    await page.goto(url);
    await page.waitForFunction(() => {
      return Boolean(window.jQuery(".program-governance-admin-shell").length && window.jQuery(".program-builder-governance-card").length);
    }, null, { timeout: 30000 });
    await page.screenshot({ path: path.join(outputDir, "admin-program-governance-summary.png"), fullPage: true });

    await page.evaluate(() => {
      const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
      if (widget) {
        widget.select(1);
      }
    });
    await clickButtonByText(page, "Criar solicitacao");
    await page.waitForFunction(() => {
      return document.body.innerText.includes("Solicitacao criada.");
    }, null, { timeout: 10000 });

    await page.evaluate(() => {
      const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
      if (widget) {
        widget.select(2);
      }
    });
    await clickButtonByText(page, "Liberar grant");
    await page.waitForFunction(() => {
      return document.body.innerText.includes("Grant criado.");
    }, null, { timeout: 10000 });

    await page.evaluate(() => {
      const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
      if (widget) {
        widget.select(5);
      }
    });
    await clickButtonByText(page, "Salvar retencao");
    await page.waitForFunction(() => {
      return document.body.innerText.includes("Retencao atualizada.");
    }, null, { timeout: 10000 });

    await page.evaluate(() => {
      const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
      if (widget) {
        widget.select(6);
      }
      const inputs = window.jQuery("input");
      inputs.each(function() {
        const label = window.jQuery(this).closest(".program-builder-field").find("label").text().trim();
        const widgetTextBox = window.jQuery(this).data("kendoTextBox");
        if (label === "Overlay ID" && widgetTextBox) {
          widgetTextBox.value("700");
        }
        if (label === "Versao do overlay" && widgetTextBox) {
          widgetTextBox.value("701");
        }
      });
    });
    await clickButtonByText(page, "Preview");
    await page.waitForFunction(() => document.body.innerText.includes("Status: warning"), null, { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "admin-program-governance-rebase.png"), fullPage: true });

    const result = await page.evaluate(() => {
      const shell = window.jQuery(".program-governance-admin-shell");
      const rows = window.jQuery(".program-governance-admin-row");
      const json = window.jQuery(".program-builder-inline-json").first().text();
      return {
        shellVisible: shell.length > 0,
        rowCount: rows.length,
        hasRequest: json.includes("requestCode"),
        hasGrant: json.includes("\"grants\""),
        rebaseLoaded: document.body.innerText.includes("Status: warning")
      };
    });

    const grantsMode = await inspectFocusedMode(browser, grantsUrl, "Grants de programas");
    const approvalsMode = await inspectFocusedMode(browser, approvalsUrl, "Aprovacoes de publicacao");
    const retentionMode = await inspectFocusedMode(browser, retentionUrl, "Retencao da governanca");

    if (pageErrors.length) {
      throw new Error("Erros JavaScript: " + pageErrors.join(" | "));
    }

    const finalResult = {
      ...result,
      grantsMode: grantsMode,
      approvalsMode: approvalsMode,
      retentionMode: retentionMode
    };

    await fs.writeFile(path.join(outputDir, "admin-program-governance-smoke-result.json"), JSON.stringify(finalResult, null, 2), "utf8");
    console.log(JSON.stringify(finalResult, null, 2));
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
