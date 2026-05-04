(function(global, $) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function() {
    const catalog = global.CrudExamplesCatalog;
    const exampleId = document.body.getAttribute("data-example-id");
    const example = catalog && catalog.get(exampleId);

    if (!catalog || !example) {
      renderFatalError("Exemplo nao encontrado.");
      return;
    }

    renderPageHeader(example);
    renderCode(catalog.getCode(exampleId));
    initializeTabs();
    initializeEngine(example, catalog);
  });

  function renderPageHeader(example) {
    document.title = example.title + " - Exemplos CRUD Engine";
    setText("example-category", example.category);
    setText("example-title", example.title);
    setText("example-summary", example.summary);
  }

  function renderCode(code) {
    const target = document.getElementById("example-code");
    if (target) {
      target.textContent = code;
    }
  }

  function initializeTabs() {
    const tabs = $("#example-tabs");
    if (!tabs.length || !$.fn.kendoTabStrip) {
      return;
    }
    tabs.kendoTabStrip({
      animation: false,
      activate: function() {
        const engine = global.currentCrudExampleEngine;
        if (engine && engine.gridRenderer && engine.gridRenderer.grid) {
          engine.gridRenderer.grid.resize();
          engine.gridRenderer.grid.refresh();
        }
      }
    });
    const widget = tabs.data("kendoTabStrip");
    if (widget) {
      widget.select(0);
    }
  }

  function initializeEngine(example, catalog) {
    const root = document.getElementById("example-render-root");
    if (!root) {
      return;
    }

    const definition = catalog.buildDefinition(example.id);
    const config = catalog.buildConfig(example.id, { assetPrefix: "../../" });
    const httpClient = new global.DemoMockHttpClient({
      storageSuffix: "examples-" + example.id
    });
    const engine = new global.CrudEngine({
      root: "#example-render-root",
      definition,
      config,
      httpClient
    });

    engine.init().then(function(instance) {
      global.currentCrudExampleEngine = instance;
      runInitialAction(example, instance);
    }).catch(function(error) {
      console.error("Falha ao renderizar exemplo.", error);
    });
  }

  function runInitialAction(example, engine) {
    window.setTimeout(function() {
      switch (example.initialAction) {
        case "openFilter":
          engine.openFilters();
          break;
        case "openCreate":
          engine.openCreate();
          break;
        case "openEdit":
          engine.openRecord("edit", 1);
          break;
        case "openView":
          engine.openRecord("view", 1);
          break;
        case "openSort":
          engine.openSortWindow();
          break;
        case "openGroup":
          engine.openGroupWindow();
          break;
        case "openLayout":
          engine.openLayoutWindow();
          break;
      }
    }, 250);
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value || "";
    }
  }

  function renderFatalError(message) {
    const root = document.getElementById("example-render-root") || document.body;
    root.innerHTML = "<section class=\"crud-message crud-message-error\">" + escapeHtml(message) + "</section>";
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
})(window, jQuery);
