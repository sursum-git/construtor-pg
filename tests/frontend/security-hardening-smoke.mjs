import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";

const repoRoot = "C:/construtor-pg";

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function listHtmlFiles(dir) {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await listHtmlFiles(fullPath));
      continue;
    }
    if (entry.isFile() && entry.name.endsWith(".html")) {
      files.push(fullPath);
    }
  }
  return files;
}

async function assertProductionCsp() {
  const files = await listHtmlFiles(path.join(repoRoot, "production"));
  assert(files.length > 0, "Nenhuma pagina de producao encontrada para validar CSP.");
  for (const file of files) {
    const html = await fs.readFile(file, "utf8");
    const csp = html.match(/<meta\s+http-equiv=["']Content-Security-Policy["']\s+content="([^"]+)"/i)
      || html.match(/<meta\s+content="([^"]+)"\s+http-equiv=["']Content-Security-Policy["']/i);
    assert(csp, `CSP ausente em ${file}`);
    for (const directive of ["default-src 'self'", "object-src 'none'", "base-uri 'self'", "form-action 'self'"]) {
      assert(csp[1].includes(directive), `Diretiva CSP obrigatoria ausente em ${file}: ${directive}`);
    }
    const inlineScript = html.match(/<script\b(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/i);
    assert(!inlineScript || inlineScript[1].trim() === "", `Script inline encontrado em ${file}`);
  }
}

async function assertNoCommittedDefaultSecrets() {
  const checks = [
    "backend/.env",
    "backend/compose.yaml",
    "compose.yaml",
    "scripts/install-onprem.ps1",
    "scripts/install-onprem.sh",
    "scripts/provision-saas-subscriber.ps1",
    "scripts/provision-saas-subscriber.sh",
    "docs/provisionamento-saas-onprem.md",
    "docs/roteiro-teste-web.md",
    "backend/README.md"
  ];
  for (const relativePath of checks) {
    const content = await fs.readFile(path.join(repoRoot, relativePath), "utf8");
    assert(!content.includes("admin123"), `Senha admin padrao residual em ${relativePath}`);
    assert(!content.includes("!ChangeMe!"), `Senha de banco padrao residual em ${relativePath}`);
    assert(!content.includes("--admin-password="), `Senha admin por argv residual em ${relativePath}`);
    assert(!content.includes("--database-password="), `Senha banco por argv residual em ${relativePath}`);
  }
}

async function assertPasswordResetUrlDoesNotCarryToken() {
  const browser = await chromium.launch({ headless: true });
  const errors = [];
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 860 } });
    page.on("pageerror", (error) => errors.push(String(error && error.message || error)));

    const loginUrl = pathToFileURL(path.join(repoRoot, "production", "login.html")).href;
    await page.goto(loginUrl + "?reset=1&noRemember=1");
    await page.waitForSelector(".k-window .login-dialog-form", { timeout: 15000 });
    const resetValues = await page.locator(".k-window .login-field input").evaluateAll((inputs) => inputs.map((input) => input.value));
    assert((resetValues[1] || "") === "", "Token de reset deve iniciar vazio quando aberto por reset=1.");

    const legacyPage = await browser.newPage({ viewport: { width: 1280, height: 860 } });
    await legacyPage.goto(loginUrl + "?resetToken=leaked-token&noRemember=1");
    await legacyPage.waitForTimeout(800);
    const leaked = await legacyPage.locator(".k-window .login-field input").evaluateAll((inputs) => inputs.map((input) => input.value)).catch(() => []);
    assert(!leaked.includes("leaked-token"), "Token de reset vindo da querystring nao deve ser aceito pela UI.");

    if (errors.length) {
      throw new Error("Erros JavaScript detectados: " + errors.join(" | "));
    }
  } finally {
    await browser.close();
  }
}

await assertProductionCsp();
await assertNoCommittedDefaultSecrets();
await assertPasswordResetUrlDoesNotCarryToken();

console.log(JSON.stringify({
  productionCsp: true,
  committedDefaultSecrets: false,
  resetTokenInQueryStringAccepted: false
}, null, 2));
