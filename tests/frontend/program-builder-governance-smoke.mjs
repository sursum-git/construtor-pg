import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const baseUrl = "file:///C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");

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
      throw new Error("Botao habilitado nao encontrado: " + buttonText);
    }
    button.click();
  }, text);
}

async function clickFirstVisibleButton(page, candidates) {
  for (const text of candidates) {
    const exists = await page.evaluate((buttonText) => {
      return Boolean(window.jQuery("button").filter(function() {
        const $button = window.jQuery(this);
        return $button.text().trim() === buttonText && $button.is(":visible") && !$button.prop("disabled");
      }).get(0));
    }, text);
    if (exists) {
      await clickButtonByText(page, text);
      return text;
    }
  }
  throw new Error("Nenhum botao visivel encontrado: " + candidates.join(", "));
}

async function setVisibleWindowField(page, title, selector, value, index = 0) {
  await page.evaluate(({ windowTitle, fieldSelector, fieldValue, fieldIndex }) => {
    const host = window.jQuery(".k-window-content").filter(function() {
      const windowElement = window.jQuery(this).closest(".k-window");
      return windowElement.is(":visible") && windowElement.find(".k-window-title").text().includes(windowTitle);
    }).first();
    const target = host.find(fieldSelector).get(fieldIndex);
    if (!target) {
      throw new Error("Campo visivel nao encontrado em " + windowTitle + " para " + fieldSelector);
    }
    const $target = window.jQuery(target);
    const textArea = $target.data("kendoTextArea");
    const textBox = $target.data("kendoTextBox");
    if (textArea && typeof textArea.value === "function") {
      textArea.value(fieldValue);
      return;
    }
    if (textBox && typeof textBox.value === "function") {
      textBox.value(fieldValue);
      return;
    }
    $target.val(fieldValue).trigger("input").trigger("change");
  }, { windowTitle: title, fieldSelector: selector, fieldValue: value, fieldIndex: index });
}

async function clickVisibleWindowButton(page, title, text) {
  await page.evaluate(({ windowTitle, buttonText }) => {
    const host = window.jQuery(".k-window-content").filter(function() {
      const windowElement = window.jQuery(this).closest(".k-window");
      return windowElement.is(":visible") && windowElement.find(".k-window-title").text().includes(windowTitle);
    }).first();
    const button = host.find("button").filter(function() {
      return window.jQuery(this).text().trim() === buttonText;
    }).get(0);
    if (!button) {
      throw new Error("Botao visivel nao encontrado em " + windowTitle + " para " + buttonText);
    }
    button.click();
  }, { windowTitle: title, buttonText: text });
}

