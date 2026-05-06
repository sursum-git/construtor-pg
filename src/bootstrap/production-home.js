(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const root = document.getElementById("home-production-root");
    if (!root) {
      return;
    }

    const params = readParams();
    const screenId = getParam(params, "screenId") || root.getAttribute("data-screen-id") || "home";
    const configUrl = getParam(params, "configUrl") ||
      root.getAttribute("data-config-url") ||
      "../public/config/crud-engine.production.config.json";

    const engine = new global.HomeEngine({
      root: "#home-production-root",
      screenId,
      configUrl,
      productionErrors: true,
      httpClient: new global.CrudHttpClient({
        allowLocalFallback: false
      })
    });

    engine.init().then(function(instance) {
      global.productionHomeEngine = instance;
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

  function logBootstrapError() {
    if (global.console && typeof global.console.error === "function") {
      global.console.error("Falha ao inicializar Home de producao.");
    }
  }
})(window);
