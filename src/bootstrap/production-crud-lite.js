(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const root = document.getElementById("crud-lite-production-root");
    if (!root) {
      return;
    }

    if (global.CrudLiteUiService && typeof global.CrudLiteUiService.activate === "function") {
      global.CrudLiteUiService.activate();
    }

    const params = readParams();
    const screenId = getParam(params, "screenId") || root.getAttribute("data-screen-id") || "";
    const configUrl = getParam(params, "configUrl") ||
      root.getAttribute("data-config-url") ||
      "../public/config/crud-engine.production.config.json";

    if (!screenId) {
      renderBootstrapError(root, "Informe o parametro screenId para abrir a tela.");
      return;
    }

    const httpClient = new global.CrudHttpClient({
      allowLocalFallback: false
    });
    const configLoader = new global.CrudConfigLoader();

    configLoader.load({ configUrl }).then(function(config) {
      const securityPolicy = global.CrudUtils.normalizeSecurityPolicy(config || {}, {});
      const request = global.CrudUtils.buildScreenDefinitionRequest(screenId, securityPolicy, "");
      return httpClient.request(request).then(function(definition) {
        if (String(definition && definition.pageType || "crud") !== "crud") {
          throw global.CrudUtils.makeError("CRUD_LITE_UNSUPPORTED_PAGE_TYPE", "A entrada Lite v1 suporta apenas telas CRUD.", {
            pageType: definition && definition.pageType
          });
        }
        return new global.CrudEngine({
          root: "#crud-lite-production-root",
          screenId,
          config,
          uiMode: "lite",
          productionErrors: true,
          httpClient
        }).init();
      });
    }).then(function(instance) {
      global.productionCrudLiteEngine = instance;
    }).catch(function(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Nao foi possivel carregar a tela Lite.");
      renderBootstrapError(root, normalized.message);
    });
  });

  function readParams() {
    try {
      return new URLSearchParams(global.location.search || "");
    } catch (_) {
      return null;
    }
  }

  function getParam(params, name) {
    if (!params) {
      return "";
    }
    return String(params.get(name) || "").trim();
  }

  function renderBootstrapError(root, message) {
    root.innerHTML = "";
    const element = document.createElement("section");
    element.className = "crud-message crud-message-error";
    const title = document.createElement("strong");
    title.textContent = message || "Erro ao carregar a tela.";
    element.appendChild(title);
    root.appendChild(element);
  }
})(window);