async function closeGovernanceWindow(page) {
  await page.evaluate(() => {
    const widget = window.jQuery(".k-window-content:visible").filter(function() {
      return window.jQuery(this).closest(".k-window").find(".k-window-title").text().includes("Governanca do programa");
    }).data("kendoWindow");
    if (widget) {
      widget.close();
    }
  });
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 }, acceptDownloads: true });
  const page = await context.newPage();
  const pageErrors = [];

  try {
    page.on("pageerror", (error) => {
      pageErrors.push(error && error.stack ? error.stack : String(error));
    });

    await page.goto(baseUrl + "/examples/pages/program-builder-governance.html");
    await page.waitForFunction(() => Boolean(window.programBuilderGovernanceDemoApp), null, { timeout: 30000 });

    await clickButtonByText(page, "Publicar");
    await page.waitForFunction(() => document.body.innerText.includes("Governanca do programa"), null, { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "program-builder-governance-initial.png"), fullPage: true });

    await setVisibleWindowField(page, "Governanca do programa", "textarea", "Ajuste governado do programa padrao.");
    await clickButtonByText(page, "Solicitar alteracao");
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      return Boolean(app && app.state.currentVersion && app.state.currentVersion.governance && app.state.currentVersion.governance.request);
    }, null, { timeout: 10000 });

    await setVisibleWindowField(page, "Governanca do programa", "input", "analista", 1);
    await clickButtonByText(page, "Liberar grant");
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      return Boolean(app && app.state.currentVersion && app.state.currentVersion.governance && app.state.currentVersion.governance.grant && app.state.currentVersion.governance.grant.status === "active" && app.state.currentLock && app.state.currentLock.grantId);
    }, null, { timeout: 10000 });

    await clickButtonByText(page, "Congelar");
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      return Boolean(app && app.state.currentVersion && app.state.currentVersion.governance && app.state.currentVersion.governance.grant && app.state.currentVersion.governance.grant.status === "frozen");
    }, null, { timeout: 10000 });

    await clickButtonByText(page, "Reativar");
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      return Boolean(app && app.state.currentVersion && app.state.currentVersion.governance && app.state.currentVersion.governance.grant && app.state.currentVersion.governance.grant.status === "active");
    }, null, { timeout: 10000 });
    await page.evaluate(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const current = app && app.state ? app.state.currentVersion : null;
      if (!app || !current) {
        throw new Error("App de governanca nao disponivel para reacquirir lock.");
      }
      app.ensureEditorLock("program", current.programCode, current.programTitle || current.programCode);
    });
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const grant = app && app.state && app.state.currentVersion && app.state.currentVersion.governance ? app.state.currentVersion.governance.grant : null;
      return Boolean(app && app.state && app.state.currentLock && grant && Number(app.state.currentLock.grantId || 0) === Number(grant.id || 0));
    }, null, { timeout: 10000 });

    await clickButtonByText(page, "Revogar");
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const grant = app && app.state && app.state.currentVersion && app.state.currentVersion.governance ? app.state.currentVersion.governance.grant : null;
      return Boolean(grant && grant.status === "revoked");
    }, null, { timeout: 10000 });

    await clickButtonByText(page, "Liberar grant");
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const grant = app && app.state && app.state.currentVersion && app.state.currentVersion.governance ? app.state.currentVersion.governance.grant : null;
      return Boolean(grant && grant.status === "active");
    }, null, { timeout: 10000 });
    await page.evaluate(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const current = app && app.state ? app.state.currentVersion : null;
      if (!app || !current) {
        throw new Error("App de governanca nao disponivel para reacquirir lock apos revogacao.");
      }
      app.ensureEditorLock("program", current.programCode, current.programTitle || current.programCode);
    });
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const grant = app && app.state && app.state.currentVersion && app.state.currentVersion.governance ? app.state.currentVersion.governance.grant : null;
      return Boolean(app && app.state && app.state.currentLock && grant && Number(app.state.currentLock.grantId || 0) === Number(grant.id || 0));
    }, null, { timeout: 10000 });

    await clickButtonByText(page, "Registrar teste aprovado");
    await clickButtonByText(page, "Aprovar publicacao");
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.refreshProgramVersions("cd1001"));
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      return Boolean(app && app.state.currentVersion && app.state.currentVersion.governance && app.state.currentVersion.governance.approval && app.state.currentVersion.governance.approval.status === "approved");
    }, null, { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "program-builder-governance-ready.png"), fullPage: true });

    await closeGovernanceWindow(page);
    await page.evaluate(() => window.programBuilderGovernanceDemoApp.handlePublish());
    await page.waitForFunction(() => document.body.innerText.includes("Deseja continuar?"), null, { timeout: 10000 });
    await clickButtonByText(page, "Confirmar");
    await page.waitForFunction(() => {
      const app = window.programBuilderGovernanceDemoApp;
      return Boolean(app && app.state.currentVersion && app.state.currentVersion.status === "published");
    }, null, { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "program-builder-governance-published.png"), fullPage: true });

    await page.evaluate(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const overlayVersion = window.CrudUtils.clone(window.ProgramBuilderGovernanceEmbeddedData.overlayVersion);
      app.populateProgramForm(overlayVersion);
      app.syncToolbarState();
    });
    await clickButtonByText(page, "Rebase overlay");
    await page.waitForFunction(() => document.body.innerText.includes("Assistente de rebase"), null, { timeout: 10000 });
    await setVisibleWindowField(page, "Rebase de overlay", "input", "700", 0);
    await setVisibleWindowField(page, "Rebase de overlay", "input", "701", 1);
    await clickButtonByText(page, "Preview");
    await page.waitForFunction(() => document.body.innerText.includes("Resumo do rebase"), null, { timeout: 10000 });
    await page.evaluate(() => {
      const select = Array.from(document.querySelectorAll(".k-window-content select")).find((item) => item.options.length > 1 && item.offsetParent !== null);
      if (select) {
        select.value = "overlay";
        select.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });
    await clickButtonByText(page, "Executar rebase");
    await page.waitForTimeout(500);
    const hasRebaseConfirm = await page.evaluate(() => {
      return Boolean(window.jQuery("button").filter(function() {
        const text = window.jQuery(this).text().trim();
        return window.jQuery(this).is(":visible") && (text === "Confirmar" || text === "Continuar");
      }).get(0));
    });
    if (hasRebaseConfirm) {
      await clickFirstVisibleButton(page, ["Confirmar", "Continuar"]);
    }
    await page.waitForFunction(() => document.body.innerText.includes("Rebase concluido."), null, { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "program-builder-governance-rebase.png"), fullPage: true });

    const result = await page.evaluate(() => {
      const app = window.programBuilderGovernanceDemoApp;
      const gate = app.governanceGateState(app.state.currentVersion);
      return {
        publishedStatus: window.programBuilderGovernanceDemoHttp.state.programResponse.versions[0].status,
        grantStatusAfterPublish: window.programBuilderGovernanceDemoHttp.state.programResponse.versions[0].governance.grant.status,
        approvalStatus: window.programBuilderGovernanceDemoHttp.state.programResponse.versions[0].governance.approval.status,
        grantWasFrozenDuringFlow: true,
        grantWasRevokedDuringFlow: true,
        overlayConflictCount: window.programBuilderGovernanceDemoHttp.state.overlayPreview.conflicts.length,
        currentUiGateRequired: gate.required
      };
    });

    await fs.writeFile(
      path.join(outputDir, "program-builder-governance-smoke-result.json"),
      JSON.stringify(result, null, 2),
      "utf8"
    );

    if (pageErrors.length) {
      throw new Error("Falhas JavaScript durante o smoke: \n" + pageErrors.join("\n\n"));
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
