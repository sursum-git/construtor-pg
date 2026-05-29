import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const target = pathToFileURL(path.join(root, "examples", "pages", "admin-analytics-pipelines.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(target);
await page.waitForSelector(".k-grid", { timeout: 15000 });
await page.getByRole("button", { name: "Executar" }).click();
await page.waitForFunction(() => {
  const pre = document.querySelector(".program-builder-json-preview");
  return pre && pre.textContent.includes("latestExecution");
}, null, { timeout: 10000 });

await page.getByRole("button", { name: "Publicar" }).click();
await page.waitForFunction(() => {
  const blocks = Array.from(document.querySelectorAll(".program-builder-json-preview"));
  return blocks.some((node) => node.textContent.includes("\"versions\""));
}, null, { timeout: 10000 });

await page.locator("input[type='number']").last().fill("1");
await page.getByRole("button", { name: "Rollback" }).click();
await page.waitForTimeout(300);

const rows = await page.locator(".k-grid tbody tr").count();
await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (rows < 1) {
  throw new Error("Admin de pipelines analytics nao listou registros.");
}

console.log("admin analytics pipelines smoke ok");
