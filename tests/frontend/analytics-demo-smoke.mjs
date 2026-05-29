import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const target = pathToFileURL(path.join(root, "examples", "pages", "analytics-bi.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1366, height: 860 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(target);
await page.waitForSelector(".analytics-grid .k-grid-content");
await page.waitForSelector(".analytics-chart", { state: "attached" });

const initialRows = await page.locator(".analytics-grid .k-grid-content tr").count();
const tabCount = await page.locator(".analytics-tabs li[role='tab']").count();
const dashboardKpis = await page.locator(".analytics-kpi strong").count();

await page.evaluate(async () => {
  const engine = window.currentAnalyticsExampleEngine;
  engine.parameterWidgets.status.value("INATIVO");
  await engine.runQuery();
});

await page.waitForFunction(() => {
  const engine = window.currentAnalyticsExampleEngine;
  return engine && engine.currentResult && engine.currentResult.data.every((row) => row.status === "INATIVO");
});

const filteredStatuses = await page.evaluate(() => {
  return window.currentAnalyticsExampleEngine.currentResult.data.map((row) => row.status);
});

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (initialRows < 1) {
  throw new Error("Grid analytics nao renderizou linhas.");
}
if (tabCount < 4) {
  throw new Error(`Esperava 4 abas analytics, recebeu ${tabCount}.`);
}
if (dashboardKpis < 2) {
  throw new Error(`Esperava KPIs no dashboard, recebeu ${dashboardKpis}.`);
}
if (!filteredStatuses.length || filteredStatuses.some((status) => status !== "INATIVO")) {
  throw new Error("Filtro de status analytics nao foi aplicado.");
}

console.log("analytics demo smoke ok");
