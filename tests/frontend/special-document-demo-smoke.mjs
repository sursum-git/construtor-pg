import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const pageUrl = pathToFileURL(path.join(root, "examples", "pages", "special-document-base.html")).toString();
const boletoUrl = pathToFileURL(path.join(root, "examples", "pages", "special-document-boleto.html")).toString();
const labelUrl = pathToFileURL(path.join(root, "examples", "pages", "special-document-label.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(pageUrl);
await page.waitForSelector(".report-summary-card");
await page.waitForSelector(".report-parameters .report-field");
await page.waitForSelector(".report-table tbody tr");
await page.waitForSelector(".special-document-barcode");
const title = await page.locator(".report-header h1").textContent();
const sections = await page.locator(".report-group").count();
const rows = await page.locator(".report-table tbody tr").count();
const pdfButtons = await page.locator(".report-toolbar-actions .k-button:has-text('PDF')").count();
const parameterFields = await page.locator(".report-parameters .report-field").count();

await page.evaluate(async () => {
  const engine = window.currentSpecialDocumentExampleEngine;
  const widget = engine.parameterWidgets.status;
  if (widget && typeof widget.value === "function") {
    widget.value("INATIVO");
  }
  await engine.runDocument();
});

await page.waitForFunction(() => {
  const engine = window.currentSpecialDocumentExampleEngine;
  return engine && engine.currentResult && engine.currentResult.table && engine.currentResult.table.rows.every((row) => row.status === "INATIVO");
});
const filteredRows = await page.locator(".report-table tbody tr").count();

await page.goto(boletoUrl);
await page.waitForSelector(".special-document-barcode");
const boletoCards = await page.locator(".special-document-card").count();

await page.goto(labelUrl);
await page.waitForSelector(".special-document-label-card");
const labelCards = await page.locator(".special-document-label-card").count();

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (!title || !title.includes("Documento especial")) {
  throw new Error("Documento especial nao renderizou cabecalho.");
}
if (sections < 1) {
  throw new Error("Documento especial nao renderizou secoes.");
}
if (rows < 1) {
  throw new Error("Documento especial nao renderizou tabela.");
}
if (filteredRows < 1) {
  throw new Error("Documento especial nao aplicou filtros por parametro.");
}
if (parameterFields < 2) {
  throw new Error("Documento especial nao exibiu parametros esperados.");
}
if (boletoCards < 2) {
  throw new Error("Documento especial boleto nao renderizou cards visuais.");
}
if (labelCards < 1) {
  throw new Error("Documento especial etiqueta nao renderizou grade de etiquetas.");
}
if (pdfButtons < 1) {
  throw new Error("Documento especial nao exibiu acao PDF.");
}

console.log("special document demo smoke ok");
