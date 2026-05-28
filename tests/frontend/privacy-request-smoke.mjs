import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const target = pathToFileURL(path.join(root, "examples", "pages", "privacy-request.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 820 } });
const errors = [];
page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(target);
await page.waitForSelector("#privacy-request-root .privacy-request-panel");
await page.locator("#requesterEmail").fill("titular@example.com");
await page.locator("#requesterName").fill("Titular Teste");
await page.locator("#protocol").fill("LGPD-20260528-ABC123");

const panels = await page.locator(".privacy-request-panel").count();
const title = await page.locator("h1").innerText();
const requestTypeValue = await page.locator("#requestType").inputValue();

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (panels !== 3) {
  throw new Error(`Esperava 3 paineis, recebeu ${panels}`);
}
if (title.trim() !== "Solicitacao LGPD") {
  throw new Error(`Titulo inesperado: ${title}`);
}
if (requestTypeValue !== "access") {
  throw new Error(`Tipo inicial inesperado: ${requestTypeValue}`);
}

console.log("privacy request smoke ok");
