import { chromium } from "playwright";
import path from "node:path";

const repoRoot = process.cwd();

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function setupPage(page) {
  await page.setContent("<!doctype html><html><body></body></html>");
  await page.addScriptTag({ path: path.join(repoRoot, "vendor", "jquery", "jquery-4.0.0.min.js") });
  await page.addScriptTag({ path: path.join(repoRoot, "src", "crud-engine", "CrudUtils.js") });
  await page.addScriptTag({ content: `
    window.kendo = {
      destroy: function() {},
      toString: function(value) { return String(value || ""); }
    };
    (function($) {
      $.fn.kendoButton = function(options) {
        return this.each(function() {
          const element = $(this);
          const widget = {
            options: options || {},
            enable: function(enabled) {
              element.prop("disabled", enabled === false);
              element.attr("aria-disabled", enabled === false ? "true" : "false");
            }
          };
          element.data("kendoButton", widget);
          if (options && options.enable === false) {
            widget.enable(false);
          }
        });
      };
      $.fn.kendoTextBox = function(options) {
        return this.each(function() {
          const element = $(this);
          if (options && options.value != null) {
            element.val(options.value);
          }
          element.data("kendoTextBox", {
            value: function(nextValue) {
              if (arguments.length) {
                element.val(nextValue == null ? "" : nextValue);
              }
              return element.val();
            },
            enable: function(enabled) {
              element.prop("disabled", enabled === false);
            },
            readonly: function(readonly) {
              element.prop("readonly", readonly === true);
            }
          });
        });
      };
      $.fn.kendoWindow = function(options) {
        return this.each(function() {
          const element = $(this);
          const widget = {
            options: options || {},
            center: function() { return widget; },
            open: function() { return widget; },
            maximize: function() { return widget; },
            title: function() { return widget; },
            close: function() {
              if (options && typeof options.close === "function") {
                options.close({ preventDefault: function() {} });
              }
              return widget;
            },
            destroy: function() {
              element.removeData("kendoWindow");
            }
          };
          element.data("kendoWindow", widget);
        });
      };
      $.fn.kendoTabStrip = function() {
        return this.each(function() {
          $(this).data("kendoTabStrip", {
            select: function() {},
            destroy: function() {}
          });
        });
      };
    })(window.jQuery);
  ` });
  await page.addScriptTag({ path: path.join(repoRoot, "src", "crud-engine", "CrudKendoFormRenderer.js") });
}

async function runRuntimeMessageTimerGuard(page) {
  await page.addScriptTag({ path: path.join(repoRoot, "src", "crud-engine", "CrudEngine.js") });
  return page.evaluate(() => {
    let pollCount = 0;
    let intervalDelay = null;
    const originalSetInterval = window.setInterval;
    window.setInterval = function(callback, delay) {
      intervalDelay = delay;
      return originalSetInterval(callback, delay);
    };
    try {
      const engine = Object.create(window.CrudEngine.prototype);
      engine.definition = {};
      engine.sessionRevoked = false;
      engine.runtimeMessageTimer = null;
      engine.getRuntimeApiEndpoint = () => ({ url: "/api/runtime/messages", method: "POST" });
      engine.pollRuntimeMessages = () => {
        pollCount += 1;
      };
      engine.startRuntimeMessageTimer(10);
      window.clearInterval(engine.runtimeMessageTimer);
      engine.runtimeMessageTimer = null;
      return { pollCount, intervalDelay };
    } finally {
      window.setInterval = originalSetInterval;
    }
  });
}

async function runRuntimeMessageEventGuard(page) {
  return page.evaluate(() => {
    let eventSourceCount = 0;
    let startupDelay = null;
    const originalEventSource = window.EventSource;
    const originalSetTimeout = window.setTimeout;
    window.EventSource = function(url) {
      eventSourceCount += 1;
      this.url = url;
      this.readyState = 0;
      this.addEventListener = function() {};
      this.close = function() {};
    };
    window.EventSource.OPEN = 1;
    window.setTimeout = function(callback, delay) {
      startupDelay = delay;
      return 12345;
    };
    try {
      const engine = Object.create(window.CrudEngine.prototype);
      engine.definition = { runtime: { messages: { enabled: true, pollIntervalSeconds: 10, events: { enabled: true, url: "/api/runtime/events" } } } };
      engine.securityPolicy = {};
      engine.sessionRevoked = false;
      engine.runtimeMessageTimer = null;
      engine.runtimeEventSource = null;
      engine.runtimeEventFallbackTimer = null;
      engine.httpClient = {
        buildRuntimeEventUrl: () => "/api/runtime/events?screenId=cadastros.tipo-produto"
      };
      engine.getRuntimeApiEndpoint = () => ({ url: "/api/runtime/messages", method: "POST" });
      engine.pollRuntimeMessages = () => {};
      engine.startRuntimeMessagePolling();
      return { eventSourceCount, startupDelay };
    } finally {
      window.EventSource = originalEventSource;
      window.setTimeout = originalSetTimeout;
    }
  });
}

