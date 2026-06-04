import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const pageUrl = pathToFileURL(path.join(root, "examples", "pages", "mestre-detalhe-paginas-ligadas.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(pageUrl);
await page.waitForSelector(".crud-grid .k-grid-content tbody tr");
await page.waitForSelector(".k-window .crud-form");

const linkedTab = page.locator(".crud-form-tabs li[role='tab']", { hasText: "Jobs do cliente" }).first();
await linkedTab.click();
await page.waitForSelector(".crud-linked-page-host .k-grid");
await page.waitForFunction(() => {
  const renderer = window.currentCrudExampleEngine && window.currentCrudExampleEngine.formRenderer;
  const entries = renderer && renderer.linkedPageEngines ? Object.values(renderer.linkedPageEngines) : [];
  const child = entries[0] && entries[0].engine;
  return child
    && child.gridRenderer
    && child.gridRenderer.grid
    && child.gridRenderer.grid.dataSource.total() >= 1;
});

const frameCount = await page.locator(".crud-linked-page-tab iframe").count();
if (frameCount !== 0) {
  throw new Error("A aba ligada nao deve renderizar iframe.");
}

const linkedState = await page.evaluate(() => {
  const renderer = window.currentCrudExampleEngine.formRenderer;
  const entries = Object.values(renderer.linkedPageEngines || {});
  const child = entries[0].engine;
  return {
    screenId: child.options.screenId,
    filterFields: child.options.initialFilters.map((filter) => filter.field),
    filterValues: child.options.initialFilters.map((filter) => String(filter.value)),
    recordIds: child.gridRenderer.grid.dataSource.view().map((row) => String(row.record_id)),
    total: child.gridRenderer.grid.dataSource.total()
  };
});

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (linkedState.screenId !== "runtime.jobs.mine") {
  throw new Error("Pagina filha nao foi carregada por screenId.");
}
if (!linkedState.filterFields.includes("record_id") || !linkedState.filterValues.includes("1")) {
  throw new Error("Filtro do registro pai nao foi aplicado na pagina filha.");
}
if (linkedState.total < 1) {
  throw new Error("Grid da pagina filha nao renderizou registros.");
}
if (linkedState.recordIds.some((recordId) => recordId !== "1")) {
  throw new Error("Grid da pagina filha exibiu registro fora do filtro do pai.");
}

console.log("linked page form tab smoke ok");
