import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const repoRoot = "C:/construtor-pg";
const pageUrl = "file:///C:/construtor-pg/login.html";
const outputDir = path.join(repoRoot, "tmp");

async function ensureOutputDir() {
  await fs.mkdir(outputDir, { recursive: true });
}

const browser = await chromium.launch({ headless: true });
try {
  await ensureOutputDir();
  const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
  const result = {
    rememberedUserLoaded: "",
    clearedUser: "",
    sessionCleared: false
  };

  await page.addInitScript(() => {
    localStorage.setItem("crudEngine.lastUsername", "analista.demo");
    localStorage.setItem("crudEngine.currentSubscriber", JSON.stringify({ id: "empresa-a", name: "Empresa A" }));
    localStorage.setItem("crudEngine.accessArea", "admin");
    localStorage.setItem("homeEngine.home.navigationState", JSON.stringify({ currentProgramId: "clientes-crud" }));
    localStorage.setItem("importExportAdmin.import-export-admin.state", JSON.stringify({ currentMappingCode: "clientes_txt_sped" }));
  });
  await page.goto(pageUrl);
  await page.waitForSelector("#login-user", { timeout: 15000 });
  result.rememberedUserLoaded = await page.locator("#login-user").inputValue();
  await page.getByRole("button", { name: "Limpar sessao local" }).click();
  await page.waitForTimeout(200);
  result.clearedUser = await page.locator("#login-user").inputValue();
  result.sessionCleared = await page.evaluate(() => {
    return !localStorage.getItem("crudEngine.currentSubscriber") &&
      !localStorage.getItem("crudEngine.accessArea") &&
      !localStorage.getItem("crudEngine.lastUsername") &&
      !localStorage.getItem("homeEngine.home.navigationState") &&
      !localStorage.getItem("importExportAdmin.import-export-admin.state");
  });
  await page.screenshot({ path: path.join(outputDir, "login-demo-smoke.png"), fullPage: true });
  await fs.writeFile(path.join(outputDir, "login-demo-smoke-result.json"), JSON.stringify(result, null, 2), "utf8");
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
}
