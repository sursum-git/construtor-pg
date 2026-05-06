(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const root = document.getElementById("crud-production-root");
    if (!root) {
      return;
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

    const engine = new global.CrudEngine({
      root: "#crud-production-root",
      screenId,
      configUrl,
      hideThemeSwitch: readBooleanParam(params, "hideThemeSwitch"),
      hideHeader: readBooleanParam(params, "hideProgramHeader"),
      productionErrors: true,
      httpClient: new global.CrudHttpClient({
        allowLocalFallback: false
      })
    });

    engine.init().then(function(instance) {
      global.productionCrudEngine = instance;
    }).catch(function(error) {
      logBootstrapError(error);
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

  function readBooleanParam(params, name) {
    const value = getParam(params, name).toLowerCase();
    return value === "1" || value === "true" || value === "yes";
  }

  function renderBootstrapError(root, message) {
    root.innerHTML = "";
    const element = document.createElement("section");
    element.className = "crud-message crud-message-error";
    element.textContent = message;
    root.appendChild(element);
  }

  function logBootstrapError() {
    if (global.console && typeof global.console.error === "function") {
      global.console.error("Falha ao inicializar CRUD de producao.");
    }
  }
})(window);
