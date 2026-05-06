(function(global) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const engine = new global.HomeEngine({
      root: "#home-demo-root",
      configUrl: "public/config/crud-engine.config.json",
      definitionUrl: "examples/home.home.json",
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
})(window);
