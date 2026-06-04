(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    if (global.CrudLiteUiService && typeof global.CrudLiteUiService.activate === "function") {
      global.CrudLiteUiService.activate();
    }

    const root = document.getElementById("crud-lite-example-root");
    const code = document.getElementById("crud-lite-example-code");
    if (!root || !global.CrudExamplesCatalog) {
      return;
    }

    const exampleId = document.body.getAttribute("data-example-id") || "consulta-basica-lite";
    const definition = global.CrudExamplesCatalog.buildDefinition(exampleId);
    const config = global.CrudExamplesCatalog.buildConfig(exampleId, {
      assetPrefix: "../../"
    });
    const httpClient = new global.DemoMockHttpClient({
      storageSuffix: "crud-lite"
    });

    if (code) {
      code.textContent = global.CrudExamplesCatalog.getCode(exampleId);
    }

    new global.CrudEngine({
      root: "#crud-lite-example-root",
      definition,
      config,
      uiMode: "lite",
      runtimeMessages: false,
      httpClient
    }).init().then(function(engine) {
      global.currentCrudLiteExampleEngine = engine;
    }).catch(function(error) {
      const normalized = global.CrudUtils.unwrapError(error, "Erro ao carregar exemplo Lite.");
      global.CrudUtils.showMessage(normalized.message, "error");
    });
  });
})(window);
