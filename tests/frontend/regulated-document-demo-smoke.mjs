import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const fiscalUrl = pathToFileURL(path.join(root, "examples", "pages", "regulated-document-fiscal-base.html")).toString();
const bankingUrl = pathToFileURL(path.join(root, "examples", "pages", "regulated-document-banking-base.html")).toString();
const logisticsUrl = pathToFileURL(path.join(root, "examples", "pages", "regulated-document-logistics-base.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(fiscalUrl);
await page.waitForFunction(() => Boolean(window.currentRegulatedDocumentExampleEngine), null, { timeout: 30000 });
await page.evaluate(async () => {
  const engine = window.currentRegulatedDocumentExampleEngine;
  const widget = engine.parameterWidgets.status;
  if (widget && typeof widget.val === "function") {
    widget.val("ATIVO");
  }
  await engine.prepareDocument();
  await engine.renderDocument();
  await engine.issueDocument("html");
  await engine.verifyDocument();
});
await page.waitForSelector(".report-table tbody tr");
await page.waitForFunction(() => {
  const engine = window.currentRegulatedDocumentExampleEngine;
  return engine && engine.currentIssued && /^sha256:[a-f0-9]{64}$/i.test(engine.currentIssued.hash || "") && engine.currentVerification && engine.currentVerification.ok === true;
});

const fiscalResult = await page.evaluate(() => ({
  title: document.querySelector(".report-header h1") && document.querySelector(".report-header h1").textContent || "",
  rows: document.querySelectorAll(".report-table tbody tr").length,
  hash: window.currentRegulatedDocumentExampleEngine.currentIssued.hash,
  state: window.currentRegulatedDocumentExampleEngine.currentVerification.state
}));

await page.goto(bankingUrl);
await page.waitForFunction(() => Boolean(window.currentRegulatedDocumentExampleEngine), null, { timeout: 30000 });
await page.evaluate(async () => {
  const engine = window.currentRegulatedDocumentExampleEngine;
  const widget = engine.parameterWidgets.status;
  if (widget && typeof widget.val === "function") {
    widget.val("ATIVO");
  }
  await engine.prepareDocument();
  await engine.renderDocument();
});
await page.waitForSelector(".report-table tbody tr");
const bankingResult = await page.evaluate(() => ({
  rows: document.querySelectorAll(".report-table tbody tr").length,
  title: document.querySelector(".report-header h1") && document.querySelector(".report-header h1").textContent || ""
}));

await page.goto(logisticsUrl);
await page.waitForFunction(() => Boolean(window.currentRegulatedDocumentExampleEngine), null, { timeout: 30000 });
await page.evaluate(async () => {
  const engine = window.currentRegulatedDocumentExampleEngine;
  const widget = engine.parameterWidgets.status;
  if (widget && typeof widget.val === "function") {
    widget.val("ATIVO");
  }
  await engine.prepareDocument();
  await engine.renderDocument();
});
await page.waitForSelector(".report-table tbody tr");
const logisticsResult = await page.evaluate(() => ({
  rows: document.querySelectorAll(".report-table tbody tr").length,
  title: document.querySelector(".report-header h1") && document.querySelector(".report-header h1").textContent || "",
  track: Array.from(document.querySelectorAll(".regulated-document-state-card strong")).map((item) => item.textContent || "")[0] || ""
}));

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (!fiscalResult.title.includes("Documento regulado")) {
  throw new Error("Exemplo fiscal nao renderizou o cabecalho esperado.");
}
if (fiscalResult.rows < 1 || fiscalResult.state !== "verified") {
  throw new Error("Exemplo fiscal nao concluiu o ciclo prepare/render/issue/verify.");
}
if (bankingResult.rows < 1 || !bankingResult.title.includes("Documento regulado")) {
  throw new Error("Exemplo bancario nao renderizou a base esperada.");
}
if (logisticsResult.rows < 1 || logisticsResult.track.toLowerCase() !== "logistics") {
  throw new Error("Exemplo logistico nao renderizou o track analitico esperado.");
}

console.log("regulated document demo smoke ok");
