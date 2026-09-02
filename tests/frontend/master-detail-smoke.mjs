import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const pageUrl = pathToFileURL(path.join(root, "examples", "pages", "pedido-venda-master-detail.html")).toString();

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
await page.waitForSelector(".master-detail-screen");
await page.waitForSelector(".master-detail-tabs li[role='tab']");

const tabLabels = await page.locator(".master-detail-tabs li[role='tab']").allTextContents();
const masterRows = await page.locator(".master-detail-master-grid .k-grid-content tbody tr").count();
const detailGridCount = await page.locator(".master-detail-detail-grid").count();

if (!tabLabels.some((label) => label.includes("Itens"))) {
  throw new Error("Aba de itens nao foi renderizada.");
}
if (!tabLabels.some((label) => label.includes("Parcelas"))) {
  throw new Error("Aba de parcelas nao foi renderizada.");
}
if (masterRows < 1) {
  throw new Error("Grid mestre nao renderizou pedidos.");
}
if (detailGridCount < 2) {
  throw new Error("Nao renderizou os grids filhos esperados.");
}

await page.evaluate(() => {
  const engine = window.currentMasterDetailExampleEngine;
  const idField = engine.definition.master.idField;
  const pedidoId = engine.masterRecords[0][idField];
  const detalheItens = engine.definition.details.find((detail) => detail.id === "itens");
  const detalheParcelas = engine.definition.details.find((detail) => detail.id === "parcelas");
  engine.setSelectedMaster(pedidoId);
  engine.saveDetail(detalheItens, "create", null, {
    produto: "Servico smoke",
    quantidade: 1,
    valor_unitario: 99.9,
    valor_total: 99.9
  });
  engine.saveDetail(detalheParcelas, "create", null, {
    numero: 99,
    vencimento: "2026-06-30",
    valor: 99.9,
    situacao: "ABERTA"
  });
});

await page.waitForFunction(() => {
  const engine = window.currentMasterDetailExampleEngine;
  const pedidoId = engine.selectedMasterId;
  return engine.detailRecords.itens.some((item) => item.pedido_id === pedidoId && item.produto === "Servico smoke")
    && engine.detailRecords.parcelas.some((parcela) => parcela.pedido_id === pedidoId && parcela.numero === 99);
});

const deniedWriteActions = await page.evaluate(() => {
  const engine = window.currentMasterDetailExampleEngine;
  engine.definition.permissions = {
    read: true,
    create: false,
    edit: false,
    delete: false
  };
  engine.render();
  return {
    masterActionButtons: document.querySelectorAll(".master-detail-actions button").length,
    detailActionButtons: document.querySelectorAll(".master-detail-detail-toolbar button").length,
    gridCommandButtons: document.querySelectorAll(".k-grid .k-command-cell button").length
  };
});

if (deniedWriteActions.masterActionButtons !== 0 || deniedWriteActions.detailActionButtons !== 0 || deniedWriteActions.gridCommandButtons !== 0) {
  throw new Error("A engine exibiu acoes de escrita sem permissao: " + JSON.stringify(deniedWriteActions));
}

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}

console.log("master detail smoke ok");
