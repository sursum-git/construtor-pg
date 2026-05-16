import { chromium } from "playwright";
import { spawn, spawnSync } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import http from "node:http";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const backendUrl = "http://127.0.0.1:8000";
const frontendUrl = "http://127.0.0.1:8765/production/app.html?screenId=admin.integridade";

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

function runCommand(command, args, cwd) {
  const result = spawnSync(command, args, {
    cwd,
    env: process.env,
    stdio: "pipe",
    windowsHide: true,
    encoding: "utf8"
  });
  if (result.status !== 0) {
    throw new Error((result.stderr || result.stdout || "Falha ao executar comando").trim());
  }
}

function spawnProcess(command, args, options) {
  const child = spawn(command, args, {
    cwd: options.cwd,
    env: { ...process.env, ...(options.env || {}) },
    stdio: ["ignore", "pipe", "pipe"],
    windowsHide: true
  });
  child.stdout.on("data", () => {});
  child.stderr.on("data", () => {});
  return child;
}

async function waitForHttp(url, timeoutMs = 30000) {
  const startedAt = Date.now();
  while ((Date.now() - startedAt) < timeoutMs) {
    const ok = await new Promise((resolve) => {
      const request = http.get(url, (response) => {
        response.resume();
        resolve((response.statusCode || 500) < 500);
      });
      request.on("error", () => resolve(false));
    });
    if (ok) {
      return;
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  throw new Error("Timeout ao aguardar " + url);
}

async function screenshot(page, fileName) {
  await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function openFirstRecord(page) {
  await page.evaluate(async () => {
    const engine = window.productionCrudEngine;
    const grid = engine && engine.gridRenderer && engine.gridRenderer.grid;
    if (!engine || !grid || !grid.dataSource) {
      throw new Error("Grid de producao nao disponivel para abrir o primeiro registro.");
    }
    const first = grid.dataSource.view()[0];
    if (!first) {
      throw new Error("Nenhum registro carregado na grid de producao.");
    }
    const record = typeof first.toJSON === "function" ? first.toJSON() : first;
    const id = record.id || record.record_id;
    await engine.openRecord("view", id, { record });
  });
}

const backendServer = spawnProcess("php", ["-S", "127.0.0.1:8000", "public/index.php"], {
  cwd: path.join(repoRoot, "backend")
});
const staticServer = spawnProcess("node", ["scripts/serve-static.js", "8765"], {
  cwd: repoRoot,
  env: {
    CRUD_ENGINE_API_PROXY: backendUrl
  }
});

const browser = await chromium.launch({ headless: true });
const pageErrors = [];

try {
  await ensureOutputDir();
  runCommand("php", ["bin/console", "app:seed-runtime-metadata", "--no-interaction"], path.join(repoRoot, "backend"));
  await waitForHttp(backendUrl + "/api/runtime/literals/pt-BR", 30000);
  await waitForHttp(backendUrl + "/api/runtime/screens/admin.integridade", 30000);
  await waitForHttp("http://127.0.0.1:8765/production/app.html?screenId=admin.integridade", 30000);

  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  page.on("pageerror", (error) => {
    pageErrors.push(String(error && error.message || error));
  });

  await page.goto(frontendUrl);
  await page.waitForSelector("#crud-production-root .k-grid-content tr", { timeout: 60000 });
  await screenshot(page, "admin-integridade-production-grid.png");

  await openFirstRecord(page);
  await page.waitForSelector(".k-window .crud-other-actions-button", { timeout: 20000 });
  await screenshot(page, "admin-integridade-production-form.png");

  await page.locator(".k-window .crud-other-actions-button").click();
  await page.waitForSelector(".crud-other-actions-menu .crud-other-action", { timeout: 15000 });
  await page.locator(".crud-other-actions-menu .crud-other-action").getByText("Reassinar", { exact: true }).click();
  await page.waitForSelector(".k-window:has-text('Deseja continuar?')", { timeout: 15000 });
  await screenshot(page, "admin-integridade-production-confirm.png");

  await page.getByRole("button", { name: "Confirmar", exact: true }).last().click();
  await page.waitForTimeout(1200);
  await page.waitForFunction(() => document.body.innerText.includes("Registro reassinado."), null, { timeout: 20000 });
  await screenshot(page, "admin-integridade-production-result.png");

  const result = await page.evaluate(() => {
    const grid = window.jQuery("#crud-production-root .k-grid").data("kendoGrid");
    const row = grid && grid.dataSource && grid.dataSource.data && grid.dataSource.data()[0];
    return {
      rowCount: grid && grid.dataSource ? grid.dataSource.total() : 0,
      firstRowStatus: row && row.last_check_status ? row.last_check_status : null,
      pageTextHasSuccess: document.body.innerText.includes("Registro reassinado.")
    };
  });

  if (pageErrors.length) {
    throw new Error("Erros JavaScript detectados: " + pageErrors.join(" | "));
  }

  await fs.writeFile(path.join(outputDir, "admin-integridade-production-smoke-result.json"), JSON.stringify(result, null, 2), "utf8");
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
  staticServer.kill("SIGTERM");
  backendServer.kill("SIGTERM");
}
