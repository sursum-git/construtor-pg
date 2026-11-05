import { chromium } from "playwright";

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") errors.push(message.text());
});

await page.goto("http://127.0.0.1:8000/email-analytics.html");
await page.waitForSelector("#grid .k-master-row", { timeout: 30000 });
const rows = await page.locator("#grid .k-master-row").count();
const date = await page.locator("#grid .k-master-row td").first().textContent();
await page.locator("#grid .k-master-row").first().dblclick();
await page.waitForSelector("#email-event-detail", { timeout: 10000 });
const detailVisible = await page.locator("#email-event-detail").isVisible();

await browser.close();

if (errors.length) throw new Error("Erros no console: " + errors.join(" | "));
if (rows < 1) throw new Error("Grid inicial de e-mails nao trouxe registros do dia.");
if (!/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/.test(date?.trim() ?? "")) throw new Error("Data do grid nao esta em formato brasileiro.");
if (!detailVisible) throw new Error("Duplo clique nao abriu o detalhe do evento.");

console.log("email analytics smoke ok");
