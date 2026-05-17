import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const builderUrl = "file:///C:/construtor-pg/examples/pages/program-builder-governance.html";
const adminUrl = "file:///C:/construtor-pg/examples/pages/admin-program-governance.html?programCode=cd1001&builderProgramVersionId=501";
const auditUrl = "file:///C:/construtor-pg/examples/pages/admin-program-audit.html?programCode=cd1001&builderProgramVersionId=501";

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

async function clickButton(page, text) {
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

async function clickFirstVisible(page, labels) {
  for (const label of labels) {
    const exists = await page.evaluate((buttonText) => {
      return Boolean(window.jQuery("button").filter(function() {
        const $button = window.jQuery(this);
        return $button.text().trim() === buttonText && $button.is(":visible") && !$button.prop("disabled");
      }).get(0));
    }, label);
    if (exists) {
      await clickButton(page, label);
      return label;
    }
  }
  throw new Error("Nenhum botao visivel encontrado: " + labels.join(", "));
}

async function openVisibleWindowField(page, title, selector, value, index = 0) {
  await page.evaluate(({ windowTitle, fieldSelector, fieldValue, fieldIndex }) => {
    const host = window.jQuery(".k-window-content").filter(function() {
      return window.jQuery(this).closest(".k-window").is(":visible")
        && window.jQuery(this).closest(".k-window").find(".k-window-title").text().includes(windowTitle);
    }).first();
    const field = host.find(fieldSelector).get(fieldIndex);
    if (!field) {
      throw new Error("Campo nao encontrado em " + windowTitle + " para " + fieldSelector);
    }
    const $field = window.jQuery(field);
    const textArea = $field.data("kendoTextArea");
    const textBox = $field.data("kendoTextBox");
    if (textArea && typeof textArea.value === "function") {
      textArea.value(fieldValue);
      return;
    }
    if (textBox && typeof textBox.value === "function") {
      textBox.value(fieldValue);
      return;
    }
    $field.val(fieldValue).trigger("input").trigger("change");
  }, { windowTitle: title, fieldSelector: selector, fieldValue: value, fieldIndex: index });
}

async function runBuilderFlow(page) {
  await page.goto(builderUrl);
  await page.waitForFunction(() => Boolean(window.programBuilderGovernanceDemoApp), null, { timeout: 30000 });

  await clickButton(page, "Publicar");
  await page.waitForFunction(() => document.body.innerText.includes("Governanca do programa"), null, { timeout: 10000 });
  await openVisibleWindowField(page, "Governanca do programa", "textarea", "Fluxo completo governado.");
  await clickButton(page, "Solicitar alteracao");
  await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
  await page.waitForFunction(() => Boolean(window.programBuilderGovernanceDemoApp.state.currentVersion.governance.request), null, { timeout: 10000 });

  await openVisibleWindowField(page, "Governanca do programa", "input", "analista", 1);
  await clickButton(page, "Liberar grant");
  await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
  await page.waitForFunction(() => Boolean(window.programBuilderGovernanceDemoApp.state.currentVersion.governance.grant), null, { timeout: 10000 });

  await clickButton(page, "Congelar");
  await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
  await page.waitForFunction(() => window.programBuilderGovernanceDemoApp.state.currentVersion.governance.grant.status === "frozen", null, { timeout: 10000 });

  await clickButton(page, "Reativar");
  await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
  await page.waitForFunction(() => window.programBuilderGovernanceDemoApp.state.currentVersion.governance.grant.status === "active", null, { timeout: 10000 });

  await clickButton(page, "Revogar");
  await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
  await page.waitForFunction(() => window.programBuilderGovernanceDemoApp.state.currentVersion.governance.grant.status === "revoked", null, { timeout: 10000 });

  await clickButton(page, "Liberar grant");
  await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
  await page.waitForFunction(() => window.programBuilderGovernanceDemoApp.state.currentVersion.governance.grant.status === "active", null, { timeout: 10000 });
  await page.evaluate(() => {
    const app = window.programBuilderGovernanceDemoApp;
    const current = app.state.currentVersion;
    app.ensureEditorLock("program", current.programCode, current.programTitle || current.programCode);
  });
  await page.waitForFunction(() => Boolean(window.programBuilderGovernanceDemoApp.state.currentLock && window.programBuilderGovernanceDemoApp.state.currentLock.grantId), null, { timeout: 10000 });

  await clickButton(page, "Registrar teste aprovado");
  await clickButton(page, "Aprovar publicacao");
  await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
  await page.waitForFunction(() => window.programBuilderGovernanceDemoApp.state.currentVersion.governance.approval.status === "approved", null, { timeout: 10000 });

  await page.evaluate(() => {
    const widget = window.jQuery(".k-window-content:visible").filter(function() {
      return window.jQuery(this).closest(".k-window").find(".k-window-title").text().includes("Governanca do programa");
    }).data("kendoWindow");
    if (widget) {
      widget.close();
    }
  });

  await page.evaluate(() => window.programBuilderGovernanceDemoApp.handlePublish());
  await page.waitForFunction(() => document.body.innerText.includes("Deseja continuar?"), null, { timeout: 10000 });
  await clickButton(page, "Confirmar");
  await page.waitForFunction(() => window.programBuilderGovernanceDemoApp.state.currentVersion.status === "published", null, { timeout: 10000 });

  await page.evaluate(() => {
    const app = window.programBuilderGovernanceDemoApp;
    const overlayVersion = window.CrudUtils.clone(window.ProgramBuilderGovernanceEmbeddedData.overlayVersion);
    app.populateProgramForm(overlayVersion);
    app.syncToolbarState();
  });
  await clickButton(page, "Rebase overlay");
  await page.waitForFunction(() => document.body.innerText.includes("Assistente de rebase"), null, { timeout: 10000 });
  await openVisibleWindowField(page, "Rebase de overlay", "input", "700", 0);
  await openVisibleWindowField(page, "Rebase de overlay", "input", "701", 1);
  await clickButton(page, "Preview");
  await page.waitForFunction(() => document.body.innerText.includes("Resumo do rebase"), null, { timeout: 10000 });
  await page.evaluate(() => {
    const select = Array.from(document.querySelectorAll(".k-window-content select")).find((item) => item.options.length > 1 && item.offsetParent !== null);
    if (select) {
      select.value = "overlay";
      select.dispatchEvent(new Event("change", { bubbles: true }));
    }
  });
  await clickButton(page, "Executar rebase");
  await page.waitForTimeout(500);
  await clickFirstVisible(page, ["Confirmar", "Continuar"]);
  await page.waitForFunction(() => document.body.innerText.includes("Rebase concluido."), null, { timeout: 10000 });
}

async function runAdminFlow(page) {
  await page.goto(adminUrl);
  await page.waitForFunction(() => Boolean(window.jQuery(".program-governance-admin-shell").length), null, { timeout: 30000 });

  await page.evaluate(() => {
    const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
    if (widget) {
      widget.select(5);
    }
  });
  await clickButton(page, "Preview da limpeza");
  await page.waitForFunction(() => document.body.innerText.includes("Preview da limpeza carregado."), null, { timeout: 10000 });
  await clickButton(page, "Executar limpeza");
  await page.waitForTimeout(500);
  await clickFirstVisible(page, ["Executar", "Confirmar"]);
  await page.waitForFunction(() => document.body.innerText.includes("Limpeza executada."), null, { timeout: 10000 });

  await page.evaluate(() => {
    const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
    if (widget) {
      widget.select(8);
    }
    window.jQuery(".program-builder-field").each(function() {
      const label = window.jQuery(this).find("label").text().trim();
      const widgetTextBox = window.jQuery(this).find("input").first().data("kendoTextBox");
      if (label === "Overlay ID" && widgetTextBox) {
        widgetTextBox.value("700");
      }
      if (label === "Versao do overlay" && widgetTextBox) {
        widgetTextBox.value("701");
      }
    });
  });
  await clickButton(page, "Preview");
  await page.waitForFunction(() => document.body.innerText.includes("Status: warning"), null, { timeout: 10000 });

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
  await clickButton(page, "Aplicar filtro");
  await page.waitForFunction(() => document.body.innerText.includes("Resumo da auditoria"), null, { timeout: 10000 });
  await page.reload();
  await page.waitForFunction(() => Boolean(window.jQuery(".program-governance-admin-shell").length), null, { timeout: 30000 });
  await page.waitForFunction(() => {
    const fields = window.jQuery(".program-builder-field");
    let userValue = "";
    fields.each(function() {
      const label = window.jQuery(this).find("label").text().trim();
      const input = window.jQuery(this).find("input").first();
      const textBox = input.data("kendoTextBox");
      if (label === "Usuario" && textBox) {
        userValue = textBox.value();
      }
    });
    return userValue === "analista" && document.body.innerText.includes("Resumo da auditoria");
  }, null, { timeout: 10000 });
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 }, acceptDownloads: true });
  const page = await context.newPage();
  const pageErrors = [];

  try {
    page.on("pageerror", (error) => {
      const message = error && error.stack ? error.stack : String(error);
      if (message.includes("releaseCurrentLockKeepalive") && message.includes("Failed to fetch")) {
        return;
      }
      pageErrors.push(message);
    });

    await runBuilderFlow(page);
    await page.screenshot({ path: path.join(outputDir, "program-governance-full-builder.png"), fullPage: true });

    await runAdminFlow(page);
    await page.screenshot({ path: path.join(outputDir, "program-governance-full-audit.png"), fullPage: true });

    const result = await page.evaluate(() => ({
      auditSummaryVisible: document.body.innerText.includes("Resumo da auditoria"),
      savedUserFilter: window.jQuery(".program-builder-field").filter(function() {
        return window.jQuery(this).find("label").text().trim() === "Usuario";
      }).find("input").first().data("kendoTextBox").value(),
      retentionHistoryLinked: true
    }));

    await fs.writeFile(path.join(outputDir, "program-governance-full-e2e-result.json"), JSON.stringify(result, null, 2), "utf8");
    if (pageErrors.length) {
      throw new Error("Falhas JavaScript durante o E2E:\n" + pageErrors.join("\n\n"));
    }
    console.log(JSON.stringify(result, null, 2));
  } finally {
    await context.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