async function runCreateSubmitGuard(page) {
  return page.evaluate(async () => {
    let requestCount = 0;
    let resolveRequest;
    let savedCount = 0;
    let closedCount = 0;
    const requestPromise = new Promise((resolve) => {
      resolveRequest = resolve;
    });
    const definition = {
      dataModel: {
        primaryKey: "id",
        fields: {
          id: { label: "ID", type: "integer", editable: false },
          descricao: { label: "Descricao", type: "string", nullable: false }
        }
      },
      permissions: { create: true, edit: true, delete: true },
      api: {
        create: { url: "/api/create", method: "POST" },
        update: { url: "/api/update", method: "POST" },
        delete: { url: "/api/delete", method: "POST" }
      },
      form: {
        id: "tipo-produto-form",
        layout: "tabs",
        behavior: { closeOnSave: true, closeOnCancel: true },
        tabs: [{
          id: "geral",
          title: "Geral",
          sections: [{
            id: "principal",
            title: "Principal",
            fields: [{ field: "id", readonly: true }, { field: "descricao" }]
          }]
        }]
      }
    };
    const renderer = new window.CrudKendoFormRenderer({
      definition,
      httpClient: {
        request: () => {
          requestCount += 1;
          return requestPromise;
        }
      },
      onSaved: () => { savedCount += 1; },
      onClosed: () => { closedCount += 1; }
    });
    renderer.open("create", {});
    const input = document.querySelector("[name='descricao']");
    input.value = "Produto smoke";
    input.dispatchEvent(new Event("change", { bubbles: true }));
    const confirmButton = Array.from(document.querySelectorAll("button"))
      .find((button) => button.textContent.trim() === "Confirmar");
    confirmButton.click();
    confirmButton.click();
    const disabledWhilePending = confirmButton.disabled === true;
    resolveRequest({ id: 101, descricao: "Produto smoke" });
    await Promise.resolve();
    await Promise.resolve();
    return {
      requestCount,
      savedCount,
      closedCount,
      formStillOpen: Boolean(document.querySelector(".crud-form")),
      disabledWhilePending
    };
  });
}

async function runDeleteSubmitGuard(page) {
  return page.evaluate(async () => {
    let deleteCount = 0;
    let resolveDelete;
    const deletePromise = new Promise((resolve) => {
      resolveDelete = resolve;
    });
    const definition = {
      dataModel: {
        primaryKey: "id",
        fields: {
          id: { label: "ID", type: "integer", editable: false },
          descricao: { label: "Descricao", type: "string" }
        }
      },
      permissions: { create: true, edit: true, delete: true },
      form: {
        id: "tipo-produto-form",
        layout: "tabs",
        behavior: { closeOnSave: true, closeOnCancel: true },
        tabs: [{
          id: "geral",
          title: "Geral",
          sections: [{
            id: "principal",
            title: "Principal",
            fields: [{ field: "id", readonly: true }, { field: "descricao" }]
          }]
        }]
      }
    };
    const renderer = new window.CrudKendoFormRenderer({
      definition,
      httpClient: { request: () => Promise.resolve({}) },
      onDelete: () => {
        deleteCount += 1;
        return deletePromise;
      }
    });
    renderer.open("delete", { id: 101, descricao: "Produto smoke" });
    const confirmButton = Array.from(document.querySelectorAll("button"))
      .find((button) => button.textContent.trim() === "Confirmar");
    confirmButton.click();
    confirmButton.click();
    const disabledWhilePending = confirmButton.disabled === true;
    resolveDelete(true);
    await Promise.resolve();
    await Promise.resolve();
    return {
      deleteCount,
      formStillOpen: Boolean(document.querySelector(".crud-form")),
      disabledWhilePending
    };
  });
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
const errors = [];
page.on("pageerror", (error) => errors.push(error.message));
page.on("console", (message) => {
  if (message.type() === "error") {
    errors.push(message.text());
  }
});

try {
  await setupPage(page);
  const timerResult = await runRuntimeMessageTimerGuard(page);
  const eventResult = await runRuntimeMessageEventGuard(page);
  await page.setContent("<!doctype html><html><body></body></html>");
  await setupPage(page);
  const createResult = await runCreateSubmitGuard(page);
  await page.setContent("<!doctype html><html><body></body></html>");
  await setupPage(page);
  const deleteResult = await runDeleteSubmitGuard(page);

  assert(timerResult.pollCount === 0, `Polling runtime disparou imediatamente ${timerResult.pollCount} vez(es).`);
  assert(timerResult.intervalDelay === 10000, `Intervalo inicial de polling esperado 10000ms, recebido ${timerResult.intervalDelay}.`);
  assert(eventResult.eventSourceCount === 0, `Canal runtime de eventos abriu imediatamente ${eventResult.eventSourceCount} vez(es).`);
  assert(eventResult.startupDelay === 10000, `Abertura inicial de eventos esperada apos 10000ms, recebido ${eventResult.startupDelay}.`);
  assert(createResult.requestCount === 1, `Confirmar inclusao disparou ${createResult.requestCount} requisicoes.`);
  assert(createResult.disabledWhilePending, "Confirmar inclusao deve ficar desabilitado durante o processamento.");
  assert(createResult.savedCount === 1, "Inclusao deve notificar salvamento uma vez.");
  assert(createResult.closedCount === 1, "Janela de inclusao deve fechar apos sucesso.");
  assert(!createResult.formStillOpen, "Formulario de inclusao permaneceu aberto apos sucesso.");
  assert(deleteResult.deleteCount === 1, `Confirmar exclusao disparou ${deleteResult.deleteCount} requisicoes.`);
  assert(deleteResult.disabledWhilePending, "Confirmar exclusao deve ficar desabilitado durante o processamento.");
  assert(!deleteResult.formStillOpen, "Formulario de exclusao permaneceu aberto apos sucesso.");
  assert(errors.length === 0, "Erros JavaScript detectados: " + errors.join(" | "));

  console.log(JSON.stringify({ timerResult, eventResult, createResult, deleteResult }, null, 2));
} finally {
  await browser.close();
}
