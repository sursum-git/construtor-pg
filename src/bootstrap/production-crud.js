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

    const hideThemeSwitch = readBooleanParam(params, "hideThemeSwitch");
    const hideHeader = readBooleanParam(params, "hideProgramHeader");
    const initialFilters = readInitialFilters(params);
    const httpClient = new global.CrudHttpClient({
      allowLocalFallback: false
    });
    const configLoader = new global.CrudConfigLoader();

    configLoader.load({
      configUrl: configUrl
    }).then(function(config) {
      const securityPolicy = global.CrudUtils.normalizeSecurityPolicy(config || {}, {});
      const request = global.CrudUtils.buildScreenDefinitionRequest(screenId, securityPolicy, "");
      return httpClient.request(request).then(function(definition) {
        return bootstrapByPageType(definition, {
          root: "#crud-production-root",
          screenId: screenId,
          configUrl: configUrl,
          config: config,
          hideThemeSwitch: hideThemeSwitch,
          hideHeader: hideHeader,
          initialFilters: initialFilters,
          httpClient: httpClient
        });
      });
    }).then(function(instance) {
      global.productionCrudEngine = instance;
    }).catch(function(error) {
      logBootstrapError(error);
      renderBootstrapFailure(root, error);
    });
  });

  function bootstrapByPageType(definition, options) {
    const pageType = String(definition && definition.pageType || "crud").trim() || "crud";
    if (pageType === "crud") {
      return new global.CrudEngine({
        root: options.root,
        screenId: options.screenId,
        config: options.config,
        hideThemeSwitch: options.hideThemeSwitch,
        hideHeader: options.hideHeader,
        initialFilters: options.initialFilters,
        productionErrors: true,
        httpClient: options.httpClient
      }).init();
    }
    if (pageType === "process") {
      return new global.ProcessEngine({
        root: options.root,
        screenId: options.screenId,
        config: options.config,
        hideHeader: options.hideHeader,
        httpClient: options.httpClient
      }).init();
    }
    if (pageType === "custom") {
      return new global.CustomPageEngine({
        root: options.root,
        definition: definition,
        config: options.config,
        hideThemeSwitch: options.hideThemeSwitch,
        hideHeader: options.hideHeader,
        httpClient: options.httpClient
      }).init();
    }
    if (pageType === "analytics") {
      return new global.AnalyticsEngine({
        root: options.root,
        screenId: options.screenId,
        definition: definition,
        config: options.config,
        hideThemeSwitch: options.hideThemeSwitch,
        hideHeader: options.hideHeader,
        httpClient: options.httpClient
      }).init();
    }
    if (pageType === "report") {
      return new global.ReportEngine({
        root: options.root,
        screenId: options.screenId,
        definition: definition,
        config: options.config,
        hideThemeSwitch: options.hideThemeSwitch,
        hideHeader: options.hideHeader,
        httpClient: options.httpClient
      }).init();
    }
    if (pageType === "special_document") {
      return new global.SpecialDocumentEngine({
        root: options.root,
        screenId: options.screenId,
        definition: definition,
        config: options.config,
        hideHeader: options.hideHeader,
        httpClient: options.httpClient
      }).init();
    }
    if (pageType === "regulated_document") {
      return new global.RegulatedDocumentEngine({
        root: options.root,
        screenId: options.screenId,
        definition: definition,
        config: options.config,
        hideHeader: options.hideHeader,
        httpClient: options.httpClient
      }).init();
    }
    return Promise.reject(global.CrudUtils.makeError("UNSUPPORTED_PAGE_TYPE", "Tipo de pagina nao suportado na entrada de producao.", {
      pageType: pageType
    }));
  }

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

  function readInitialFilters(params) {
    const filters = [];
    if (!params) {
      return filters;
    }
    params.forEach(function(value, key) {
      if (String(key || "").indexOf("filter__") !== 0) {
        return;
      }
      const field = String(key.slice("filter__".length) || "").trim();
      const normalizedValue = String(value || "").trim();
      if (!field || !normalizedValue) {
        return;
      }
      filters.push({
        field: field,
        operator: "eq",
        value: normalizedValue,
        displayValue: normalizedValue
      });
    });
    return filters;
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
      global.console.error("Falha ao inicializar pagina de producao.");
    }
  }

  function renderBootstrapFailure(root, error) {
    const unwrapped = global.CrudUtils.unwrapError(error, "Falha ao inicializar pagina de producao.");
    renderBootstrapError(root, unwrapped && unwrapped.message ? unwrapped.message : "Falha ao inicializar pagina de producao.");
  }
})(window);
