import { execFileSync } from "node:child_process";
import { createServer } from "node:http";
import { readFile } from "node:fs/promises";
import path from "node:path";
import { chromium } from "playwright";

const root = process.cwd();
const screenId = "vendas.pedidos-builder-smoke";
const fixturePath = path.join(root, "backend", "tests", "Builder", "ProgramBuilderMasterDetailFixture.php");

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function assertActiveDetailTab(page, title, contentText) {
  await page.getByText(title, { exact: true }).click();
  const state = await page.locator(".master-detail-tabs").evaluate((element, expectedTitle) => {
    const activeTab = Array.from(element.querySelectorAll(".k-tabstrip-items li")).find((tab) =>
      tab.classList.contains("k-active") || tab.getAttribute("aria-selected") === "true"
    );
    const activeContent = Array.from(element.querySelectorAll(".k-tabstrip-content")).find((panel) =>
      panel.classList.contains("k-active") || panel.getAttribute("aria-hidden") === "false"
    );
    return {
      activeTitle: activeTab?.textContent?.trim() || "",
      activeContent: activeContent?.textContent || ""
    };
  }, title);
  assert(state.activeTitle === title, "A aba ativa deveria ser " + title + ".");
  assert(state.activeContent.includes(contentText), "O painel ativo de " + title + " nao mostrou seu conteudo.");
}

async function closedRuntimeErrorCode(page, requestUrl, method = "POST") {
  return page.evaluate(async ({ url, requestMethod }) => {
    try {
      await window.productionCrudEngine.httpClient.request({ url, method: requestMethod });
      return "REQUEST_ACCEPTED";
    } catch (error) {
      return error?.error?.code || error?.code || "UNKNOWN_ERROR";
    }
  }, { url: requestUrl, requestMethod: method });
}

function loadPublishedDefinition() {
  const output = execFileSync("php", [fixturePath], {
    cwd: root,
    encoding: "utf8",
    windowsHide: true
  });
  return JSON.parse(output);
}

function startStaticServer() {
  const mime = {
    ".css": "text/css; charset=utf-8",
    ".html": "text/html; charset=utf-8",
    ".js": "application/javascript; charset=utf-8",
    ".json": "application/json; charset=utf-8"
  };
  const server = createServer(async (request, response) => {
    const pathname = decodeURIComponent(new URL(request.url || "/", "http://127.0.0.1").pathname);
    const filePath = path.normalize(path.join(root, pathname === "/" ? "index.html" : pathname));
    if (!filePath.startsWith(root)) {
      response.writeHead(403).end();
      return;
    }
    try {
      const content = await readFile(filePath);
      response.writeHead(200, { "Content-Type": mime[path.extname(filePath)] || "application/octet-stream" });
      response.end(content);
    } catch (_) {
      response.writeHead(404).end("Not found");
    }
  });
  return new Promise((resolve) => {
    server.listen(0, "127.0.0.1", () => {
      const address = server.address();
      resolve({ server, baseUrl: "http://127.0.0.1:" + address.port });
    });
  });
}

const definition = loadPublishedDefinition();
const { server, baseUrl } = await startStaticServer();
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
page.setDefaultTimeout(15000);
const errors = [];
const dialogs = [];

page.on("pageerror", (error) => errors.push(String(error.message || error)));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});
page.on("dialog", async (dialog) => {
  dialogs.push(dialog.type());
  await dialog.dismiss();
});

await page.addInitScript(({ runtimeDefinition, runtimeScreenId }) => {
  document.addEventListener("DOMContentLoaded", () => {
    class ClosedBuilderRuntimeMock {
      constructor() {
        this.masterRecords = [{ id: 101, numero: "PV-101", cliente: "Cliente publicado" }];
        this.detailRecords = {
          pedido_item: [{ id: 1001, pedido_id: 101, produto: "Produto publicado", quantidade: 2, valor_total: 120 }],
          pedido_parcela: [{ id: 2001, pedido_id: 101, numero: 1, vencimento: "2026-09-10", valor: 120 }]
        };
        window.__programBuilderMasterDetailCreateGraphCalls = 0;
        window.__programBuilderMasterDetailRequests = [];
      }

      request(request) {
        let url;
        try {
          url = new URL(request?.url || "", window.location.href);
        } catch (_) {
          return Promise.reject({ error: { code: "RUNTIME_ENDPOINT_NOT_FOUND", message: "Endpoint fechado nao encontrado." } });
        }
        const runtimePath = "/api/runtime/screens/" + encodeURIComponent(runtimeScreenId);
        const endpointPath = runtimePath + "/endpoints/";
        const method = String(request?.method || "GET").toUpperCase();
        window.__programBuilderMasterDetailRequests.push({ method, pathname: url.pathname });
        if (url.origin !== window.location.origin || url.search || url.hash) {
          return Promise.reject({ error: { code: "RUNTIME_ENDPOINT_NOT_FOUND", message: "Endpoint fechado nao encontrado." } });
        }
        if (method === "GET" && url.pathname === runtimePath) {
          return Promise.resolve(runtimeDefinition);
        }
        if (method === "POST" && url.pathname === endpointPath + "master.read") {
          return Promise.resolve({ data: this.masterRecords });
        }
        if (method === "POST" && url.pathname === endpointPath + "master.get") {
          const id = request?.data?.id;
          return Promise.resolve(this.masterRecords.find((record) => String(record.id) === String(id)) || {});
        }
        if (method === "POST" && url.pathname === endpointPath + "detail.pedido_item.read") {
          return Promise.resolve({ data: this.detailRecords.pedido_item });
        }
        if (method === "POST" && url.pathname === endpointPath + "detail.pedido_parcela.read") {
          return Promise.resolve({ data: this.detailRecords.pedido_parcela });
        }
        if (method === "POST" && url.pathname === endpointPath + "createGraph") {
          window.__programBuilderMasterDetailCreateGraphCalls += 1;
          return Promise.reject({
            error: {
              code: "PEDIDO_GRAPH_VALIDATION",
              message: "Informe uma condicao de pagamento valida.",
              details: { fields: { condicao_pagamento: ["Condicao obrigatoria."] } }
            }
          });
        }
        return Promise.reject({ error: { code: "RUNTIME_ENDPOINT_NOT_FOUND", message: "Endpoint fechado nao encontrado." } });
      }
    }

    window.CrudHttpClient = ClosedBuilderRuntimeMock;
  }, { once: true });
}, { runtimeDefinition: definition, runtimeScreenId: screenId });

