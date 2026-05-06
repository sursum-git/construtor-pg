(function(global) {
  "use strict";

  let clientesEngine = null;
  let pendingTheme = null;

  document.addEventListener("DOMContentLoaded", function() {
    const engine = new global.CrudEngine({
      root: "#crud-demo-root",
      configUrl: "public/config/crud-engine.config.json",
      definitionUrl: "examples/clientes.crud.json",
      hideThemeSwitch: shouldHideThemeSwitch(),
      hideHeader: shouldHideProgramHeader(),
      httpClient: new global.DemoMockHttpClient()
    });

    engine.init().then(function(instance) {
      clientesEngine = instance;
      global.clientesCrudEngine = instance;
      if (pendingTheme) {
        applyExternalTheme(pendingTheme);
      }
    }).catch(function(error) {
      console.error("Falha ao inicializar demo CRUD.", error);
    });
  });

  global.addEventListener("message", function(event) {
    if (!shouldHideThemeSwitch() || event.source !== global.parent) {
      return;
    }
    const data = event.data || {};
    if (data.type !== "homeThemeChange") {
      return;
    }
    const theme = data.theme === "dark" ? "dark" : data.theme === "light" ? "light" : "";
    if (!theme) {
      return;
    }
    pendingTheme = theme;
    applyExternalTheme(theme);
  });

  function shouldHideThemeSwitch() {
    return readBooleanQueryParam("hideThemeSwitch");
  }

  function shouldHideProgramHeader() {
    return readBooleanQueryParam("hideProgramHeader");
  }

  function readBooleanQueryParam(name) {
    try {
      const params = new URLSearchParams(global.location.search);
      const value = params.get(name);
      return value === "1" || value === "true";
    } catch (_) {
      return false;
    }
  }

  function applyExternalTheme(theme) {
    if (!clientesEngine || typeof clientesEngine.applyTheme !== "function") {
      return;
    }
    clientesEngine.applyTheme(theme, { persist: false });
    if (clientesEngine.themeInputElement) {
      clientesEngine.themeInputElement.prop("checked", theme === "dark");
    }
    if (clientesEngine.themeToggleElement && typeof clientesEngine.updateThemeToggleLabel === "function") {
      clientesEngine.updateThemeToggleLabel(clientesEngine.themeToggleElement);
    }
  }
})(window);
