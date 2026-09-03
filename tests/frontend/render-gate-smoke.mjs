import { createServer } from "node:http";
import { readFile } from "node:fs/promises";
import path from "node:path";
import { chromium } from "playwright";

const root = process.cwd();
const screenId = "vendas.render-gate";
const crudScreenId = "cadastros.render-gate";
const crudErrorScreenId = "cadastros.render-gate-erro";
const missingScreenId = "falha.render-gate";

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function waitFor(promise, label, requests, timeout = 5000) {
  return Promise.race([
    promise,
    new Promise((_, reject) => {
      setTimeout(() => reject(new Error("Timeout aguardando " + label + ". Rotas chamadas: " + JSON.stringify(requests))), timeout);
    })
  ]);
}

async function waitForPageSignal(promise, label, requests, page, errors, timeout = 5000) {
  try {
    return await waitFor(promise, label, requests, timeout);
  } catch (error) {
    let pageState = {};
    try {
      pageState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
        state: rootElement.getAttribute("data-render-state"),
        busy: rootElement.getAttribute("aria-busy"),
        visibility: getComputedStyle(rootElement).visibility,
        text: rootElement.innerText
      }));
    } catch (_) {
      pageState = { unavailable: true };
    }
    throw new Error(error.message + ". Estado da pagina: " + JSON.stringify(pageState) + ". Console: " + JSON.stringify(errors));
  }
}

function startStaticServer() {
  const requests = [];
  let detailStartedResolve;
  const detailStarted = new Promise((resolve) => {
    detailStartedResolve = resolve;
  });
  let crudReadStartedResolve;
  const crudReadStarted = new Promise((resolve) => {
    crudReadStartedResolve = resolve;
  });
  let crudErrorReadStartedResolve;
  const crudErrorReadStarted = new Promise((resolve) => {
    crudErrorReadStartedResolve = resolve;
  });
  let definitionStartedResolve;
  const definitionStarted = new Promise((resolve) => {
    definitionStartedResolve = resolve;
  });
  const mime = {
    ".css": "text/css; charset=utf-8",
    ".html": "text/html; charset=utf-8",
    ".js": "application/javascript; charset=utf-8",
    ".json": "application/json; charset=utf-8"
  };
  const server = createServer(async (request, response) => {
    const pathname = decodeURIComponent(new URL(request.url || "/", "http://127.0.0.1").pathname);
    const method = String(request.method || "GET").toUpperCase();
    requests.push(method + " " + pathname);
    const runtimePath = "/api/runtime/screens/" + encodeURIComponent(screenId);
    const crudRuntimePath = "/api/runtime/screens/" + encodeURIComponent(crudScreenId);
    const crudErrorRuntimePath = "/api/runtime/screens/" + encodeURIComponent(crudErrorScreenId);
    const missingRuntimePath = "/api/runtime/screens/" + encodeURIComponent(missingScreenId);
    const endpointPath = runtimePath + "/endpoints/";
    const crudEndpointPath = crudRuntimePath + "/endpoints/";
    const crudErrorEndpointPath = crudErrorRuntimePath + "/endpoints/";
    if (method === "GET" && pathname === missingRuntimePath) {
      definitionStartedResolve();
      await new Promise((resolve) => setTimeout(resolve, 1200));
      response.writeHead(500, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify({ error: { code: "SCREEN_ERROR", message: "Tela indisponivel." } }));
      return;
    }
    if (method === "GET" && pathname === crudRuntimePath) {
      response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify(crudDefinition));
      return;
    }
    if (method === "GET" && pathname === crudErrorRuntimePath) {
      response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify(crudErrorDefinition));
      return;
    }
    if (method === "POST" && pathname === crudEndpointPath + "read") {
      crudReadStartedResolve();
      await new Promise((resolve) => setTimeout(resolve, 3000));
      response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify({ data: [{ id: 1, nome: "Produto pronto" }], total: 1 }));
      return;
    }
    if (method === "POST" && pathname === crudErrorEndpointPath + "read") {
      crudErrorReadStartedResolve();
      await new Promise((resolve) => setTimeout(resolve, 300));
      response.writeHead(500, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify({ error: { code: "READ_ERROR", message: "Falha na leitura inicial." } }));
      return;
    }
    if (method === "GET" && pathname === runtimePath) {
      response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify(definition));
      return;
    }
    if (method === "GET" && pathname === "/api/runtime/literals/pt-BR") {
      response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify({ data: {} }));
      return;
    }
    if (method === "POST" && pathname === endpointPath + "master.get") {
      response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify({ id: 101, numero: "PV-101", cliente: "Cliente teste" }));
      return;
    }
    if (method === "POST" && pathname === endpointPath + "detail.pedido_item.read") {
      detailStartedResolve();
      await new Promise((resolve) => setTimeout(resolve, 3000));
      response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
      response.end(JSON.stringify({ data: [{ id: 1, pedido_id: 101, produto: "Produto liberado" }] }));
      return;
    }
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
      resolve({
        server,
        baseUrl: "http://127.0.0.1:" + address.port,
        detailStarted,
        crudReadStarted,
        crudErrorReadStarted,
        definitionStarted
        , requests
      });
    });
  });
}

