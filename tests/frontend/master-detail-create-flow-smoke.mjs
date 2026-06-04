import { chromium } from "playwright";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = process.cwd();
const parentFirstUrl = pathToFileURL(path.join(root, "examples", "pages", "pedido-venda-master-detail.html")).toString();
const draftUrl = pathToFileURL(path.join(root, "examples", "pages", "pedido-venda-master-detail-rascunho.html")).toString();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
const errors = [];

page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

await page.goto(parentFirstUrl);
await page.waitForSelector(".master-detail-screen");
const parentFirstMode = await page.evaluate(() => window.currentMasterDetailExampleEngine.getCreateFlowMode());
if (parentFirstMode !== "parentFirst") {
  throw new Error("Exemplo pai primeiro nao esta em createFlow.mode=parentFirst.");
}

await page.goto(draftUrl);
await page.waitForSelector(".master-detail-screen");
const draftMode = await page.evaluate(() => window.currentMasterDetailExampleEngine.getCreateFlowMode());
if (draftMode !== "draftWithChildren") {
  throw new Error("Exemplo de inclusao conjunta nao esta em createFlow.mode=draftWithChildren.");
}

await page.locator(".master-detail-actions").getByRole("button", { name: "Incluir" }).first().click();
await page.waitForSelector(".master-detail-create-graph-window");
await page.evaluate(() => {
  const windowWidget = window.jQuery(".master-detail-create-graph-window").data("kendoWindow");
  if (windowWidget) {
    windowWidget.close();
  }
});

await page.evaluate(async () => {
  const engine = window.currentMasterDetailExampleEngine;
  await engine.saveCreateGraph({
    numero: "PV-SMOKE",
    cliente: "Cliente smoke",
    data_emissao: "2026-06-04",
    status: "ABERTO"
  }, {
    itens: [
      {
        produto: "Item smoke",
        quantidade: 2,
        valor_unitario: 50,
        valor_total: 100
      }
    ],
    parcelas: [
      {
        numero: 1,
        vencimento: "2026-06-30",
        valor: 100,
        situacao: "ABERTA"
      }
    ]
  });
});

await page.waitForFunction(() => {
  const engine = window.currentMasterDetailExampleEngine;
  const master = engine.masterRecords.find((record) => record.numero === "PV-SMOKE");
  if (!master) {
    return false;
  }
  return engine.detailRecords.itens.some((item) => item.pedido_id === master.id && item.produto === "Item smoke")
    && engine.detailRecords.parcelas.some((parcela) => parcela.pedido_id === master.id && parcela.numero === 1);
});

await browser.close();

if (errors.length) {
  throw new Error("Erros no console: " + errors.join(" | "));
}

console.log("master detail create flow smoke ok");
