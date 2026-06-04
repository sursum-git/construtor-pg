import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const pageUrl = pathToFileURL(path.join(root, "examples", "pages", "consulta-basica-lite.html")).toString();

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
await page.waitForSelector(".crud-lite-table tbody tr");

const initialRows = await page.locator(".crud-lite-table tbody tr[data-lite-row-id]").count();
await page.getByRole("button", { name: /Filtros/ }).click();
await page.locator("[data-lite-filter-field='status'] select[data-lite-filter-value]").selectOption("INATIVO");
await page.getByRole("button", { name: "Filtrar" }).click();
await page.waitForFunction(() => {
  return Array.from(document.querySelectorAll(".crud-lite-table tbody tr[data-lite-row-id]")).every((row) => {
    return row.textContent.includes("Inativo") || row.textContent.includes("INATIVO");
  });
});

await page.getByRole("button", { name: "Novo" }).click();
await page.locator("[data-lite-field='nome']").fill("Cliente Lite Smoke");
await page.locator("[data-lite-field='email']").fill("lite-smoke@example.test");
await page.locator("[data-lite-field='status']").selectOption("ATIVO");
await page.locator("[data-lite-field='tipo_pessoa']").selectOption("PJ");
await page.getByRole("button", { name: "Confirmar" }).click();
await page.waitForSelector(".crud-lite-toast-success, .crud-lite-toast-info");

await page.getByRole("button", { name: /Filtros/ }).click();
await page.getByRole("button", { name: "Limpar" }).click();
await page.waitForFunction(() => {
  return Array.from(document.querySelectorAll(".crud-lite-table tbody tr[data-lite-row-id]")).some((row) => {
    return row.textContent.includes("Cliente Lite Smoke");
  });
});

const rowsAfterCreate = await page.locator(".crud-lite-table tbody tr[data-lite-row-id]").count();
const createdRow = page.locator(".crud-lite-table tbody tr[data-lite-row-id]", { hasText: "Cliente Lite Smoke" }).first();
const editButtonClass = await createdRow.locator("[data-lite-row-action='edit']").getAttribute("class");
const deleteButtonClass = await createdRow.locator("[data-lite-row-action='delete']").getAttribute("class");

if (!editButtonClass || !editButtonClass.includes("crud-lite-row-action-edit")) {
  throw new Error("Botao de editar Lite nao recebeu classe visual propria.");
}
if (!deleteButtonClass || !deleteButtonClass.includes("crud-lite-row-action-delete")) {
  throw new Error("Botao de excluir Lite nao recebeu classe visual propria.");
}

await createdRow.locator("[data-lite-row-action='edit']").click();
await page.waitForSelector(".crud-lite-form-dialog");
const editTitle = await page.locator(".crud-lite-form-dialog .crud-lite-dialog-header h2").textContent();
if (editTitle !== "Editar Cliente") {
  throw new Error("Botao de editar Lite nao abriu o formulario de edicao.");
}

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}
if (initialRows < 1) {
  throw new Error("CRUD Lite nao renderizou linhas iniciais.");
}
if (rowsAfterCreate < 1) {
  throw new Error("CRUD Lite nao renderizou linhas apos inclusao.");
}

console.log("crud lite smoke ok");
