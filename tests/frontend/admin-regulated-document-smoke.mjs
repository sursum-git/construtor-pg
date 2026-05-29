import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const adminUrl = pathToFileURL(path.join(root, "examples", "pages", "admin-regulated-document.html")).toString();

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 960 } });
const page = await context.newPage();
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(adminUrl);
await page.waitForFunction(() => Boolean(window.DemoMockHttpClient && window.currentRegulatedDocumentAdmin), null, { timeout: 30000 });
const issueId = await page.evaluate(async () => {
  const client = window.currentRegulatedDocumentAdmin.httpClient;
  const issued = await client.issueRegulatedDocument("documentos.regulados-fiscal-base", {
    parameters: { status: "ATIVO" },
    format: "pdf"
  });
  await window.currentRegulatedDocumentAdmin.loadEntries();
  return issued && issued.issueId || "";
});
await page.waitForFunction(() => window.currentRegulatedDocumentAdmin && Array.isArray(window.currentRegulatedDocumentAdmin.entries) && window.currentRegulatedDocumentAdmin.entries.length > 0, null, { timeout: 30000 });
const result = await page.evaluate(() => ({
  rows: document.querySelectorAll(".program-builder-governance-list .k-grid-content tbody tr").length,
  detailText: document.querySelector(".program-builder-json-preview") && document.querySelector(".program-builder-json-preview").textContent || "",
  title: document.querySelector(".program-governance-admin-title h1") && document.querySelector(".program-governance-admin-title h1").textContent || ""
}));

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (!result.title.includes("Documentos regulados")) {
  throw new Error("Tela administrativa do modulo regulado nao abriu corretamente.");
}
if (result.rows < 1 || !result.detailText.includes(issueId)) {
  throw new Error("Tela administrativa do modulo regulado nao exibiu a emissao realizada.");
}

console.log("admin regulated document smoke ok");