const crudDefinition = {
  schemaVersion: "1.0",
  pageType: "crud",
  id: crudScreenId,
  module: "cadastros",
  entity: "produto",
  title: "CRUD Render Gate",
  screenId: crudScreenId,
  program: {
    id: crudScreenId,
    module: "cadastros",
    entity: "produto",
    title: "CRUD Render Gate",
    subtitle: "Teste de leitura inicial"
  },
  permissions: {
    read: true,
    create: false,
    edit: false,
    delete: false
  },
  api: {
    read: { endpointId: "read", method: "POST" }
  },
  dataModel: {
    entity: "produto",
    primaryKey: "id",
    fields: {
      id: { label: "ID", type: "integer" },
      nome: { label: "Nome", type: "string" }
    }
  },
  query: {
    defaultPageSize: 20
  },
  grid: {
    id: "crudRenderGateGrid",
    pageable: true,
    sortable: true,
    columns: [
      { field: "nome", title: "Nome" }
    ]
  },
  form: {
    id: "crud-render-gate-form",
    sections: [
      {
        id: "geral",
        title: "Geral",
        fields: [
          { field: "nome", label: "Nome" }
        ]
      }
    ]
  }
};

const crudErrorDefinition = JSON.parse(JSON.stringify(crudDefinition));
crudErrorDefinition.id = crudErrorScreenId;
crudErrorDefinition.screenId = crudErrorScreenId;
crudErrorDefinition.program.id = crudErrorScreenId;

const definition = {
  pageType: "master_detail",
  screenId,
  program: {
    title: "Render Gate",
    subtitle: "Teste de abertura apos todos os objetos"
  },
  permissions: {
    read: true,
    create: true,
    edit: true,
    delete: true
  },
  master: {
    id: "pedido",
    entity: "pedido",
    title: "Pedido",
    idField: "id",
    fields: [
      { id: "id", label: "ID", type: "integer", hidden: true },
      { id: "numero", label: "Numero", type: "string" },
      { id: "cliente", label: "Cliente", type: "string" }
    ],
    api: {
      get: { endpointId: "master.get", method: "POST" }
    }
  },
  details: [
    {
      id: "itens",
      entity: "pedido_item",
      title: "Itens",
      singularTitle: "item",
      idField: "id",
      parentField: "pedido_id",
      fields: [
        { id: "id", label: "ID", type: "integer", hidden: true },
        { id: "pedido_id", label: "Pedido", type: "integer", hidden: true },
        { id: "produto", label: "Produto", type: "string" }
      ],
      grid: {
        columns: [
          { field: "produto", title: "Produto" }
        ]
      },
      api: {
        read: { endpointId: "detail.pedido_item.read", method: "POST" }
      }
    }
  ]
};

const { server, baseUrl, detailStarted, crudReadStarted, crudErrorReadStarted, definitionStarted, requests } = await startStaticServer();
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
page.setDefaultTimeout(15000);
const errors = [];

page.on("pageerror", (error) => errors.push(String(error.message || error)));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

