import { chromium } from "playwright";

const appUrl = "http://127.0.0.1:8765/production/app.html?screenId=cadastros.tipo-produto";

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function waitForGrid(page) {
  await page.waitForFunction(() => {
    return Boolean(document.querySelector(".k-grid")) &&
      document.body.innerText.includes("Incluir");
  }, null, { timeout: 30000 });
}

async function createRecord(page, description) {
  await page.getByRole("button", { name: "Incluir" }).click({ timeout: 10000 });
  await page.waitForSelector(".k-window .crud-form", { timeout: 15000 });
  await page.locator(".k-window .crud-form [name='descricao']").fill(description, { timeout: 10000 });

  const responsePromise = page.waitForResponse((response) => {
    return response.url().includes("/api/runtime/screens/cadastros.tipo-produto/endpoints/create");
  }, { timeout: 30000 });

  await page.locator(".k-window .crud-form-footer-appbar button", { hasText: "Confirmar" }).click({ timeout: 10000 });
  await responsePromise;
  await page.waitForFunction(() => !document.querySelector(".k-window .crud-form"), null, { timeout: 30000 });
  await page.waitForFunction((text) => document.body.innerText.includes(text), description, { timeout: 30000 });
}

async function cleanupRecord(page, description) {
  await page.evaluate(async (text) => {
    await fetch("/api/runtime/screens/cadastros.tipo-produto/endpoints/read", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ page: 1, pageSize: 500 })
    }).then((response) => response.json()).then(async (payload) => {
      const rows = Array.isArray(payload && payload.data) ? payload.data : [];
      for (const row of rows.filter((item) => item && item.descricao === text)) {
        if (row && row.id != null) {
          await fetch("/api/runtime/screens/cadastros.tipo-produto/endpoints/delete", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: row.id, record: row })
          });
        }
      }
    }).catch(() => {});
  }, description);
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
const errors = [];
const marker = "Smoke exclusao linha " + Date.now();

page.on("pageerror", (error) => errors.push(error.message));

try {
  await page.goto(appUrl, { waitUntil: "load", timeout: 30000 });
  await waitForGrid(page);
  await createRecord(page, marker);

  const row = page.locator(".k-grid tbody tr", { hasText: marker }).first();
  await row.waitFor({ state: "visible", timeout: 15000 });
  await row.locator(".crud-row-actions-toggle").click({ timeout: 10000 });
  await page.waitForSelector(".crud-row-actions-popup .crud-row-action[data-action='delete']", { timeout: 10000 });
  await page.locator(".crud-row-actions-popup .crud-row-action[data-action='delete']").click({ timeout: 10000 });

  await page.waitForSelector(".crud-confirm-content", { timeout: 15000 });
  const confirmText = await page.locator(".crud-confirm-content").innerText({ timeout: 10000 });

  assert(confirmText.includes("Deseja excluir este registro?"), "Clique em Excluir na linha nao abriu confirmacao de exclusao.");
  assert(confirmText.includes("Excluir"), "Confirmacao de exclusao deve exibir botao Excluir.");
  assert(errors.length === 0, "Erros JavaScript detectados: " + errors.join(" | "));
  const deleteResponsePromise = page.waitForResponse((response) => {
    return response.url().includes("/api/runtime/screens/cadastros.tipo-produto/endpoints/delete");
  }, { timeout: 30000 });
  await page.locator(".crud-confirm-content button", { hasText: "Excluir" }).click({ timeout: 10000 });
  await deleteResponsePromise;
  await page.waitForFunction((text) => !document.body.innerText.includes(text), marker, { timeout: 30000 });
  console.log(JSON.stringify({ marker, confirmText }, null, 2));
} finally {
  await cleanupRecord(page, marker);
  await browser.close();
}
