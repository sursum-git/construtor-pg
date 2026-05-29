import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const pageUrl = pathToFileURL(path.join(root, "examples", "pages", "special-document-base.html")).toString();

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
const title = await page.locator(".report-header h1").textContent();
const sections = await page.locator(".report-group").count();
const pdfButtons = await page.locator(".report-toolbar-actions .k-button:has-text('PDF')").count();

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
if (pdfButtons < 1) {
  throw new Error("Documento especial nao exibiu acao PDF.");
}

console.log("special document demo smoke ok");