try {
  const bootstrapNavigation = page.goto(baseUrl + "/production/app.html?screenId=" + missingScreenId, { waitUntil: "domcontentloaded" });
  await waitForPageSignal(definitionStarted, "inicio do carregamento da definicao com erro", requests, page, errors);
  const bootstrapLoadingState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
    state: rootElement.getAttribute("data-render-state"),
    busy: rootElement.getAttribute("aria-busy"),
    visibility: getComputedStyle(rootElement).visibility
  }));
  assert(bootstrapLoadingState.state === "loading", "Bootstrap deveria marcar loading antes de criar a engine: " + JSON.stringify(bootstrapLoadingState));
  assert(bootstrapLoadingState.busy === "true", "Bootstrap deveria marcar aria-busy=true antes de criar a engine.");
  assert(bootstrapLoadingState.visibility === "hidden", "Bootstrap nao deve exibir o root enquanto metadata ainda carrega.");
  await bootstrapNavigation;
  await page.waitForSelector("#crud-production-root[data-render-state='error']");
  const bootstrapErrorState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
    state: rootElement.getAttribute("data-render-state"),
    busy: rootElement.getAttribute("aria-busy"),
    visibility: getComputedStyle(rootElement).visibility,
    text: rootElement.innerText
  }));
  assert(bootstrapErrorState.busy === "false", "Bootstrap deveria limpar aria-busy em erro.");
  assert(bootstrapErrorState.visibility !== "hidden", "Erro de bootstrap deve ficar visivel.");
  assert(bootstrapErrorState.text.includes("Tela indisponivel."), "Erro de bootstrap deveria renderizar a mensagem segura.");
  errors.length = 0;

  const crudErrorNavigation = page.goto(baseUrl + "/production/app.html?screenId=" + crudErrorScreenId, { waitUntil: "domcontentloaded" });
  await waitForPageSignal(crudErrorReadStarted, "inicio da leitura CRUD com erro", requests, page, errors);
  await crudErrorNavigation;
  await page.waitForSelector("#crud-production-root[data-render-state='error']");
  const crudErrorState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
    state: rootElement.getAttribute("data-render-state"),
    busy: rootElement.getAttribute("aria-busy"),
    visibility: getComputedStyle(rootElement).visibility,
    text: rootElement.innerText
  }));
  assert(crudErrorState.busy === "false", "CRUD deveria limpar aria-busy quando a leitura inicial falha.");
  assert(crudErrorState.visibility !== "hidden", "Erro da leitura inicial deve ficar visivel.");
  assert(
    crudErrorState.text.includes("Falha na leitura inicial."),
    "Falha inicial do CRUD deveria renderizar UI de erro do motor: " + JSON.stringify(crudErrorState)
  );
  errors.length = 0;

  const crudNavigation = page.goto(baseUrl + "/production/app.html?screenId=" + crudScreenId, { waitUntil: "domcontentloaded" });
  await waitForPageSignal(crudReadStarted, "inicio da leitura CRUD", requests, page, errors);
  await page.waitForSelector(".crud-screen", { state: "attached" });
  const crudLoadingState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
    state: rootElement.getAttribute("data-render-state"),
    busy: rootElement.getAttribute("aria-busy"),
    visibility: getComputedStyle(rootElement).visibility,
    text: rootElement.innerText
  }));
  assert(crudLoadingState.state === "loading", "CRUD deveria continuar loading enquanto a leitura inicial do grid esta pendente: " + JSON.stringify(crudLoadingState));
  assert(crudLoadingState.visibility === "hidden", "CRUD nao deve exibir grid antes da primeira leitura terminar.");
  assert(!crudLoadingState.text.includes("Produto pronto"), "Registro do CRUD ainda nao deveria estar disponivel antes da liberacao.");
  await crudNavigation;
  await page.waitForSelector("#crud-production-root[data-render-state='ready']");
  await page.getByText("Produto pronto").waitFor();
  const crudReadyState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
    state: rootElement.getAttribute("data-render-state"),
    busy: rootElement.getAttribute("aria-busy"),
    visibility: getComputedStyle(rootElement).visibility
  }));
  assert(crudReadyState.state === "ready", "CRUD deveria ficar ready apos primeira leitura.");
  assert(crudReadyState.busy === "false", "CRUD deveria limpar aria-busy apos primeira leitura.");
  assert(crudReadyState.visibility !== "hidden", "CRUD deveria ficar visivel apos primeira leitura.");

  const navigation = page.goto(baseUrl + "/production/app.html?screenId=" + screenId + "&masterId=101", { waitUntil: "domcontentloaded" });
  await waitForPageSignal(detailStarted, "inicio da leitura mestre-detalhe", requests, page, errors);

  const loadingState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
    state: rootElement.getAttribute("data-render-state"),
    busy: rootElement.getAttribute("aria-busy"),
    visibility: getComputedStyle(rootElement).visibility,
    visibleText: rootElement.innerText
  }));

  assert(loadingState.state === "loading", "Root deveria permanecer em loading enquanto detalhes ainda carregam: " + JSON.stringify(loadingState));
  assert(loadingState.busy === "true", "Root deveria marcar aria-busy=true enquanto carrega.");
  assert(loadingState.visibility === "hidden", "Tela nao deve ficar visivel antes de todos os objetos estarem prontos.");
  assert(!loadingState.visibleText.includes("Produto liberado"), "Detalhe ainda nao deveria estar disponivel antes da liberacao.");

  await navigation;
  await page.waitForSelector("#crud-production-root[data-render-state='ready']");
  await page.waitForSelector(".master-detail-detail-grid");

  const readyState = await page.locator("#crud-production-root").evaluate((rootElement) => ({
    state: rootElement.getAttribute("data-render-state"),
    busy: rootElement.getAttribute("aria-busy"),
    visibility: getComputedStyle(rootElement).visibility,
    text: rootElement.innerText
  }));

  assert(readyState.state === "ready", "Root deveria ficar ready apos carregar todos os objetos.");
  assert(readyState.busy === "false", "Root deveria marcar aria-busy=false apos carregamento.");
  assert(readyState.visibility !== "hidden", "Tela deveria ficar visivel apos todos os objetos carregarem.");
  assert(readyState.text.includes("Produto liberado"), "Detalhe carregado deveria aparecer apos liberar a renderizacao.");
  assert(errors.length === 0, "Erros de pagina ou console: " + errors.join(" | "));
  console.log("render gate smoke ok");
} finally {
  await browser.close();
  server.closeAllConnections();
  await new Promise((resolve) => server.close(resolve));
}
