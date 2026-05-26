import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const url = "file:///C:/construtor-pg/production/install.html";

async function main() {
  await fs.mkdir(outputDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
  const errors = [];
  const requests = [];

  try {
    page.on("pageerror", (error) => errors.push(String(error && error.message || error)));
    await page.addInitScript(() => {
      function jsonResponse(payload, status = 200) {
        return {
          ok: status >= 200 && status < 300,
          status,
          text: async () => JSON.stringify(payload)
        };
      }
      const validStatus = {
        activation: {
          required: true,
          valid: true,
          profile: "subscriber",
          profileLabel: "Assinante",
          subscriberCode: "cliente-a",
          mode: "docker",
          sessionId: "sess-valid",
          expiresAt: "2026-05-26T23:00:00+00:00",
          message: "Sessao local valida."
        },
        systemInstalled: true,
        requiresInstallerPassword: true,
        installerPasswordConfigured: true,
        canRun: true,
        databaseAvailable: true,
        authUserTableExists: true,
        authUserCount: 1,
        environment: {
          databaseEnvironment: "prod",
          databaseIdentity: "install:cliente-a",
          authRequired: true,
          centralControl: false
        },
        steps: [
          { code: "bootstrap", label: "Criar banco, aplicar migrations e seed", status: "pending" },
          { code: "subscriber", label: "Criar assinante principal e administrador", status: "pending" }
        ]
      };
      const invalidStatus = {
        ...validStatus,
        activation: {
          required: true,
          valid: false,
          profileLabel: "-",
          subscriberCode: "",
          message: "Sessao local criada pelo executavel nao encontrada."
        },
        canRun: false,
        lockReason: "Sessao local criada pelo executavel nao encontrada."
      };
      window.__installRequests = [];
      window.__installMockMode = "invalid";
      window.fetch = async (requestUrl, options = {}) => {
        const target = String(requestUrl);
        const method = String(options.method || "GET").toUpperCase();
        const body = options.body ? JSON.parse(options.body) : {};
        window.__installRequests.push({ target, method, body });
        if (target.includes("/api/install/status")) {
          return jsonResponse(window.__installMockMode === "valid" ? validStatus : invalidStatus);
        }
        if (target.includes("/api/install/precheck")) {
          const missingBackup = validStatus.systemInstalled && !body.backupPolicy;
          return jsonResponse({
            canRun: !missingBackup,
            hasBlockingIssues: missingBackup,
            blockingIssues: missingBackup ? [{ code: "reinstall_backup_policy", message: "Escolha a politica de backup antes de reinstalar." }] : [],
            warnings: [],
            checklist: [
              { code: "activation", label: "Ativacao pelo instalador compilado", status: "ok", message: "Sessao local valida." },
              { code: "reinstall_backup", label: "Backup antes da reinstalacao", status: missingBackup ? "error" : "ok", message: missingBackup ? "Escolha a politica de backup antes de reinstalar." : "Backup validado antes da reinstalacao." }
            ],
            steps: validStatus.steps
          });
        }
        if (target.includes("/api/install/run")) {
          if (!body.backupPolicy) {
            return jsonResponse({ error: { code: "INSTALL_PRECHECK_FAILED", message: "Revise os bloqueios antes de executar a instalacao." } }, 422);
          }
          return jsonResponse({
            success: true,
            status: "succeeded",
            steps: validStatus.steps.map((step) => ({ ...step, status: "succeeded", message: "Etapa concluida." })),
            outputTail: "Instalacao concluida.",
            precheck: { checklist: [], blockingIssues: [], warnings: [] }
          });
        }
        return jsonResponse({ error: { code: "NOT_FOUND", message: "Nao mockado." } }, 404);
      };
    });

    await page.goto(url);
    await page.waitForFunction(() => Boolean(window.jQuery(".system-install-shell").length), null, { timeout: 30000 });
    await page.waitForFunction(() => document.body.innerText.includes("Sessao local criada pelo executavel nao encontrada."), null, { timeout: 10000 });

    await page.evaluate(() => {
      window.__installMockMode = "valid";
      window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Atualizar status";
      }).get(0).click();
    });
    await page.waitForFunction(() => document.body.innerText.includes("Sessao local valida."), null, { timeout: 10000 });

    await page.evaluate(() => {
      const value = "Instalador!12345A";
      window.jQuery("input[type='password']").first().data("kendoTextBox").value(value);
      const passwords = window.jQuery("input[type='password']");
      window.jQuery(passwords.get(1)).data("kendoTextBox").value("Admin!123456789A");
      window.jQuery("input[type='checkbox']").first().prop("checked", true);
      const backup = window.jQuery("input").filter(function() {
        return window.jQuery(this).closest("label").text().includes("Politica de backup");
      }).data("kendoDropDownList");
      backup.value("validated");
      backup.trigger("change");
    });

    await page.evaluate(() => {
      window.jQuery("button").filter(function() {
        return window.jQuery(this).text().trim() === "Validar";
      }).get(0).click();
    });
    await page.waitForFunction(() => document.body.innerText.includes("Backup validado antes da reinstalacao."), null, { timeout: 10000 });

    requests.push(...await page.evaluate(() => window.__installRequests));
    if (!requests.some((item) => item.target.includes("/api/install/precheck") && item.body.backupPolicy === "validated")) {
      throw new Error("Precheck nao enviou politica de backup validada.");
    }
    if (errors.length) {
      throw new Error("Erros JavaScript: " + errors.join(" | "));
    }

    await page.screenshot({ path: path.join(outputDir, "system-install-e2e.png"), fullPage: true });
    await fs.writeFile(path.join(outputDir, "system-install-e2e.json"), JSON.stringify({ requests }, null, 2), "utf8");
  } finally {
    await page.close();
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
