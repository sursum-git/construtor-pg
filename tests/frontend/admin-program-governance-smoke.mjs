import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const url = "file:///C:/construtor-pg/examples/pages/admin-program-governance.html?programCode=cd1001&builderProgramVersionId=501&tab=grants";
const grantsUrl = "file:///C:/construtor-pg/examples/pages/admin-program-grants.html?programCode=cd1001&builderProgramVersionId=501";
const approvalsUrl = "file:///C:/construtor-pg/examples/pages/admin-program-approvals.html?programCode=cd1001&builderProgramVersionId=501";
const retentionUrl = "file:///C:/construtor-pg/examples/pages/admin-program-retention.html?programCode=cd1001&builderProgramVersionId=501";
const retentionHistoryUrl = "file:///C:/construtor-pg/examples/pages/admin-program-retention-history.html?programCode=cd1001&builderProgramVersionId=501";
const auditUrl = "file:///C:/construtor-pg/examples/pages/admin-program-audit.html?programCode=cd1001&builderProgramVersionId=501";
const operationsUrl = "file:///C:/construtor-pg/examples/pages/admin-program-operations.html?programCode=cd1001&builderProgramVersionId=501";
const overlaysUrl = "file:///C:/construtor-pg/examples/pages/admin-program-overlays.html?programCode=cd1001&builderProgramVersionId=501";
const overlayVersionsUrl = "file:///C:/construtor-pg/examples/pages/admin-program-overlay-versions.html?programCode=cd1001&builderProgramVersionId=501&overlayId=700";

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
    if (expectedTitle === "Operacoes da governanca") {
      await page.waitForFunction(() => document.body.innerText.includes("Rodar operacao unificada"), null, { timeout: 10000 });
    }
    const result = await page.evaluate(() => {
      const text = document.body.innerText;
      return {
        title: window.jQuery(".program-governance-admin-title h1").text().trim(),
        bodyText: text,
        hasGrantAction: text.includes("Liberar grant"),
        hasApprovalAction: text.includes("Aprovar publicacao"),
        hasRetentionAction: text.includes("Salvar retencao"),
        hasOperationsAction: text.includes("Rodar operacao unificada"),
        hasAuditTimeline: text.includes("Linha do tempo"),
        hasOverlayAction: text.includes("Usar no rebase"),
        hasOverlayVersionsAction: text.includes("Carregar versoes")
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
    if (expectedTitle === "Historico da retencao" && (result.hasGrantAction || result.hasApprovalAction)) {
      throw new Error("Modo focado de historico da retencao nao ficou restrito como esperado.");
    }
    if (expectedTitle === "Auditoria da governanca" && (!result.hasAuditTimeline || result.hasGrantAction || result.hasApprovalAction || result.hasRetentionAction)) {
      throw new Error("Modo focado de auditoria nao ficou restrito como esperado.");
    }
    if (expectedTitle === "Operacoes da governanca" && (!result.hasOperationsAction || result.hasGrantAction || result.hasApprovalAction)) {
      throw new Error("Modo focado de operacoes nao ficou restrito como esperado.");
    }
    if (expectedTitle === "Overlays de programas" && (result.hasGrantAction || result.hasApprovalAction || result.hasRetentionAction)) {
      throw new Error("Modo focado de overlays nao ficou restrito como esperado.");
    }
    if (expectedTitle === "Versoes de overlay" && (result.hasGrantAction || result.hasApprovalAction || result.hasRetentionAction)) {
      throw new Error("Modo focado de versoes de overlay nao ficou restrito como esperado.");
    }
    return result;
  } finally {
    await page.close();
  }
}

async function main() {
  await ensureOutputDir();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 }, acceptDownloads: true });
  const page = await context.newPage();
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
        widget.select(5);
      }
    });
    await clickButtonByText(page, "Preview da limpeza");
    await page.waitForFunction(() => {
      return document.body.innerText.includes("Preview da limpeza carregado.");
    }, null, { timeout: 10000 });
    await page.waitForFunction(() => {
      return document.body.innerText.includes("Historico da retencao");
        }, null, { timeout: 10000 });
    const retentionDownload = page.waitForEvent("download");
    await clickButtonByText(page, "Exportar JSON");
    const retentionJson = await retentionDownload;
    if (!/governanca-retencao-.*\.json$/i.test(retentionJson.suggestedFilename())) {
      throw new Error("Download JSON da retencao nao foi gerado como esperado.");
    }
    await clickButtonByText(page, "Executar limpeza");
    await page.waitForTimeout(500);
    await clickFirstVisibleButton(page, ["Executar", "Confirmar"]);
    await page.waitForFunction(() => document.body.innerText.includes("Limpeza executada."), null, { timeout: 10000 });
    await page.waitForTimeout(1000);

    await page.evaluate(() => {
      const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
      if (widget) {
        widget.select(6);
      }
    });
    await page.evaluate(() => {
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
      const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
      if (widget) {
        widget.select(8);
      }
    });
    await clickButtonByText(page, "Preview");
    await page.waitForFunction(() => document.body.innerText.includes("Status: warning"), null, { timeout: 10000 });
    await page.evaluate(() => {
      const select = Array.from(document.querySelectorAll("select")).find((item) => item.options.length && item.closest(".program-governance-admin-list"));
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
    await page.waitForFunction(() => document.body.innerText.includes("Rebase executado."), null, { timeout: 10000 });
    await page.screenshot({ path: path.join(outputDir, "admin-program-governance-rebase.png"), fullPage: true });

    await page.evaluate(() => {
      const widget = window.jQuery(".program-governance-admin-tabs").data("kendoTabStrip");
      if (widget) {
        widget.select(7);
      }
    });
    await page.evaluate(() => {
      const inputs = window.jQuery("input");
      inputs.each(function() {
        const label = window.jQuery(this).closest(".program-builder-field").find("label").text().trim();
        const widgetTextBox = window.jQuery(this).data("kendoTextBox");
        if (label === "Overlay ID" && widgetTextBox) {
          widgetTextBox.value("700");
        }
      });
    });
    await clickButtonByText(page, "Carregar versoes");
    await page.waitForFunction(() => document.body.innerText.includes("Versoes do overlay carregadas."), null, { timeout: 10000 });
    await clickButtonByText(page, "Comparar versoes");
    await page.waitForFunction(() => document.body.innerText.includes("Comparacao carregada."), null, { timeout: 10000 });
    await clickButtonByText(page, "Publicar versao esquerda");
    await page.waitForFunction(() => document.body.innerText.includes("Deseja continuar?"), null, { timeout: 10000 });
    await clickButtonByText(page, "Confirmar");
    await page.waitForFunction(() => document.body.innerText.includes("Versao do overlay publicada."), null, { timeout: 10000 });

    const result = await page.evaluate(() => {
      const shell = window.jQuery(".program-governance-admin-shell");
      const rows = window.jQuery(".program-governance-admin-row");
      const json = window.jQuery(".program-builder-inline-json").first().text();
      return {
        shellVisible: shell.length > 0,
        rowCount: rows.length,
        hasRequest: json.includes("requestCode"),
        hasGrant: json.includes("\"grants\""),
        rebaseLoaded: document.body.innerText.includes("Status: warning"),
        overlayVersionsLoaded: document.body.innerText.includes("Versao #2"),
        retentionExportJson: true,
        retentionApplyLinked: Boolean(window.programGovernanceAdminApp && window.programGovernanceAdminApp.state && Array.isArray(window.programGovernanceAdminApp.state.retentionRuns) && window.programGovernanceAdminApp.state.retentionRuns.some((item) => item.mode === "apply" && Number(item.relatedRunId || 0) > 0))
      };
    });

    const grantsMode = await inspectFocusedMode(browser, grantsUrl, "Grants de programas");
    const approvalsMode = await inspectFocusedMode(browser, approvalsUrl, "Aprovacoes de publicacao");
    const retentionMode = await inspectFocusedMode(browser, retentionUrl, "Retencao da governanca");
    const retentionHistoryMode = await inspectFocusedMode(browser, retentionHistoryUrl, "Historico da retencao");
    const auditMode = await inspectFocusedMode(browser, auditUrl, "Auditoria da governanca");
    const operationsMode = await inspectFocusedMode(browser, operationsUrl, "Operacoes da governanca");
    const overlaysMode = await inspectFocusedMode(browser, overlaysUrl, "Overlays de programas");
    const overlayVersionsMode = await inspectFocusedMode(browser, overlayVersionsUrl, "Versoes de overlay");
    const auditPage = await browser.newPage({ viewport: { width: 1440, height: 960 } });
    await auditPage.goto(auditUrl);
    await auditPage.waitForFunction(() => Boolean(window.jQuery(".program-governance-admin-shell").length), null, { timeout: 30000 });
    await auditPage.evaluate(() => {
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
    await clickButtonByText(auditPage, "Aplicar filtro");
    await auditPage.waitForFunction(() => document.body.innerText.includes("Resumo da auditoria"), null, { timeout: 10000 });
    const auditFilterResult = await auditPage.evaluate(() => ({
      hasGrantOnlySummary: document.body.innerText.includes("Eventos por tipo"),
      filteredTimelineCount: window.jQuery(".program-governance-admin-timeline-item").length
    }));
    await auditPage.close();

    if (pageErrors.length) {
      throw new Error("Erros JavaScript: " + pageErrors.join(" | "));
    }

    const finalResult = {
      ...result,
      grantsMode: grantsMode,
      approvalsMode: approvalsMode,
      retentionMode: retentionMode,
      retentionHistoryMode: retentionHistoryMode,
      auditMode: auditMode,
      operationsMode: operationsMode,
      auditFilterResult: auditFilterResult,
      overlaysMode: overlaysMode,
      overlayVersionsMode: overlayVersionsMode
    };

    await fs.writeFile(path.join(outputDir, "admin-program-governance-smoke-result.json"), JSON.stringify(finalResult, null, 2), "utf8");
    console.log(JSON.stringify(finalResult, null, 2));
  } finally {
    await context.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
