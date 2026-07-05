import { chromium } from "playwright";
import { spawn, spawnSync } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import http from "node:http";

const repoRoot = "C:/construtor-pg";
const outputDir = path.join(repoRoot, "tmp");
const backendUrl = "http://127.0.0.1:8011";
const frontendUrl = "http://127.0.0.1:8771/production/login.html?noRemember=1";
const username = "auth_e2e";
const initialPassword = "AuthE2e!123456789";
const resetPassword = "AuthE2e!987654321";

function runCommand(command, args, options = {}) {
  const result = spawnSync(command, args, {
    cwd: options.cwd,
    env: { ...process.env, ...(options.env || {}) },
    stdio: "pipe",
    windowsHide: true,
    encoding: "utf8"
  });
  if (result.status !== 0) {
    throw new Error((result.stderr || result.stdout || "Falha ao executar comando").trim());
  }
  return result.stdout;
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

async function api(page, url, body) {
  return page.evaluate(async ({ url: apiUrl, body: payload }) => {
    const response = await fetch(apiUrl, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json"
      },
      credentials: "include",
      body: JSON.stringify(payload)
    });
    const data = await response.json().catch(() => ({}));
    return { ok: response.ok, status: response.status, data };
  }, { url, body });
}

await fs.mkdir(outputDir, { recursive: true });
runCommand("php", ["bin/console", "app:seed-runtime-metadata", "--no-interaction"], {
  cwd: path.join(repoRoot, "backend")
});
runCommand("php", [
  "bin/console",
  "app:subscriber:create",
  "--code=default",
  "--name=Principal",
  "--admin-username=" + username,
  "--admin-display-name=Usuario Auth E2E",
  "--admin-email=auth-e2e@example.com",
  "--admin-password-env=CONSTRUTOR_PG_ADMIN_PASSWORD"
], {
  cwd: path.join(repoRoot, "backend"),
  env: {
    CONSTRUTOR_PG_ADMIN_PASSWORD: initialPassword
  }
});

const backendServer = spawnProcess("php", ["-S", "127.0.0.1:8011", "public/index.php"], {
  cwd: path.join(repoRoot, "backend"),
  env: {
    APP_AUTH_EXPOSE_RESET_TOKEN: "1"
  }
});
const staticServer = spawnProcess("node", ["scripts/serve-static.js", "8771"], {
  cwd: repoRoot,
  env: {
    CRUD_ENGINE_API_PROXY: backendUrl
  }
});

const browser = await chromium.launch({ headless: true });
const pageErrors = [];

try {
  await waitForHttp(backendUrl + "/api/auth/providers", 30000);
  await waitForHttp("http://127.0.0.1:8771/production/login.html", 30000);

  const page = await browser.newPage({ viewport: { width: 1280, height: 860 } });
  page.on("pageerror", (error) => {
    pageErrors.push(String(error && error.message || error));
  });

  await page.goto(frontendUrl);
  await page.waitForSelector("#login-user", { timeout: 15000 });
  await page.locator("#login-user").fill(username);
  await page.locator("#login-password").fill(initialPassword);
  await page.locator("#login-submit").click();
  await page.waitForSelector(".k-window:has-text('Area principal')", { timeout: 20000 });
  await page.getByRole("button", { name: "Area principal" }).click();
  await page.waitForURL(/production\/home\.html\?screenId=home/, { timeout: 20000 });

  const tokenAfterLogin = await page.evaluate(() => window.localStorage.getItem("crudEngine.authToken") || "");
  if (!tokenAfterLogin) {
    throw new Error("Login real nao gravou authToken local.");
  }

  const resetRequest = await api(page, "/api/auth/password/request-reset", { identity: "auth-e2e@example.com" });
  if (!resetRequest.ok || !resetRequest.data.resetToken) {
    throw new Error("Reset real nao retornou token em modo e2e explicito.");
  }

  const resetResponse = await api(page, "/api/auth/password/reset", {
    resetToken: resetRequest.data.resetToken,
    password: resetPassword
  });
  if (!resetResponse.ok) {
    throw new Error("Reset real falhou: " + JSON.stringify(resetResponse));
  }

  const oldLogin = await api(page, "/api/auth/login", {
    username,
    password: initialPassword,
    remember: false
  });
  if (oldLogin.ok) {
    throw new Error("Senha antiga continuou autenticando apos reset real.");
  }

  const newLogin = await api(page, "/api/auth/login", {
    username,
    password: resetPassword,
    remember: false
  });
  if (!newLogin.ok || !newLogin.data.token || !newLogin.data.session) {
    throw new Error("Senha nova nao autenticou apos reset real.");
  }

  if (pageErrors.length) {
    throw new Error("Erros JavaScript detectados: " + pageErrors.join(" | "));
  }

  const result = {
    uiLoginStoredToken: Boolean(tokenAfterLogin),
    resetTokenExposedOnlyForE2eFlag: Boolean(resetRequest.data.resetToken),
    oldPasswordRejected: !oldLogin.ok,
    newPasswordAccepted: newLogin.ok
  };
  await fs.writeFile(path.join(outputDir, "auth-production-e2e-result.json"), JSON.stringify(result, null, 2), "utf8");
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
  staticServer.kill("SIGTERM");
  backendServer.kill("SIGTERM");
}
