(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const engine = new global.CrudEngine({
      root: "#crud-demo-root",
      configUrl: "public/config/crud-engine.config.json",
      definitionUrl: "examples/clientes.crud.json",
      httpClient: new global.DemoMockHttpClient()
    });

    engine.init().then(function(instance) {
      global.clientesCrudEngine = instance;
    }).catch(function(error) {
      console.error("Falha ao inicializar demo CRUD.", error);
    });
  });
})(window);
