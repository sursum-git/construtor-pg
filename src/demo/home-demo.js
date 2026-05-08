(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const params = readParams();
    const accessArea = getParam(params, "accessArea");
    const initialProgramId = getParam(params, "initialProgramId") ||
      (accessArea === "admin" ? "admin-parametros" : "");

    const engine = new global.HomeEngine({
      root: "#home-demo-root",
      configUrl: "public/config/crud-engine.config.json",
      definitionUrl: "examples/home.home.json",
      initialProgramId,
      httpClient: new global.DemoMockHttpClient({
        storageSuffix: "home-demo"
      })
    });

    engine.init().then(function(instance) {
      global.homeDemoEngine = instance;
    }).catch(function(error) {
      console.error("Falha ao inicializar Home Engine.", error);
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
})(window);