try {
  await page.setViewportSize({ width: 1366, height: 900 });
  await page.goto(baseUrl + "/production/app.html?screenId=" + screenId + "&masterId=101");
  await page.waitForSelector(".master-detail-screen");
  await page.waitForSelector(".master-detail-parent-panel");
  await page.waitForSelector(".master-detail-child-panel");
  await assertActiveDetailTab(page, "Itens", "Produto publicado");
  await assertActiveDetailTab(page, "Parcelas", "120,00");
  const openedMaster = await page.locator(".master-detail-master-form .master-detail-field").filter({ hasText: "Numero" }).locator("input, textarea").inputValue();
  const runtimeRequests = await page.evaluate(() => window.__programBuilderMasterDetailRequests);
  assert(openedMaster === "PV-101", "Formulario mestre-detalhe nao abriu o mestre solicitado pelo CRUD: " + JSON.stringify({ openedMaster, runtimeRequests }));
  const masterReadCalls = runtimeRequests.filter((item) => item.pathname.endsWith("/master.read")).length;
  assert(masterReadCalls === 0, "MasterDetailEngine nao deve consultar/listar mestres; a consulta fica no CRUD existente.");

  const rejectedRoutes = await Promise.all([
    closedRuntimeErrorCode(page, "https://nao-confiavel.test/api/runtime/screens/" + screenId + "/endpoints/master.read"),
    closedRuntimeErrorCode(page, baseUrl + "/api/runtime/screens/outra-tela/endpoints/master.read"),
    closedRuntimeErrorCode(page, baseUrl + "/api/runtime/screens/" + screenId + "/endpoints/master.read?origem=externa"),
    closedRuntimeErrorCode(page, baseUrl + "/api/runtime/screens/" + screenId + "/endpoints/master.read", "GET")
  ]);
  assert(rejectedRoutes.every((code) => code === "RUNTIME_ENDPOINT_NOT_FOUND"), "O runtime mock aceitou URL, caminho ou metodo fora da lista fechada.");

  await page.setViewportSize({ width: 768, height: 900 });
  await page.reload();
  await page.waitForSelector(".master-detail-screen");
  const boxes = await page.locator(".master-detail-panel").evaluateAll((nodes) => nodes.map((node) => {
    const box = node.getBoundingClientRect();
    return { width: box.width, height: box.height };
  }));
  assert(boxes.length === 2 && boxes.every((box) => box.width > 0 && box.height > 0), "Paineis mestre-detalhe invisiveis em largura reduzida.");

  await page.locator(".master-detail-actions").getByRole("button", { name: "Incluir" }).first().click();
  await page.waitForSelector(".master-detail-create-graph-window");
  const draftFields = page.locator(".master-detail-create-graph-window .master-detail-draft-fields .master-detail-field");
  await draftFields.filter({ hasText: "Numero" }).locator("textarea").fill("PV-ERRO");
  await draftFields.filter({ hasText: "Cliente" }).locator("textarea").fill("Cliente de validacao");
  await page.locator(".master-detail-create-graph-window").getByRole("button", { name: "Confirmar inclusao" }).click();
  await page.locator(".master-detail-create-graph-window .master-detail-validation").getByText("Informe uma condicao de pagamento valida.").waitFor();

  const result = await page.evaluate(() => ({
    createGraphCalls: window.__programBuilderMasterDetailCreateGraphCalls,
    validation: document.querySelector(".master-detail-create-graph-window .master-detail-validation")?.textContent || ""
  }));
  assert(result.createGraphCalls === 1, "A inclusao conjunta deve enviar uma unica operacao createGraph.");
  assert(result.validation.includes("Informe uma condicao de pagamento valida."), "Erro estruturado nao foi exibido na janela Kendo.");
  assert(dialogs.length === 0, "Erro estruturado abriu dialogo nativo: " + dialogs.join(", "));
  assert(errors.length === 0, "Erros de pagina ou console: " + errors.join(" | "));
  console.log("program builder master detail smoke ok");
} finally {
  await browser.close();
  server.closeAllConnections();
  await new Promise((resolve) => server.close(resolve));
}
