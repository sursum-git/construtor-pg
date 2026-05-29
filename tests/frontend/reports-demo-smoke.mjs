import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const operationalUrl = pathToFileURL(path.join(root, "examples", "pages", "report-operacional.html")).toString();
const analyticUrl = pathToFileURL(path.join(root, "examples", "pages", "report-analitico.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(operationalUrl);
await page.waitForSelector(".report-table tbody tr");
const operationalRows = await page.locator(".report-table tbody tr").count();
const operationalPdfButtons = await page.locator(".report-toolbar-actions .k-button:has-text('PDF')").count();

await page.evaluate(async () => {
  const engine = window.currentReportExampleEngine;
  const widget = engine.parameterWidgets.status;
  if (widget && typeof widget.value === "function") {
    widget.value("INATIVO");
  }
  await engine.runReport();
});

await page.waitForFunction(() => {
  const engine = window.currentReportExampleEngine;
  return engine && engine.currentResult && engine.currentResult.rows.every((row) => row.status === "INATIVO");
});

await page.goto(analyticUrl);
await page.waitForSelector(".report-group");
const analyticGroups = await page.locator(".report-group").count();
const analyticTotals = await page.locator(".report-total-card").count();

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (operationalRows < 1) {
  throw new Error("Relatorio operacional nao renderizou linhas.");
}
if (operationalPdfButtons < 1) {
  throw new Error("Relatorio operacional nao exibiu acao PDF.");
}
if (analyticGroups < 1) {
  throw new Error("Relatorio analitico nao renderizou grupos.");
}
if (analyticTotals < 1) {
  throw new Error("Relatorio analitico nao renderizou totais.");
}

console.log("reports demo smoke ok");
